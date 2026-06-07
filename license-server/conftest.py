"""Pytest fixtures: a fresh file-backed SQLite app per test."""
import pytest

from server import create_app
from server.config import TestConfig


@pytest.fixture
def app(tmp_path):
    class Cfg(TestConfig):
        SQLALCHEMY_DATABASE_URI = "sqlite:///" + str(tmp_path / "test.db")
    return create_app(Cfg)


@pytest.fixture
def client(app):
    return app.test_client()
