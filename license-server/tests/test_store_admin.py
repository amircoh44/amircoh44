"""Smoke tests for the storefront pages and the admin panel flow."""


def test_storefront_pages_render(client):
    for path in ["/", "/features", "/pricing", "/faq", "/account", "/download", "/contact"]:
        assert client.get(path).status_code == 200, path


def test_pricing_shows_aggressive_numbers(client):
    body = client.get("/pricing").get_data(as_text=True)
    assert "$39" in body and "$69" in body          # Pro / Expert annual
    assert "LAUNCH40" in body
    assert "24-hour response" in body                # Expert support SLA
    assert "Email support" in body                   # Pro support


def test_sandbox_checkout_issues_license(client):
    r = client.post("/checkout?edition=pro&term=annual&tier=1",
                    data={"edition": "pro", "term": "annual", "tier": "1",
                          "coupon": "LAUNCH40", "email": "buyer@example.com",
                          "place_order": "1"},
                    follow_redirects=True)
    assert r.status_code == 200
    assert b"all set" in r.data and b"SPR-" in r.data


def test_admin_requires_login(client):
    r = client.get("/admin/")
    assert r.status_code in (301, 302)
    assert "/admin/login" in r.headers["Location"]


def test_admin_login_and_issue_license(client):
    r = client.post("/admin/login", data={"username": "admin", "password": "changeme"},
                    follow_redirects=True)
    assert b"Dashboard" in r.data

    r = client.post("/admin/licenses/new",
                    data={"email": "agency@example.com", "edition": "expert",
                          "term": "lifetime", "tier": "agency"},
                    follow_redirects=True)
    assert b"SPR-" in r.data and b"Expert" in r.data
