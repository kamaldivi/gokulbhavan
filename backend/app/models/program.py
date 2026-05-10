from datetime import datetime
from .. import db


class ProgramSchedule(db.Model):
    __tablename__ = "program_schedules"

    id           = db.Column(db.Integer,     primary_key=True)
    title        = db.Column(db.String(200), nullable=False)
    description  = db.Column(db.Text,        nullable=False, default="")
    day_of_week  = db.Column(db.String(20),  nullable=False)   # "Friday"
    time_ist     = db.Column(db.String(20),  nullable=False)   # "8:00 PM"
    time_cst     = db.Column(db.String(20),  nullable=False)   # "9:30 AM"
    time_est     = db.Column(db.String(20),  nullable=False)   # "10:30 AM"
    duration_min = db.Column(db.Integer,     nullable=False, default=95)
    platform     = db.Column(db.String(50),  nullable=False, default="Zoom")
    zoom_id      = db.Column(db.String(50),  nullable=True)
    language     = db.Column(db.String(50),  nullable=False, default="English")
    site         = db.Column(db.String(50),  nullable=False, default="gokulbhavan")
    active       = db.Column(db.Boolean,     nullable=False, default=True)
    created_at   = db.Column(db.DateTime,    default=datetime.utcnow)

    def to_dict(self) -> dict:
        return {
            "id":           self.id,
            "title":        self.title,
            "description":  self.description,
            "day_of_week":  self.day_of_week,
            "time_ist":     self.time_ist,
            "time_cst":     self.time_cst,
            "time_est":     self.time_est,
            "duration_min": self.duration_min,
            "platform":     self.platform,
            "zoom_id":      self.zoom_id,
            "language":     self.language,
            "site":         self.site,
            "active":       self.active,
        }
