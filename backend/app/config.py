"""
Flask configuration — loaded from environment variables via .env
"""
import os
from dotenv import load_dotenv

load_dotenv()


class Config:
    SECRET_KEY        = os.environ["FLASK_SECRET_KEY"]
    SQLALCHEMY_DATABASE_URI     = os.environ["DATABASE_URL"]
    SQLALCHEMY_TRACK_MODIFICATIONS = False
    CORS_ORIGINS      = os.getenv("CORS_ORIGINS", "http://localhost:4321").split(",")

    # Simple admin credentials (replace with DB-backed auth when ready)
    ADMIN_USERNAME    = os.getenv("ADMIN_USERNAME", "admin")
    ADMIN_PASSWORD    = os.getenv("ADMIN_PASSWORD", "changeme")


class DevelopmentConfig(Config):
    DEBUG = True


class ProductionConfig(Config):
    DEBUG = False


config = {
    "development": DevelopmentConfig,
    "production":  ProductionConfig,
    "default":     DevelopmentConfig,
}
