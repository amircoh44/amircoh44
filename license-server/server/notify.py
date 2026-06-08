"""Email delivery + the admin digest of legitimate, reasonable requests.

Every message is recorded in the EmailMessage outbox so digests are auditable
even with no SMTP configured. If SMTP_HOST is set, it is also actually sent.
"""
import os
import smtplib
from datetime import datetime
from email.message import EmailMessage as MIMEMessage

from .models import db, Ticket, EmailMessage


def admin_email():
    return os.environ.get("ADMIN_EMAIL", "admin@localhost")


def send_email(to, subject, body):
    """Record (and, if SMTP is configured, send) an email. Returns the row."""
    row = EmailMessage(to=to, subject=subject, body=body, created_at=datetime.utcnow())
    host = os.environ.get("SMTP_HOST")
    if host:
        try:
            msg = MIMEMessage()
            msg["From"] = os.environ.get("SMTP_FROM", admin_email())
            msg["To"] = to
            msg["Subject"] = subject
            msg.set_content(body)
            port = int(os.environ.get("SMTP_PORT", "587"))
            with smtplib.SMTP(host, port, timeout=20) as s:
                if os.environ.get("SMTP_STARTTLS", "1") == "1":
                    s.starttls()
                user = os.environ.get("SMTP_USER")
                if user:
                    s.login(user, os.environ.get("SMTP_PASS", ""))
                s.send_message(msg)
            row.sent = True
        except Exception as exc:  # keep the outbox record with the error
            row.error = str(exc)[:255]
    db.session.add(row)
    db.session.commit()
    return row


def _line(t):
    due = t.sla_due_at.strftime("%a %d %b %H:%M UTC") if t.sla_due_at else "best-effort"
    flag = " [PRIORITY]" if t.priority else ""
    sec = " [SECURITY]" if t.category == "security_report" else ""
    return ("#%d  %s  (%s/%s)%s%s\n    from: %s%s\n    SLA due: %s\n    %s\n"
            % (t.id, t.subject or "(no subject)", t.tier, t.type, flag, sec,
               t.email or "anonymous",
               ("  key: " + t.license_key) if t.license_key else "",
               due, (t.message or "").strip().replace("\n", " ")[:280]))


def build_digest(tickets):
    """Return (subject, body) for a digest of the given tickets."""
    n = len(tickets)
    prio = sum(1 for t in tickets if t.priority)
    subject = "[SEO Sprinkler] %d new support request(s)%s" % (n, (" — %d priority" % prio) if prio else "")
    # Priority / SLA-bound first, then by SLA due, then newest.
    tickets = sorted(tickets, key=lambda t: (not t.priority, t.sla_due_at or datetime.max, -t.id))
    body = ["%d legitimate request(s) awaiting reply. Priority/SLA items first.\n" % n]
    for t in tickets:
        body.append(_line(t))
    body.append("\n— Quarantined (piracy/abuse/spam) and held items are NOT included; review them in the admin panel.")
    return subject, "\n".join(body)


def send_pending_digest():
    """Email the admin all queued, legitimate, not-yet-emailed tickets.

    Returns the number of tickets included (0 if none)."""
    pending = (Ticket.query
               .filter(Ticket.status == "queued", Ticket.emailed_at.is_(None))
               .order_by(Ticket.created_at.asc())
               .all())
    if not pending:
        return 0
    subject, body = build_digest(pending)
    send_email(admin_email(), subject, body)
    now = datetime.utcnow()
    for t in pending:
        t.emailed_at = now
        t.status = "open"          # acknowledged into the admin's queue
    db.session.commit()
    return len(pending)
