# Gokul Bhavan Gaudiya Matha — Web Project

## Structure

```
gokulbhavan/
├── design/      Static HTML prototype (Tailwind) — stakeholder reviews
├── frontend/    Astro site — production frontend (deploys to IONOS)
├── backend/     Flask REST API + admin — production backend (deploys to IONOS)
└── README.md
```

## Local Development

### Prerequisites
- Node.js 20+
- Python 3.11+
- MariaDB (local install or Docker)

### Frontend (Astro — port 4321)
```bash
cd frontend
npm install
npm run dev
```
Connects to Flask API at `http://localhost:5000` by default.

### Backend (Flask — port 5000)
```bash
cd backend
python -m venv .venv
source .venv/bin/activate      # Windows: .venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env           # then edit .env with your DB credentials
flask --app wsgi:app db init   # first time only
flask --app wsgi:app db migrate -m "initial"
flask --app wsgi:app db upgrade
flask --app wsgi:app run --port 5000
```
Admin UI at `http://localhost:5000/admin`

### Design Prototype (port 3000)
```bash
cd design
npm run dev
```
Serves the static HTML prototype at `http://localhost:3000`.

## Deployment

| Layer    | Target                        |
|----------|-------------------------------|
| Frontend | IONOS Deploy Now (static)     |
| Backend  | IONOS Python Hosting (wsgi.py)|
| Database | MariaDB on IONOS              |
