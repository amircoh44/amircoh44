"""Application factory for the SEO Sprinkler license server."""
from datetime import datetime

from flask import Flask

from .config import Config
from .models import db


def create_app(config=Config):
    app = Flask(__name__)
    app.config.from_object(config)

    db.init_app(app)

    from .store import bp as store_bp
    from .admin import bp as admin_bp
    from .api import bp as api_bp
    from .webhooks import bp as webhooks_bp

    app.register_blueprint(store_bp)
    app.register_blueprint(admin_bp, url_prefix="/admin")
    app.register_blueprint(api_bp, url_prefix="/api/v1")
    app.register_blueprint(webhooks_bp, url_prefix="/webhook")

    from . import licensing

    @app.context_processor
    def _globals():
        return {
            "BRAND": "SEO Sprinkler",
            "PRICES": licensing.PRICES,
            "SITE_TIERS": licensing.SITE_TIERS,
            "SUPPORT": licensing.SUPPORT,
            "now": datetime.utcnow(),
        }

    with app.app_context():
        db.create_all()
        from .seed import seed
        seed(app)

    return app
