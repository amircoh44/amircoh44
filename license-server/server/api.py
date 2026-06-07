"""License REST API consumed by the WordPress plugin.

POST /api/v1/activate    {key, site_url, site_name?}  -> takes/refreshes a seat
POST /api/v1/validate    {key, site_url?}             -> reports status (no seat change)
POST /api/v1/deactivate  {key, site_url}              -> frees a seat
GET  /api/v1/ping
"""
from datetime import datetime

from flask import Blueprint, request, jsonify

from .models import db, License, Activation
from . import licensing

bp = Blueprint("api", __name__)


def _payload():
    return request.get_json(silent=True) or request.form


def _state(lic, site_url=None):
    active = licensing.is_license_active(lic)
    return {
        "edition": lic.edition if active else "free",
        "active": active,
        "plan": lic.edition,
        "term": lic.term,
        "lifetime": lic.expires_at is None,
        "site_limit": lic.site_limit,
        "sites_used": licensing.seats_used(lic),
        "expires_at": (lic.expires_at.isoformat() + "Z") if lic.expires_at else None,
        "status": lic.status,
        "site_activated": licensing.site_is_activated(lic, site_url) if site_url else None,
    }


@bp.get("/ping")
def ping():
    return jsonify(success=True, service="seo-sprinkler-license", version="1.0")


@bp.post("/activate")
def activate():
    data = _payload()
    key = (data.get("key") or "").strip()
    site_url = (data.get("site_url") or "").strip()
    site_name = (data.get("site_name") or "").strip()
    if not key or not site_url:
        return jsonify(success=False, edition="free", message="key and site_url are required"), 400

    lic = License.query.filter_by(key=key).first()
    if not lic:
        return jsonify(success=False, edition="free", message="Unknown license key"), 404
    if not licensing.is_license_active(lic):
        return jsonify(success=False, message="License is expired or revoked", **_state(lic, site_url)), 200
    if not licensing.can_activate(lic, site_url):
        return jsonify(success=False, message="Activation limit reached for this license", **_state(lic, site_url)), 200

    now = datetime.utcnow()
    act = Activation.query.filter_by(license_id=lic.id, site_url=site_url).first()
    if act:
        act.last_seen_at = now
        if site_name:
            act.site_name = site_name
    else:
        db.session.add(Activation(license_id=lic.id, site_url=site_url, site_name=site_name,
                                  activated_at=now, last_seen_at=now))
    db.session.commit()
    return jsonify(success=True, message="Activated", **_state(lic, site_url)), 200


@bp.post("/validate")
def validate():
    data = _payload()
    key = (data.get("key") or "").strip()
    site_url = (data.get("site_url") or "").strip()
    if not key:
        return jsonify(success=False, edition="free", message="key is required"), 400

    lic = License.query.filter_by(key=key).first()
    if not lic:
        return jsonify(success=False, edition="free", message="Unknown license key"), 404

    if site_url:
        act = Activation.query.filter_by(license_id=lic.id, site_url=site_url).first()
        if act:
            act.last_seen_at = datetime.utcnow()
            db.session.commit()

    active = licensing.is_license_active(lic)
    return jsonify(success=active, message="OK" if active else "Inactive", **_state(lic, site_url)), 200


@bp.post("/deactivate")
def deactivate():
    data = _payload()
    key = (data.get("key") or "").strip()
    site_url = (data.get("site_url") or "").strip()
    if not key or not site_url:
        return jsonify(success=False, message="key and site_url are required"), 400

    lic = License.query.filter_by(key=key).first()
    if not lic:
        return jsonify(success=False, message="Unknown license key"), 404

    act = Activation.query.filter_by(license_id=lic.id, site_url=site_url).first()
    if act:
        db.session.delete(act)
        db.session.commit()
    return jsonify(success=True, message="Deactivated", sites_used=licensing.seats_used(lic)), 200
