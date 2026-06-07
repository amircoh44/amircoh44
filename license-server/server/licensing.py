"""Pure licensing logic: price matrix, key generation, edition resolution,
seat checks and coupon maths. No Flask/DB imports so it is easy to unit-test."""
import secrets
from datetime import datetime

# --- Price matrix (source of truth for the storefront) ----------------------
# Aggressive / land-grab pricing. Annual + one-time lifetime, per site tier.
PRICES = {
    "pro": {
        "label": "Pro",
        "tagline": "Automation + AI for growing sites",
        "annual": {"1": 39, "5": 79, "agency": 159},
        "lifetime": {"1": 119, "5": 239, "agency": 479},
    },
    "expert": {
        "label": "Expert",
        "tagline": "Pro + migration + syndication",
        "annual": {"1": 69, "5": 139, "agency": 279},
        "lifetime": {"1": 199, "5": 399, "agency": 799},
    },
}

# Site tiers: (key, label, numeric limit where 0 = unlimited).
SITE_TIERS = [
    ("1", "1 site", 1),
    ("5", "5 sites", 5),
    ("agency", "Agency — unlimited sites", 0),
]

EDITIONS = ("free", "pro", "expert")

# Support entitlement per edition (advertised + shown in-product).
SUPPORT = {
    "free": "Community forum & documentation",
    "pro": "Email support",
    "expert": "Priority email support — 24-hour response",
}


def support_for(edition):
    return SUPPORT.get(edition, SUPPORT["free"])


# Response-time SLA per tier. Expert is the committed SLA: 12h on business days,
# 24h over the weekend. Pro is a 48h target. Free / bug reports are best-effort.
def sla_due(tier, created_at):
    """Return the datetime a first response is due, or None for best-effort."""
    from datetime import timedelta
    if tier == "expert":
        hours = 24 if created_at.weekday() >= 5 else 12   # Sat=5, Sun=6 -> weekend
        return created_at + timedelta(hours=hours)
    if tier == "pro":
        return created_at + timedelta(hours=48)
    return None


def sla_target_label(tier):
    return {
        "expert": "12 hours (business days) / 24 hours (weekends)",
        "pro": "within 48 hours",
        "free": "best-effort (community & docs)",
    }.get(tier, "best-effort")


def is_priority(tier):
    return tier == "expert"

# Free-tier grace mirrored from the plugin (everything unlocked under N pages).
FREE_PAGE_LIMIT = 25

_ALPHABET = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"  # no ambiguous 0/O/1/I.


def generate_key():
    """e.g. SPR-7K3M-9QXA-2F4D-RTW8"""
    groups = ["".join(secrets.choice(_ALPHABET) for _ in range(4)) for _ in range(4)]
    return "SPR-" + "-".join(groups)


def site_limit_for_tier(tier_key):
    for key, _label, limit in SITE_TIERS:
        if key == tier_key:
            return limit
    return 1


def price_for(edition, term, tier_key):
    try:
        return PRICES[edition][term][tier_key]
    except KeyError:
        return None


def is_license_active(license, now=None):
    """A license is active if not revoked and not past its expiry (lifetime = no expiry)."""
    now = now or datetime.utcnow()
    if license.status != "active":
        return False
    if license.expires_at is not None and license.expires_at < now:
        return False
    return True


def edition_for_license(license, now=None):
    """The edition a site should run at: the license edition if active, else 'free'."""
    return license.edition if is_license_active(license, now) else "free"


def seats_used(license):
    return len(license.activations)


def site_is_activated(license, site_url):
    return any(a.site_url == site_url for a in license.activations)


def can_activate(license, site_url):
    """True if this site may take (or keep) a seat."""
    if site_is_activated(license, site_url):
        return True                      # re-activation of a known site is always fine.
    if license.site_limit and license.site_limit > 0:
        return seats_used(license) < license.site_limit
    return True                          # 0 / unlimited (agency).


def coupon_is_valid(coupon, now=None):
    now = now or datetime.utcnow()
    if not coupon or not coupon.active:
        return False
    if coupon.expires_at is not None and coupon.expires_at < now:
        return False
    if coupon.max_uses is not None and coupon.used_count >= coupon.max_uses:
        return False
    return True


def apply_coupon(price, coupon, now=None):
    """Return the discounted price (rounded to whole dollars)."""
    if price is None or not coupon_is_valid(coupon, now):
        return price
    return round(price * (100 - coupon.percent_off) / 100)
