"""Database models for customers, licenses, activations and coupons."""
from datetime import datetime

from flask_sqlalchemy import SQLAlchemy

db = SQLAlchemy()


class Plan(db.Model):
    """A purchasable tier (free / pro / expert). Prices live in licensing.PRICES."""
    __tablename__ = "plans"
    id = db.Column(db.Integer, primary_key=True)
    key = db.Column(db.String(20), unique=True, nullable=False)   # free|pro|expert
    name = db.Column(db.String(40), nullable=False)
    edition = db.Column(db.String(20), nullable=False)            # free|pro|expert
    blurb = db.Column(db.String(255), default="")
    sort = db.Column(db.Integer, default=0)


class Customer(db.Model):
    __tablename__ = "customers"
    id = db.Column(db.Integer, primary_key=True)
    email = db.Column(db.String(190), unique=True, nullable=False, index=True)
    name = db.Column(db.String(120), default="")
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    licenses = db.relationship("License", backref="customer", cascade="all, delete-orphan")


class License(db.Model):
    __tablename__ = "licenses"
    id = db.Column(db.Integer, primary_key=True)
    key = db.Column(db.String(40), unique=True, nullable=False, index=True)
    customer_id = db.Column(db.Integer, db.ForeignKey("customers.id"), nullable=False)
    edition = db.Column(db.String(20), nullable=False)            # pro|expert
    term = db.Column(db.String(20), default="annual")             # annual|lifetime
    site_limit = db.Column(db.Integer, default=1)                 # 0 = unlimited
    status = db.Column(db.String(20), default="active")           # active|revoked
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    expires_at = db.Column(db.DateTime, nullable=True)            # None = lifetime
    note = db.Column(db.String(255), default="")

    activations = db.relationship("Activation", backref="license", cascade="all, delete-orphan")


class Activation(db.Model):
    __tablename__ = "activations"
    id = db.Column(db.Integer, primary_key=True)
    license_id = db.Column(db.Integer, db.ForeignKey("licenses.id"), nullable=False)
    site_url = db.Column(db.String(255), nullable=False)
    site_name = db.Column(db.String(190), default="")
    activated_at = db.Column(db.DateTime, default=datetime.utcnow)
    last_seen_at = db.Column(db.DateTime, default=datetime.utcnow)

    __table_args__ = (db.UniqueConstraint("license_id", "site_url", name="uq_license_site"),)


class Coupon(db.Model):
    __tablename__ = "coupons"
    id = db.Column(db.Integer, primary_key=True)
    code = db.Column(db.String(40), unique=True, nullable=False, index=True)
    percent_off = db.Column(db.Integer, nullable=False)
    max_uses = db.Column(db.Integer, nullable=True)               # None = unlimited
    used_count = db.Column(db.Integer, default=0)
    expires_at = db.Column(db.DateTime, nullable=True)
    active = db.Column(db.Boolean, default=True)
    note = db.Column(db.String(255), default="")


class AdminUser(db.Model):
    __tablename__ = "admin_users"
    id = db.Column(db.Integer, primary_key=True)
    username = db.Column(db.String(80), unique=True, nullable=False)
    password_hash = db.Column(db.String(255), nullable=False)


class Ticket(db.Model):
    """A support request or bug report, tier-tagged with an SLA clock + triage."""
    __tablename__ = "tickets"
    id = db.Column(db.Integer, primary_key=True)
    type = db.Column(db.String(10), default="support")        # support|bug
    email = db.Column(db.String(190), default="", index=True)
    license_id = db.Column(db.Integer, db.ForeignKey("licenses.id"), nullable=True)
    license_key = db.Column(db.String(40), default="")
    tier = db.Column(db.String(10), default="free")           # free|pro|expert
    subject = db.Column(db.String(200), default="")
    message = db.Column(db.Text, default="")
    url = db.Column(db.String(255), default="")               # page a bug was seen on
    created_at = db.Column(db.DateTime, default=datetime.utcnow, index=True)
    sla_due_at = db.Column(db.DateTime, nullable=True)
    priority = db.Column(db.Boolean, default=False)
    status = db.Column(db.String(12), default="queued")       # queued|flagged|held|open|closed
    category = db.Column(db.String(20), default="legit")      # legit|security_report|spam|abuse|low_quality
    risk_score = db.Column(db.Integer, default=0)
    reasons = db.Column(db.String(400), default="")
    emailed_at = db.Column(db.DateTime, nullable=True)

    license = db.relationship("License")


class EmailMessage(db.Model):
    """Outbox so digests are auditable even without SMTP configured."""
    __tablename__ = "email_outbox"
    id = db.Column(db.Integer, primary_key=True)
    to = db.Column(db.String(190), nullable=False)
    subject = db.Column(db.String(255), nullable=False)
    body = db.Column(db.Text, default="")
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    sent = db.Column(db.Boolean, default=False)
    error = db.Column(db.String(255), default="")
