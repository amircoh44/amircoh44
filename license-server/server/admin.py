"""Password-protected admin panel: dashboard, customers, licenses, coupons."""
from datetime import datetime, timedelta
from functools import wraps

from flask import (Blueprint, render_template, request, redirect, url_for,
                   session, flash)
from werkzeug.security import check_password_hash

from .models import db, AdminUser, Customer, License, Activation, Coupon
from . import licensing

bp = Blueprint("admin", __name__)


def login_required(f):
    @wraps(f)
    def wrap(*args, **kwargs):
        if not session.get("admin_id"):
            return redirect(url_for("admin.login", next=request.path))
        return f(*args, **kwargs)
    return wrap


@bp.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "POST":
        user = request.form.get("username", "").strip()
        pw = request.form.get("password", "")
        admin = AdminUser.query.filter_by(username=user).first()
        if admin and check_password_hash(admin.password_hash, pw):
            session["admin_id"] = admin.id
            session["admin_user"] = admin.username
            flash("Welcome back, %s." % admin.username, "success")
            return redirect(request.args.get("next") or url_for("admin.dashboard"))
        flash("Invalid credentials.", "error")
    return render_template("admin/login.html")


@bp.route("/logout")
def logout():
    session.clear()
    flash("Signed out.", "success")
    return redirect(url_for("admin.login"))


@bp.route("/")
@login_required
def dashboard():
    now = datetime.utcnow()
    licenses = License.query.all()
    active = [l for l in licenses if licensing.is_license_active(l, now)]

    mrr = 0.0
    for l in active:
        tier = "agency" if (l.site_limit or 0) == 0 else ("5" if l.site_limit >= 5 else "1")
        price = licensing.price_for(l.edition, "annual", tier) or 0
        if l.expires_at is not None:           # lifetime contributes no recurring revenue
            mrr += price / 12.0

    stats = {
        "customers": Customer.query.count(),
        "licenses": len(licenses),
        "active": len(active),
        "activations": Activation.query.count(),
        "coupons": Coupon.query.filter_by(active=True).count(),
        "mrr": round(mrr),
    }
    recent = License.query.order_by(License.created_at.desc()).limit(8).all()
    return render_template("admin/dashboard.html", stats=stats, recent=recent,
                           active_fn=licensing.is_license_active)


@bp.route("/customers")
@login_required
def customers():
    q = request.args.get("q", "").strip()
    query = Customer.query
    if q:
        query = query.filter(Customer.email.contains(q) | Customer.name.contains(q))
    rows = query.order_by(Customer.created_at.desc()).all()
    return render_template("admin/customers.html", rows=rows, q=q)


@bp.route("/customers/<int:cid>")
@login_required
def customer(cid):
    c = db.get_or_404(Customer, cid)
    return render_template("admin/customer.html", c=c,
                           edition_for=licensing.edition_for_license,
                           active_fn=licensing.is_license_active)


@bp.route("/licenses")
@login_required
def licenses():
    q = request.args.get("q", "").strip()
    status = request.args.get("status", "")
    query = License.query.join(Customer)
    if q:
        query = query.filter(License.key.contains(q.upper()) | Customer.email.contains(q))
    rows = query.order_by(License.created_at.desc()).all()
    if status == "active":
        rows = [r for r in rows if licensing.is_license_active(r)]
    elif status == "inactive":
        rows = [r for r in rows if not licensing.is_license_active(r)]
    return render_template("admin/licenses.html", rows=rows, q=q, status=status,
                           active_fn=licensing.is_license_active, seats=licensing.seats_used)


@bp.route("/licenses/new", methods=["GET", "POST"])
@login_required
def license_new():
    if request.method == "POST":
        email = request.form.get("email", "").strip().lower()
        name = request.form.get("name", "").strip()
        edition = request.form.get("edition", "pro")
        term = request.form.get("term", "annual")
        tier = request.form.get("tier", "1")
        if not email:
            flash("Customer email is required.", "error")
            return redirect(url_for("admin.license_new"))
        if edition not in ("pro", "expert"):
            edition = "pro"
        cust = Customer.query.filter_by(email=email).first()
        if not cust:
            cust = Customer(email=email, name=name)
            db.session.add(cust)
            db.session.flush()
        expires = None if term == "lifetime" else datetime.utcnow() + timedelta(days=365)
        lic = License(key=licensing.generate_key(), customer_id=cust.id, edition=edition,
                      term=term, site_limit=licensing.site_limit_for_tier(tier),
                      status="active", expires_at=expires, note="Issued from admin")
        db.session.add(lic)
        db.session.commit()
        flash("Issued %s license %s" % (edition, lic.key), "success")
        return redirect(url_for("admin.license_detail", lid=lic.id))
    return render_template("admin/license_form.html", tiers=licensing.SITE_TIERS)


@bp.route("/licenses/<int:lid>")
@login_required
def license_detail(lid):
    lic = db.get_or_404(License, lid)
    return render_template("admin/license_detail.html", lic=lic,
                           active=licensing.is_license_active(lic),
                           seats=licensing.seats_used(lic))


@bp.route("/licenses/<int:lid>/action", methods=["POST"])
@login_required
def license_action(lid):
    lic = db.get_or_404(License, lid)
    action = request.form.get("action")
    if action == "revoke":
        lic.status = "revoked"
        flash("License revoked.", "success")
    elif action == "reactivate":
        lic.status = "active"
        flash("License reactivated.", "success")
    elif action == "extend":
        base = lic.expires_at if (lic.expires_at and lic.expires_at > datetime.utcnow()) else datetime.utcnow()
        lic.expires_at = base + timedelta(days=365)
        lic.term = "annual"
        flash("Extended by 1 year.", "success")
    elif action == "make_lifetime":
        lic.expires_at = None
        lic.term = "lifetime"
        flash("Converted to lifetime.", "success")
    elif action == "regenerate":
        lic.key = licensing.generate_key()
        flash("New key generated.", "success")
    db.session.commit()
    return redirect(url_for("admin.license_detail", lid=lic.id))


@bp.route("/activations/<int:aid>/delete", methods=["POST"])
@login_required
def activation_delete(aid):
    act = db.get_or_404(Activation, aid)
    lid = act.license_id
    db.session.delete(act)
    db.session.commit()
    flash("Activation removed — seat freed.", "success")
    return redirect(url_for("admin.license_detail", lid=lid))


@bp.route("/coupons", methods=["GET", "POST"])
@login_required
def coupons():
    if request.method == "POST":
        code = request.form.get("code", "").strip().upper()
        try:
            pct = int(request.form.get("percent_off", "0") or 0)
        except ValueError:
            pct = 0
        if code and 1 <= pct <= 100 and not Coupon.query.filter_by(code=code).first():
            db.session.add(Coupon(code=code, percent_off=pct, note=request.form.get("note", "")))
            db.session.commit()
            flash("Coupon %s created." % code, "success")
        else:
            flash("Invalid percentage or a coupon with that code already exists.", "error")
        return redirect(url_for("admin.coupons"))
    rows = Coupon.query.order_by(Coupon.code).all()
    return render_template("admin/coupons.html", rows=rows)


@bp.route("/coupons/<int:cid>/toggle", methods=["POST"])
@login_required
def coupon_toggle(cid):
    c = db.get_or_404(Coupon, cid)
    c.active = not c.active
    db.session.commit()
    flash("Coupon %s %s." % (c.code, "enabled" if c.active else "disabled"), "success")
    return redirect(url_for("admin.coupons"))


@bp.route("/plans")
@login_required
def plans():
    return render_template("admin/plans.html")


@bp.route("/activations")
@login_required
def activations():
    rows = Activation.query.order_by(Activation.last_seen_at.desc()).limit(100).all()
    return render_template("admin/activations.html", rows=rows)
