"""Entrypoint for the SEO Sprinkler license server + storefront.

    python run.py            # http://127.0.0.1:5001
    PORT=8080 python run.py
"""
import os

from server import create_app

app = create_app()

if __name__ == "__main__":
    app.run(
        host=os.environ.get("HOST", "127.0.0.1"),
        port=int(os.environ.get("PORT", "5001")),
        debug=bool(os.environ.get("DEBUG", "1") == "1"),
    )
