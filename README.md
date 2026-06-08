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

```text
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
│       ├── pages/        Route pages (bhajans, albums, admin/, etc.)
│       └── styles/       global.css — shared Tailwind component classes
├── media/           Audio files and images — served from server, not in git
│   └── audio/
│       ├── bhajan/{category}/    Main bhajan MP3s
│       ├── sloka/{category}/     Main sloka MP3s
│       ├── sankirtan/{category}/ Main sankirtan MP3s
│       ├── album/{category}/     Album MP3s
│       ├── base/                 Base track (karaoke) MP3s
│       ├── versions/             Singer version MP3s
│       └── deleted/              Soft-deleted files (archived, not served)
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

```env
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
| --- | --- |
| `audio_category` | Content categories — bhajan, sankirtan, sloka, album. Has `audio_family` (bhajan/sloka/sankirtan) and `sort_order`. |
| `audio_track` | Primary tracks with `audio_file_path`, optional `base_track_path` and `lyrics_file_path` |
| `audio_singer_version` | Singer variant recordings per track (`track_id`, `singer`, `audio_file_path`) |
| `lyrics` | Lyrics and meanings (en + ta); sankirtans use sentinel `MAHAMANTRA` row |

### Video tables

| Table | Description |
| --- | --- |
| `video_category` | High-level groupings (Bhajans, Events, etc.) |
| `video_playlist` | YouTube playlists, linked to a category |
| `video` | Individual YouTube videos, deduplicated by video ID |
| `video_playlist_map` | Many-to-many: playlists ↔ videos |

### Other tables

| Table | Description |
| --- | --- |
| `program` | Weekly program schedules |
| `registration` | Devotee registrations from /register |
| `question` | Ask Guruji submissions |
| `announcement` | Site-wide announcements |
| `sanga` | Sanga (local group) directory |
| `global` | Site-wide config (YouTube API key, etc.) |

## MP3 File Naming Convention

All audio files follow a strict naming pattern. This is enforced by the admin upload UI.

| Type | Format | Example |
| --- | --- | --- |
| Main | `{track_id}-{track_name}.mp3` | `B-71-Tumi To Doyara Sindhu.mp3` |
| Base track | `{track_id}-{track_name} - Base.mp3` | `B-71-Tumi To Doyara Sindhu - Base.mp3` |
| Singer version | `{track_id}-{track_name} - {Singer Name}.mp3` | `B-71-Tumi To Doyara Sindhu - Gopika Devi.mp3` |

The admin upload panel auto-extracts the singer name from the filename (text after the last ` - `). All uploads are rejected if the filename does not begin with the correct track ID.

Deleted/replaced files are moved to `media/audio/deleted/` with a datetime suffix rather than permanently removed.

## Admin Panel

Available at `/admin` (session-protected). Covers all content management functions.

### Navigation

| Page | Path | Description |
| --- | --- | --- |
| Dashboard | `/admin/dashboard` | Metrics overview + quick links to all sections |
| Announcements | `/admin/announcements` | Site-wide banners with date ranges |
| Ask Guruji | `/admin/questions` | Review and respond to submitted questions |
| Audios | `/admin/audio` | Full audio content management (see below) |
| Programs | `/admin/programs` | Weekly program schedules |
| Registrations | `/admin/registrations` | Devotee registration records |
| Sanga Info | `/admin/sanga` | Local group locations and contacts |
| Videos | `/admin/video-library` | YouTube playlist and category management |
| YouTube Sync | (dashboard card) | Triggers live sync from YouTube API |

### Audio Management (`/admin/audio`)

Three-column resizable layout:

1. **Categories** (left) — add/edit/delete categories per audio family (Bhajan / Sloka / Sankirtan)
2. **Tracks** (centre) — track list for the selected category; Edit and Delete per row
3. **Editor** (right) — two-tab editor per track:
   - **Audio Files tab**: track ID, name, main MP3, optional base track, singer versions (each with auto-named singer extracted from filename). Delete buttons archive files to `media/audio/deleted/`.
   - **Lyrics tab**: per-language lyrics + meaning textareas; add/delete languages; lazy-loaded on first open.

### Shared Admin CSS Components (`frontend/src/styles/global.css`)

All admin pages use a shared component library rather than per-page inline styles:

| Class | Usage |
| --- | --- |
| `btn-new` | Pill-style "New / Add" action button |
| `btn-edit` | Subtle navy edit button |
| `btn-delete` | Red delete button |
| `btn-save` | Primary save/submit button |
| `btn-cancel` | Secondary cancel button |
| `admin-panel-header/body/footer` | Slide-in side panel sections |
| `admin-input` / `admin-label` | Form field styles |
| `status-badge-{green/blue/amber/red/slate}` | Status pill badges |

## Key Design Notes

- **Lyrics**: all stored in the `lyrics` table. Album tracks redirect to source bhajan lyrics via `audio_track.lyrics_source_track_id`. Sankirtans share a single sentinel row (`track_id = 'MAHAMANTRA'`).
- **Albums**: 10 Gokula Ganam volumes (GG1–GG10). Track IDs follow `GG1-01` format.
- **Media path migration**: run `GET /api/admin/migrate-audio-paths.php` (dry-run) then `?apply=1` to reorganise files from the legacy flat layout to the new `media/audio/{family}/{category}/` hierarchy and update all DB paths.
- **YouTube sync**: videos are synced via `api/youtubesync.php` (token-protected). Playlists seeded from `data/seed_video_categories.sql`.
