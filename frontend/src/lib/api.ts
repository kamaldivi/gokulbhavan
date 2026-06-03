/**
 * Typed fetch helpers for the PHP API layer.
 * All functions are called client-side (browser fetch).
 *
 * Base URL is injected at build time:
 *   - local dev:  http://localhost:5000  (set by astro.config.mjs default)
 *   - production: https://gokulbhavan.org  (same domain — PHP lives under /api/)
 */

const BASE = import.meta.env.VITE_API_BASE_URL as string;

// ── Types ─────────────────────────────────────────────────────────────────

export interface CalendarEvent {
  id:          number;
  title:       string;
  description: string;
  date:        string;   // ISO 8601 — "2026-05-09"
  time:        string;   // "7:00 PM IST"
  location:    string;   // "Zoom" | venue name
  zoom_id?:    string;
  site:        string;   // "gokulbhavan" | "tamil-sangha"
}

export interface ProgramSchedule {
  id:          number;
  title:       string;
  description: string;
  day_of_week: string;   // "Friday"
  time_ist:    string;   // "8:00 PM"
  time_cst:    string;   // "9:30 AM"
  time_est:    string;   // "10:30 AM"
  duration_min: number;
  platform:    string;   // "Zoom"
  zoom_id?:    string;
  language:    string;   // "Tamil" | "English"
  site:        string;
  active:      boolean;
}

// ── Fetch helpers ─────────────────────────────────────────────────────────

async function get<T>(path: string): Promise<T> {
  const res = await fetch(`${BASE}${path}`);
  if (!res.ok) throw new Error(`API error ${res.status}: ${path}`);
  return res.json() as Promise<T>;
}

// ── Public API calls ──────────────────────────────────────────────────────

/** Upcoming calendar events, optionally filtered by site slug. */
export const getEvents = (site?: string): Promise<CalendarEvent[]> =>
  get(`/api/events${site ? `?site=${site}` : ""}`);

/** Active program schedules, optionally filtered by site slug. */
export const getPrograms = (site?: string): Promise<ProgramSchedule[]> =>
  get(`/api/programs${site ? `?site=${site}` : ""}`);
