# Gokul Bhavan Gaudiya Matha — Web Project

Website for [gokulbhavan.org](https://gokulbhavan.org) — a devotional site featuring audio (bhajans, slokas, sankirtans, albums), videos, programs, and community resources.

## Stack

| Layer    | Technology                        |
|----------|-----------------------------------|
| Frontend | Astro + Tailwind CSS (static)     |
| API      | PHP 8 REST endpoints              |
| Database | MariaDB on IONOS shared hosting   |
| Hosting  | IONOS (frontend + API + DB)       |

## Repository Structure

```
gokulbhavan/
├── api/             PHP REST API — public endpoints
│   └── admin/       Admin-only API endpoints (session-protected)
├── data/            Database schema and seed files
├── frontend/        Astro site — production frontend
│   ├── public/      Static assets
│   └── src/
│       ├── components/   Shared Astro components (AudioPlayer, Header, etc.)
│       ├── layouts/      Base and AdminShell layouts
│       ├── lib/          TypeScript utilities
│       └── pages/        Route pages (bhajans, albums, admin/, etc.)
├── media/           Audio files, lyrics, images — served from server, not in git
├── scripts/         Utility scripts (YouTube snapshot tool — paused WIP)
├── deploy.sh        SFTP deployment script
└── dev.sh           Local dev startup script
```

## Local Development

### Prerequisites

- Node.js 20+
- PHP 8.1+

### Frontend (Astro — port 4321)

```bash
cd frontend
npm install
npm run dev
```

Set `PUBLIC_API_BASE` in `frontend/.env` to point at the API:

```
PUBLIC_API_BASE=https://gokulbhavan.org
```

### PHP API

No build step. Point a local web server (Laravel Herd, MAMP, or `php -S`) at the project root so `/api/*` routes to the `api/` directory. Copy `api/config.php` from the server (not in git — contains DB credentials).

## Deployment

```bash
./deploy.sh
```

Deploys via SFTP to IONOS. Credentials stored in `.deploy.env` (not in git).

## Database

Schema: [`data/schema.sql`](data/schema.sql)

### Audio tables

| Table | Description |
|---|---|
| `audio_category` | Content categories — bhajan, sankirtan, sloka, album |
| `audio_track` | Primary tracks; albums carry `lyrics_source_track_id` pointing to source bhajan |
| `audio_singer_version` | Singer variant recordings per track |
| `lyrics` | Lyrics and meanings (en + ta); sankirtans use sentinel `MAHAMANTRA` row |

### Video tables

| Table | Description |
|---|---|
| `video_category` | High-level groupings (Bhajans, Events, etc.) |
| `video_playlist` | YouTube playlists, linked to a category |
| `video` | Individual YouTube videos, deduplicated by video ID |
| `video_playlist_map` | Many-to-many: playlists ↔ videos |

### Other tables

| Table | Description |
|---|---|
| `program` | Weekly program schedules |
| `registration` | Devotee registrations from /register |
| `question` | Ask Guruji submissions |
| `announcement` | Site-wide announcements |
| `sanga` | Sanga (local group) directory |
| `global` | Site-wide config (YouTube API key, etc.) |

## Key Design Notes

- **Lyrics**: all stored in the `lyrics` table. Album tracks redirect to source bhajan lyrics via `audio_track.lyrics_source_track_id`. Sankirtans share a single sentinel row (`track_id = 'MAHAMANTRA'`).
- **Albums**: 10 Gokula Ganam volumes (GG1–GG10) in `media/albums/`. Track IDs follow `GG1-01` format; `reload-albums.php` re-seeds from filesystem.
- **Admin panel**: available at `/admin` (session-protected). Covers programs, video library, lyrics editor, and registrations.
- **YouTube sync**: videos are synced via `api/youtubesync.php` (token-protected). Playlists seeded from `data/seed_video_categories.sql`.
