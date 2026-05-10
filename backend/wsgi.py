"""
WSGI entry point — used by IONOS mod_wsgi in production.
For local development, use:  flask --app wsgi:app run
"""
from app import create_app

app = create_app()

if __name__ == "__main__":
    app.run()
