"""Tests for the license REST API the WordPress plugin calls."""
from datetime import datetime, timedelta

from server.models import db, Customer, License


def _issue(app, key="SPR-TEST-AAAA-BBBB-CCCC", **kw):
    with app.app_context():
        cust = Customer(email="api@example.com", name="API")
        db.session.add(cust)
        db.session.flush()
        defaults = dict(key=key, customer_id=cust.id, edition="pro", term="annual",
                        site_limit=1, status="active",
                        expires_at=datetime.utcnow() + timedelta(days=365))
        defaults.update(kw)
        db.session.add(License(**defaults))
        db.session.commit()
    return key


def test_ping(client):
    assert client.get("/api/v1/ping").get_json()["success"] is True


def test_activate_within_and_over_limit(app, client):
    key = _issue(app, site_limit=1)
    d = client.post("/api/v1/activate", json={"key": key, "site_url": "https://one.com"}).get_json()
    assert d["success"] and d["edition"] == "pro" and d["sites_used"] == 1

    # Re-activating the same site does not consume another seat.
    d = client.post("/api/v1/activate", json={"key": key, "site_url": "https://one.com"}).get_json()
    assert d["sites_used"] == 1

    # A second distinct site is rejected on a 1-site license.
    d = client.post("/api/v1/activate", json={"key": key, "site_url": "https://two.com"}).get_json()
    assert not d["success"] and "limit" in d["message"].lower()


def test_unknown_key_is_free(client):
    r = client.post("/api/v1/activate", json={"key": "SPR-NOPE-NOPE-NOPE-NOPE", "site_url": "https://x.com"})
    assert r.status_code == 404
    assert r.get_json()["edition"] == "free"


def test_expired_license_resolves_free(app, client):
    key = _issue(app, key="SPR-EXPI-RED0-0000-0000",
                 expires_at=datetime.utcnow() - timedelta(days=1))
    d = client.post("/api/v1/validate", json={"key": key, "site_url": "https://a.com"}).get_json()
    assert not d["success"] and d["edition"] == "free"


def test_deactivate_frees_a_seat(app, client):
    key = _issue(app, key="SPR-DEAC-TIVE-0000-0000", site_limit=1)
    client.post("/api/v1/activate", json={"key": key, "site_url": "https://a.com"})
    assert client.post("/api/v1/deactivate", json={"key": key, "site_url": "https://a.com"}).get_json()["success"]
    # Seat freed -> a new site can now activate.
    d = client.post("/api/v1/activate", json={"key": key, "site_url": "https://b.com"}).get_json()
    assert d["success"]
