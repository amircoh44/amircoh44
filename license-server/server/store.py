"""Public storefront: landing, features, pricing, FAQ, account lookup,
(sandbox) checkout and download."""
import os
from datetime import datetime, timedelta

from flask import Blueprint, render_template, request, redirect, url_for, flash

from .models import db, Customer, License, Coupon, Ticket
from . import licensing, payments, triage

bp = Blueprint("store", __name__)


def _resolve_license(license_key, email):
    """Find the submitter's best active license and resolve their tier."""
    lic = None
    if license_key:
        lic = License.query.filter_by(key=license_key.strip().upper()).first()
    if (not lic or not licensing.is_license_active(lic)) and email:
        cust = Customer.query.filter_by(email=email.strip().lower()).first()
        if cust:
            actives = [l for l in cust.licenses if licensing.is_license_active(l)]
            if actives:
                rank = {"expert": 2, "pro": 1, "free": 0}
                lic = sorted(actives, key=lambda l: rank.get(l.edition, 0), reverse=True)[0]
    tier = licensing.edition_for_license(lic) if lic else "free"
    return lic, tier


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
        elif payments.stripe_enabled():
            # Hand off to Stripe; the webhook issues the license on completion.
            base = os.environ.get("PUBLIC_BASE_URL") or request.url_root
            return redirect(payments.create_session(edition, term, tier, email,
                                                     code if coupon else "", final, base))
        else:
            # Sandbox: issue a working key immediately.
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


@bp.route("/checkout/thanks")
def thanks():
    """Stripe success landing — the license arrives via webhook moments later."""
    return render_template("store/thanks.html")


@bp.route("/support", methods=["GET", "POST"])
def support():
    """Open to everyone: support requests (tier-tagged + SLA) and bug reports."""
    if request.method == "POST":
        ttype = request.form.get("type", "support")
        if ttype not in ("support", "bug"):
            ttype = "support"
        email = request.form.get("email", "").strip().lower()
        license_key = request.form.get("license_key", "").strip()
        subject = request.form.get("subject", "").strip()
        message = request.form.get("message", "").strip()
        page_url = request.form.get("url", "").strip()

        if not message or (ttype == "support" and not email):
            flash("Please include a message" + (" and your email." if ttype == "support" else "."), "error")
        else:
            lic, tier = _resolve_license(license_key, email)
            verdict = triage.classify(subject, message, ttype)
            now = datetime.utcnow()
            status = {"quarantine": "flagged", "hold": "held", "queue": "queued"}[verdict["action"]]
            # SLA applies to support requests; bug reports are best-effort.
            due = licensing.sla_due(tier, now) if ttype == "support" else None
            priority = (licensing.is_priority(tier) and ttype == "support") or verdict["category"] == "security_report"
            ticket = Ticket(
                type=ttype, email=email, license_id=(lic.id if lic else None),
                license_key=(license_key.upper() if license_key else ""), tier=tier,
                subject=subject[:200], message=message, url=page_url[:255], created_at=now,
                sla_due_at=due, priority=priority, status=status,
                category=verdict["category"], risk_score=verdict["score"],
                reasons="; ".join(verdict["reasons"])[:400],
            )
            db.session.add(ticket)
            db.session.commit()
            return redirect(url_for("store.support_sent", tid=ticket.id))

    return render_template("store/support.html",
                           expert_sla=licensing.sla_target_label("expert"),
                           pro_sla=licensing.sla_target_label("pro"))


@bp.route("/support/sent/<int:tid>")
def support_sent(tid):
    ticket = db.get_or_404(Ticket, tid)
    return render_template("store/support_sent.html", t=ticket,
                           sla_label=licensing.sla_target_label(ticket.tier))
