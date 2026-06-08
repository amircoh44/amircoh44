"""Stripe payment flow: webhook issues licenses; sandbox is the default."""
from server import payments


def test_stripe_disabled_without_key(monkeypatch):
    monkeypatch.delenv("STRIPE_SECRET_KEY", raising=False)
    assert not payments.stripe_enabled()


def test_sandbox_checkout_is_default(client):
    # With no Stripe key, the storefront issues a key immediately.
    r = client.post("/checkout?edition=pro&term=annual&tier=1",
                    data={"edition": "pro", "term": "annual", "tier": "1",
                          "email": "sandbox@example.com", "place_order": "1"},
                    follow_redirects=True)
    assert b"all set" in r.data and b"SPR-" in r.data


def test_webhook_issues_license(client):
    event = {
        "type": "checkout.session.completed",
        "data": {"object": {"metadata": {
            "email": "wh@example.com", "edition": "expert",
            "term": "lifetime", "tier": "agency", "coupon": ""
        }}},
    }
    d = client.post("/webhook/stripe", json=event).get_json()
    assert d["success"] and d["issued"] and d["key"].startswith("SPR-")

    # The new license shows up in the account lookup as Expert / lifetime.
    r = client.post("/account", data={"q": "wh@example.com"}, follow_redirects=True)
    assert b"Expert" in r.data and b"lifetime" in r.data


def test_webhook_ignores_unrelated_events(client):
    d = client.post("/webhook/stripe", json={"type": "customer.created", "data": {"object": {}}}).get_json()
    assert d["success"] and d.get("ignored") == "customer.created"
