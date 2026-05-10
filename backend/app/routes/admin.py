"""
Admin routes — login-protected forms for managing events and programs.
Access at /admin  (browser UI, Jinja2 templates)
"""
from flask import Blueprint, render_template, redirect, url_for, request, flash, session
from functools import wraps
from ..models import CalendarEvent, ProgramSchedule
from .. import db
import os

admin_bp = Blueprint("admin", __name__, url_prefix="/admin")


# ── Simple session-based auth ─────────────────────────────────────────────

def login_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if not session.get("admin_logged_in"):
            return redirect(url_for("admin.login"))
        return f(*args, **kwargs)
    return decorated


@admin_bp.get("/login")
def login():
    return render_template("admin/login.html")


@admin_bp.post("/login")
def login_post():
    from flask import current_app
    username = request.form.get("username", "")
    password = request.form.get("password", "")
    if (username == current_app.config["ADMIN_USERNAME"] and
            password == current_app.config["ADMIN_PASSWORD"]):
        session["admin_logged_in"] = True
        return redirect(url_for("admin.dashboard"))
    flash("Invalid credentials.")
    return redirect(url_for("admin.login"))


@admin_bp.get("/logout")
def logout():
    session.clear()
    return redirect(url_for("admin.login"))


# ── Dashboard ─────────────────────────────────────────────────────────────

@admin_bp.get("/")
@login_required
def dashboard():
    events   = CalendarEvent.query.order_by(CalendarEvent.date.desc()).limit(10).all()
    programs = ProgramSchedule.query.order_by(ProgramSchedule.title).all()
    return render_template("admin/dashboard.html", events=events, programs=programs)


# ── Calendar Events ───────────────────────────────────────────────────────

@admin_bp.get("/events/new")
@login_required
def new_event():
    return render_template("admin/event_form.html", event=None)


@admin_bp.post("/events/new")
@login_required
def create_event():
    from datetime import date as date_type
    event = CalendarEvent(
        title=request.form["title"],
        description=request.form.get("description", ""),
        date=date_type.fromisoformat(request.form["date"]),
        time=request.form["time"],
        location=request.form.get("location", "Zoom"),
        zoom_id=request.form.get("zoom_id") or None,
        site=request.form.get("site", "gokulbhavan"),
    )
    db.session.add(event)
    db.session.commit()
    flash("Event created.")
    return redirect(url_for("admin.dashboard"))


@admin_bp.get("/events/<int:id>/edit")
@login_required
def edit_event(id: int):
    event = CalendarEvent.query.get_or_404(id)
    return render_template("admin/event_form.html", event=event)


@admin_bp.post("/events/<int:id>/edit")
@login_required
def update_event(id: int):
    from datetime import date as date_type
    event = CalendarEvent.query.get_or_404(id)
    event.title       = request.form["title"]
    event.description = request.form.get("description", "")
    event.date        = date_type.fromisoformat(request.form["date"])
    event.time        = request.form["time"]
    event.location    = request.form.get("location", "Zoom")
    event.zoom_id     = request.form.get("zoom_id") or None
    event.site        = request.form.get("site", "gokulbhavan")
    db.session.commit()
    flash("Event updated.")
    return redirect(url_for("admin.dashboard"))


@admin_bp.post("/events/<int:id>/delete")
@login_required
def delete_event(id: int):
    event = CalendarEvent.query.get_or_404(id)
    db.session.delete(event)
    db.session.commit()
    flash("Event deleted.")
    return redirect(url_for("admin.dashboard"))


# ── Program Schedules ─────────────────────────────────────────────────────

@admin_bp.get("/programs/new")
@login_required
def new_program():
    return render_template("admin/program_form.html", program=None)


@admin_bp.post("/programs/new")
@login_required
def create_program():
    program = ProgramSchedule(
        title=request.form["title"],
        description=request.form.get("description", ""),
        day_of_week=request.form["day_of_week"],
        time_ist=request.form["time_ist"],
        time_cst=request.form["time_cst"],
        time_est=request.form["time_est"],
        duration_min=int(request.form.get("duration_min", 95)),
        platform=request.form.get("platform", "Zoom"),
        zoom_id=request.form.get("zoom_id") or None,
        language=request.form.get("language", "English"),
        site=request.form.get("site", "gokulbhavan"),
        active=bool(request.form.get("active")),
    )
    db.session.add(program)
    db.session.commit()
    flash("Program created.")
    return redirect(url_for("admin.dashboard"))
