"""Seed reference data (plans, admin user, the marketing coupons) and a demo
license so the storefront's account page has something to show."""
from datetime import datetime, timedelta

from werkzeug.security import generate_password_hash

from .models import db, Plan, AdminUser, Coupon, Customer, License


def seed(app):
    if Plan.query.count() == 0:
        db.session.add_all([
            Plan(key="free", name="Free", edition="free", sort=0,
                 blurb="All audits + tools; everything free under 25 pages."),
            Plan(key="pro", name="Pro", edition="pro", sort=1,
                 blurb="Automation + AI for growing sites."),
            Plan(key="expert", name="Expert", edition="expert", sort=2,
                 blurb="Pro + export/migration + syndication."),
        ])

    if AdminUser.query.count() == 0:
        db.session.add(AdminUser(
            username=app.config["ADMIN_USER"],
            password_hash=generate_password_hash(app.config["ADMIN_PASS"]),
        ))

    if Coupon.query.count() == 0:
        db.session.add_all([
            Coupon(code="LAUNCH40", percent_off=40, note="Launch — first year"),
            Coupon(code="BFCM50", percent_off=50, note="Black Friday / Cyber Monday"),
            Coupon(code="GROW25", percent_off=25, note="Crossed 25 pages"),
            Coupon(code="WELCOME10", percent_off=10, note="Newsletter signup"),
            Coupon(code="COMEBACK15", percent_off=15, note="Cart abandonment"),
        ])

    db.session.commit()

    # A demo license (skip under tests) so /account shows a real lookup.
    if not app.config.get("TESTING") and Customer.query.count() == 0:
        cust = Customer(email="demo@example.com", name="Demo Customer")
        db.session.add(cust)
        db.session.flush()
        db.session.add(License(
            key="SPR-DEMO-2222-3333-4444",
            customer_id=cust.id,
            edition="pro",
            term="annual",
            site_limit=5,
            status="active",
            expires_at=datetime.utcnow() + timedelta(days=365),
            note="Seeded demo license",
        ))
        db.session.commit()
