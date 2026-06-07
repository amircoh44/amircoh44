"""Stripe webhook: create a license when a checkout completes.

In production set STRIPE_WEBHOOK_SECRET so signatures are verified. In dev /
tests (no secret, or TESTING), the JSON event is accepted directly so the flow
can be exercised without Stripe.
"""
import os

from flask import Blueprint, request, jsonify, current_app

from . import payments

bp = Blueprint("webhooks", __name__)


@bp.post("/stripe")
def stripe_webhook():
    payload = request.get_data()
    secret = os.environ.get("STRIPE_WEBHOOK_SECRET")

    if secret and payments.stripe is not None and not current_app.config.get("TESTING"):
        sig = request.headers.get("Stripe-Signature", "")
        try:
            event = dict(payments.stripe.Webhook.construct_event(payload, sig, secret))
        except Exception as exc:  # signature/parse failure
            return jsonify(success=False, error=str(exc)), 400
    else:
        event = request.get_json(silent=True) or {}

    if event.get("type") == "checkout.session.completed":
        meta = (event.get("data", {}).get("object", {}) or {}).get("metadata") or {}
        lic = payments.issue_from_metadata(meta)
        return jsonify(success=True, issued=bool(lic), key=(lic.key if lic else None)), 200

    return jsonify(success=True, ignored=event.get("type")), 200
