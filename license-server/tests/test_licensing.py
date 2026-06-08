"""Unit tests for the pure licensing logic."""
from datetime import datetime, timedelta
from types import SimpleNamespace

from server import licensing


def L(**kw):
    base = dict(status="active", expires_at=datetime.utcnow() + timedelta(days=10),
                edition="pro", site_limit=1, activations=[])
    base.update(kw)
    return SimpleNamespace(**base)


def A(url):
    return SimpleNamespace(site_url=url)


def test_key_format():
    k = licensing.generate_key()
    assert k.startswith("SPR-")
    assert len(k.split("-")) == 5


def test_active_and_edition():
    assert licensing.is_license_active(L())
    assert licensing.edition_for_license(L()) == "pro"

    expired = L(expires_at=datetime.utcnow() - timedelta(days=1))
    assert not licensing.is_license_active(expired)
    assert licensing.edition_for_license(expired) == "free"

    assert licensing.edition_for_license(L(status="revoked")) == "free"
    assert licensing.is_license_active(L(expires_at=None))  # lifetime


def test_seat_limits():
    one = L(site_limit=1, activations=[A("https://a.com")])
    assert licensing.can_activate(one, "https://a.com")       # known site
    assert not licensing.can_activate(one, "https://b.com")   # over limit

    unlimited = L(site_limit=0, activations=[A("x"), A("y"), A("z")])
    assert licensing.can_activate(unlimited, "https://new.com")


def test_prices():
    assert licensing.price_for("pro", "annual", "1") == 39
    assert licensing.price_for("expert", "lifetime", "agency") == 799
    assert licensing.site_limit_for_tier("agency") == 0
    assert licensing.site_limit_for_tier("5") == 5


def test_coupon():
    good = SimpleNamespace(active=True, expires_at=None, max_uses=None, used_count=0, percent_off=40)
    assert licensing.coupon_is_valid(good)
    assert licensing.apply_coupon(100, good) == 60

    disabled = SimpleNamespace(active=False, expires_at=None, max_uses=None, used_count=0, percent_off=40)
    assert not licensing.coupon_is_valid(disabled)
    assert licensing.apply_coupon(100, disabled) == 100

    used_up = SimpleNamespace(active=True, expires_at=None, max_uses=1, used_count=1, percent_off=50)
    assert not licensing.coupon_is_valid(used_up)
