"""Production WSGI entrypoint:  gunicorn wsgi:app"""
from server import create_app

app = create_app()
