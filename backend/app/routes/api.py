"""
Public REST API — consumed by the Astro frontend (client-side fetch).

Endpoints:
  GET /api/events?site=gokulbhavan
  GET /api/programs?site=gokulbhavan
"""
from datetime import date
from flask import Blueprint, jsonify, request
from ..models import CalendarEvent, ProgramSchedule

api_bp = Blueprint("api", __name__, url_prefix="/api")


@api_bp.get("/events")
def list_events():
    site = request.args.get("site")
    query = CalendarEvent.query.filter(CalendarEvent.date >= date.today())
    if site:
        query = query.filter_by(site=site)
    events = query.order_by(CalendarEvent.date).all()
    return jsonify([e.to_dict() for e in events])


@api_bp.get("/programs")
def list_programs():
    site = request.args.get("site")
    query = ProgramSchedule.query.filter_by(active=True)
    if site:
        query = query.filter_by(site=site)
    programs = query.order_by(ProgramSchedule.title).all()
    return jsonify([p.to_dict() for p in programs])
