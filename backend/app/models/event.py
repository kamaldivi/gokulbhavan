from datetime import date, datetime
from .. import db


class CalendarEvent(db.Model):
    __tablename__ = "calendar_events"

    id          = db.Column(db.Integer,     primary_key=True)
    title       = db.Column(db.String(200), nullable=False)
    description = db.Column(db.Text,        nullable=False, default="")
    date        = db.Column(db.Date,        nullable=False)
    time        = db.Column(db.String(50),  nullable=False)   # "7:00 PM IST"
    location    = db.Column(db.String(200), nullable=False, default="Zoom")
    zoom_id     = db.Column(db.String(50),  nullable=True)
    site        = db.Column(db.String(50),  nullable=False, default="gokulbhavan")
    created_at  = db.Column(db.DateTime,    default=datetime.utcnow)

    def to_dict(self) -> dict:
        return {
            "id":          self.id,
            "title":       self.title,
            "description": self.description,
            "date":        self.date.isoformat(),
            "time":        self.time,
            "location":    self.location,
            "zoom_id":     self.zoom_id,
            "site":        self.site,
        }
