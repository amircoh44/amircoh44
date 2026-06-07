"""Public storefront: landing, features, pricing, FAQ, account lookup,
(sandbox) checkout and download."""
from datetime import datetime, timedelta

from flask import Blueprint, render_template, request, redirect, url_for, flash

from .models import db, Customer, License, Coupon
from . import licensing

bp = Blueprint("store", __name__)


@bp.route("/")
def index():
    return render_template("store/index.html")


@bp.route("/features")
def features():
    return render_template("store/features.html")


@bp.route("/pricing")
def pricing():
    return render_template("store/pricing.html")


@bp.route("/faq")
def faq():
    return render_template("store/faq.html")


@bp.route("/contact")
def contact():
    return render_template("store/contact.html")


@bp.route("/download")
def download():
    return render_template("store/download.html")


@bp.route("/account", methods=["GET", "POST"])
def account():
    result = None
    query = ""
    if request.method == "POST":
        query = request.form.get("q", "").strip()
        cust = Customer.query.filter_by(email=query.lower()).first()
        licenses = []
        if cust:
            licenses = cust.licenses
        else:
            lic = License.query.filter_by(key=query.upper()).first()
            if lic:
                cust = lic.customer
                licenses = [lic]
        result = {"customer": cust, "licenses": licenses}
    return render_template("store/account.html", result=result, query=query,
                           edition_for=licensing.edition_for_license,
                           active_fn=licensing.is_license_active,
                           seats=licensing.seats_used)


@bp.route("/checkout", methods=["GET", "POST"])
def checkout():
    edition = request.values.get("edition", "pro")
    term = request.values.get("term", "annual")
    tier = request.values.get("tier", "1")
    if edition not in ("pro", "expert"):
        edition = "pro"
    if term not in ("annual", "lifetime"):
        term = "annual"

    base = licensing.price_for(edition, term, tier) or 0
    code = request.values.get("coupon", "").strip().upper()
    coupon = None
    invalid_coupon = False
    final = base
    if code:
        coupon = Coupon.query.filter_by(code=code).first()
        if licensing.coupon_is_valid(coupon):
            final = licensing.apply_coupon(base, coupon)
        else:
            coupon = None
            invalid_coupon = True

    if request.method == "POST" and request.form.get("place_order"):
        email = request.form.get("email", "").strip().lower()
        name = request.form.get("name", "").strip()
        if not email:
            flash("Please enter your email address.", "error")
        else:
            cust = Customer.query.filter_by(email=email).first()
            if not cust:
                cust = Customer(email=email, name=name)
                db.session.add(cust)
                db.session.flush()
            expires = None if term == "lifetime" else datetime.utcnow() + timedelta(days=365)
            lic = License(key=licensing.generate_key(), customer_id=cust.id, edition=edition,
                          term=term, site_limit=licensing.site_limit_for_tier(tier),
                          status="active", expires_at=expires, note="Storefront (sandbox) order")
            db.session.add(lic)
            if coupon:
                coupon.used_count = (coupon.used_count or 0) + 1
            db.session.commit()
            return redirect(url_for("store.success", key=lic.key))

    tier_label = dict((k, l) for k, l, _ in licensing.SITE_TIERS).get(tier, tier)
    return render_template("store/checkout.html", edition=edition, term=term, tier=tier,
                           tier_label=tier_label, base=base, final=final, coupon=coupon,
                           code=code, invalid_coupon=invalid_coupon)


@bp.route("/success/<key>")
def success(key):
    lic = License.query.filter_by(key=key).first_or_404()
    return render_template("store/success.html", lic=lic)
