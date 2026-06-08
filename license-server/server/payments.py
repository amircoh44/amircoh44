"""Stripe checkout integration.

If STRIPE_SECRET_KEY is set (and the `stripe` library is installed), the
storefront hands off to a Stripe Checkout Session and the license is created by
the webhook on `checkout.session.completed`. Otherwise the storefront falls back
to the sandbox flow that issues a key immediately.
"""
import os
from datetime import datetime, timedelta

from .models import db, Customer, License, Coupon
from . import licensing

try:
    import stripe  # type: ignore
except Exception:  # pragma: no cover - library optional
    stripe = None


def stripe_enabled():
    return stripe is not None and bool(os.environ.get("STRIPE_SECRET_KEY"))


def _client():
    stripe.api_key = os.environ.get("STRIPE_SECRET_KEY")
    return stripe


def create_session(edition, term, tier, email, code, amount_usd, base_url):
    """Create a Stripe Checkout Session and return its URL."""
    client = _client()
    meta = {"edition": edition, "term": term, "tier": tier, "email": email, "coupon": code or ""}
    mode = "subscription" if term == "annual" else "payment"

    price_data = {
        "currency": os.environ.get("CURRENCY", "usd"),
        "product_data": {"name": "SEO Sprinkler %s — %s" % (edition.capitalize(), tier)},
        "unit_amount": int(round(amount_usd * 100)),
    }
    if mode == "subscription":
        price_data["recurring"] = {"interval": "year"}

    base = base_url.rstrip("/")
    kwargs = dict(
        mode=mode,
        line_items=[{"price_data": price_data, "quantity": 1}],
        metadata=meta,
        success_url=base + "/checkout/thanks?session_id={CHECKOUT_SESSION_ID}",
        cancel_url=base + "/pricing",
    )
    if email:
        kwargs["customer_email"] = email
    if mode == "subscription":
        kwargs["subscription_data"] = {"metadata": meta}

    session = client.checkout.Session.create(**kwargs)
    return session.url


def issue_from_metadata(meta):
    """Create a License from a completed checkout's metadata. Returns the License."""
    email = (meta.get("email") or "").strip().lower()
    if not email:
        return None
    edition = meta.get("edition", "pro")
    if edition not in ("pro", "expert"):
        edition = "pro"
    term = meta.get("term", "annual")
    tier = meta.get("tier", "1")
    code = (meta.get("coupon") or "").strip().upper()

    cust = Customer.query.filter_by(email=email).first()
    if not cust:
        cust = Customer(email=email)
        db.session.add(cust)
        db.session.flush()

    expires = None if term == "lifetime" else datetime.utcnow() + timedelta(days=365)
    lic = License(key=licensing.generate_key(), customer_id=cust.id, edition=edition, term=term,
                  site_limit=licensing.site_limit_for_tier(tier), status="active",
                  expires_at=expires, note="Stripe order")
    db.session.add(lic)
    if code:
        coupon = Coupon.query.filter_by(code=code).first()
        if coupon:
            coupon.used_count = (coupon.used_count or 0) + 1
    db.session.commit()
    return lic
