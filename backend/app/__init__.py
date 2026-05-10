"""
Flask application factory.
"""
import os
from flask import Flask
from flask_sqlalchemy import SQLAlchemy
from flask_migrate import Migrate
from flask_cors import CORS
from flask_login import LoginManager

from .config import config

db       = SQLAlchemy()
migrate  = Migrate()
login_mgr = LoginManager()


def create_app(env: str | None = None) -> Flask:
    env = env or os.getenv("FLASK_ENV", "development")
    app = Flask(__name__, template_folder="templates")
    app.config.from_object(config[env])

    # Extensions
    db.init_app(app)
    migrate.init_app(app, db)
    CORS(app, origins=app.config["CORS_ORIGINS"])
    login_mgr.init_app(app)
    login_mgr.login_view = "admin.login"

    # Import models so Flask-Migrate sees them
    with app.app_context():
        from .models import CalendarEvent, ProgramSchedule  # noqa: F401

    # Blueprints
    from .routes.api   import api_bp
    from .routes.admin import admin_bp
    app.register_blueprint(api_bp)
    app.register_blueprint(admin_bp)

    return app
