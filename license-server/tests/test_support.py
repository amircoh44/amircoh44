"""Support inbox: submission, tier+SLA tagging, quarantine, and the admin digest."""
from datetime import datetime

from server import licensing, notify
from server.models import db, Customer, License, Ticket, EmailMessage


def _expert(app, key="SPR-EXPT-0000-0000-0001", email="exp@example.com"):
    with app.app_context():
        c = Customer(email=email)
        db.session.add(c)
        db.session.flush()
        db.session.add(License(key=key, customer_id=c.id, edition="expert", term="annual",
                               site_limit=1, status="active", expires_at=datetime(2999, 1, 1)))
        db.session.commit()
    return key, email


def test_sla_weekend_vs_weekday():
    wed = datetime(2026, 6, 3, 10, 0)   # Wednesday
    sat = datetime(2026, 6, 6, 10, 0)   # Saturday
    assert (licensing.sla_due("expert", wed) - wed).total_seconds() == 12 * 3600
    assert (licensing.sla_due("expert", sat) - sat).total_seconds() == 24 * 3600
    assert (licensing.sla_due("pro", wed) - wed).total_seconds() == 48 * 3600
    assert licensing.sla_due("free", wed) is None


def test_anyone_can_report_a_bug(app, client):
    r = client.post("/support", data={"type": "bug", "subject": "UI bug",
                                      "message": "Button overlaps the title on mobile; repro on the home page."},
                    follow_redirects=True)
    assert r.status_code == 200 and b"Received" in r.data
    with app.app_context():
        t = Ticket.query.order_by(Ticket.id.desc()).first()
        assert t.type == "bug" and t.tier == "free" and t.sla_due_at is None


def test_support_requires_email(client):
    r = client.post("/support", data={"type": "support", "message": "please help with linking"},
                    follow_redirects=True)
    assert b"your email" in r.data        # re-rendered form with the error


def test_expert_request_is_priority_with_sla(app, client):
    key, email = _expert(app)
    r = client.post("/support", data={"type": "support", "email": email, "license_key": key,
                                      "subject": "Linking issue",
                                      "message": "Internal links are not applying after the latest update; steps included."},
                    follow_redirects=True)
    assert b"Received" in r.data
    with app.app_context():
        t = Ticket.query.order_by(Ticket.id.desc()).first()
        assert t.tier == "expert" and t.priority is True and t.sla_due_at is not None
        assert (t.sla_due_at - t.created_at).total_seconds() <= 24 * 3600 + 1


def test_piracy_is_flagged(app, client):
    client.post("/support", data={"type": "support", "email": "x@example.com", "subject": "key",
                                  "message": "please share a cracked license key to bypass activation"})
    with app.app_context():
        t = Ticket.query.order_by(Ticket.id.desc()).first()
        assert t.status == "flagged" and t.category == "abuse"


def test_digest_emails_only_legit(app, client):
    client.post("/support", data={"type": "bug",
                                  "message": "Schema output missing on archive pages; repro steps attached."})
    client.post("/support", data={"type": "support", "email": "a@b.com", "subject": "crack",
                                  "message": "please share a cracked license key to bypass activation"})
    with app.app_context():
        n = notify.send_pending_digest()
        assert n == 1                                   # only the legit bug
        msg = EmailMessage.query.order_by(EmailMessage.id.desc()).first()
        assert "request" in msg.subject.lower()
        flagged = Ticket.query.filter_by(status="flagged").first()
        assert flagged is not None and flagged.emailed_at is None  # piracy never emailed


def test_digest_cron_requires_token(client, monkeypatch):
    monkeypatch.delenv("DIGEST_TOKEN", raising=False)
    assert client.post("/admin/tickets/digest/cron").status_code == 403
    monkeypatch.setenv("DIGEST_TOKEN", "secret")
    r = client.post("/admin/tickets/digest/cron?token=secret")
    assert r.status_code == 200 and r.get_json()["success"] is True
