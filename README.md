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
| `audio_author` | Optional author/composer metadata for audio tracks |
| `lyrics` | Lyrics and meanings (en + ta); sankirtans use sentinel `MAHAMANTRA` row |

### Sloka tables

| Table | Description |
| --- | --- |
| `sloka_category` | Tattva/topic categories (PK: `id`, UNIQUE: `category_code`, `category_name`) |
| `scripture` | Source texts — BG, SB, CC Adi/Madhya/Antya, Puranas, etc. (PK: `id`, UNIQUE: `name`; has `short_title`, `sort_order`) |
| `sloka` | Individual ślokas. FK → `sloka_category` and `scripture`. Columns: `title`, `search_title` (normalised for search), `sloka_text`, `scripture_ref`, `slokamrtam_ref`, `word_by_word`, `translation`, `commentary`, `audio_file_path` |

### Daily highlight tables

| Table | Description |
| --- | --- |
| `daily_highlight` | PK: `content_type`. One row per type (sloka/bhajan) holding the current day's highlight |
| `highlight_history` | UNIQUE(`content_type`, `shown_on`). History of past daily highlights |

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
| Slokas | `/admin/slokas` | Sloka library management — categories, scriptures, slokas, audio |
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

## Sloka Library (`/slokas`)

Public-facing Sanskrit śloka library at `/slokas` (`frontend/src/pages/slokas-new.astro`).

**Layout:** Resizable split-panel — sticky left list + right detail that expands the page height (no third scrollbar). Filter bar is sticky below the site header.

**Search & filter:** Full-text title search or scripture-ref search; Scripture and Tattva (category) dropdowns. Results shown as `Title (scripture_ref)` with count "N ślokas found".

**Detail panel:** Sloka title, Sanskrit text (lotus colour, centred), scripture ref, Meaning, Word-by-Word, Commentary. Copy Info and optional audio controls.

**Audio:** Play button docks to the bottom bar (`{ docked: true }`) — the modal never opens on this page, so the sloka text remains readable while audio plays.

**API endpoints:**

| Endpoint | Description |
| --- | --- |
| `GET /api/slokas.php` | List/search slokas. Params: `search`, `search_ref`, `category`, `scripture_id` |
| `GET /api/sloka-categories.php` | Tattva category list |
| `GET /api/scriptures.php` | Scripture list (includes `sloka_count`) |
| `GET/POST/PUT/DELETE /api/admin/slokas.php` | Admin CRUD for slokas |
| `GET/POST/PUT/DELETE /api/admin/sloka-categories.php` | Admin CRUD for sloka categories |
| `POST/DELETE /api/admin/sloka-audio.php` | Sloka audio file upload/removal |
| `GET/POST/PUT/DELETE /api/admin/scriptures.php` | Admin CRUD for scriptures |

## Key Design Notes

- **Lyrics**: all stored in the `lyrics` table. Album tracks redirect to source bhajan lyrics via `audio_track.lyrics_source_track_id`. Sankirtans share a single sentinel row (`track_id = 'MAHAMANTRA'`).
- **Albums**: 10 Gokula Ganam volumes (GG1–GG10). Track IDs follow `GG1-01` format.
- **Audio player docked mode**: `window.__audioPlayer.setQueue(tracks, idx, { docked: true })` starts playback in the bottom mini bar without opening the modal. Used on the slokas page so the śloka text remains readable while audio plays. All other pages use the default modal behaviour.
- **Navigation**: `frontend/src/lib/site-links.ts` is the single source of truth for all public nav links. Sub-nav components (`AboutNav.astro`, `BhajansNav.astro`) import from it.
- **Media path migration**: run `GET /api/admin/migrate-audio-paths.php` (dry-run) then `?apply=1` to reorganise files from the legacy flat layout to the new `media/audio/{family}/{category}/` hierarchy and update all DB paths.
- **YouTube sync**: `api/youtubesync.php` (token-protected). Runs a two-phase delete-and-rebuild on every execution:
  - **Phase 1** — clears `video` and `video_playlist_map` entirely.
  - **Phase 2** — iterates every playlist in `video_playlist`, fetches items from the YouTube Data API v3 (playlistItems.list, 50 per page), and inserts fresh rows. `published_date` (true upload date) is fetched via a secondary `videos.list` call per page.
  - Playlists that return 404 from YouTube are automatically deleted from `video_playlist`.
  - Videos with title "Deleted video", "Private video", or "Unlisted video" are skipped and not inserted.
  - Videos appearing in multiple playlists are inserted once into `video` (INSERT IGNORE); `video_playlist_map` records each playlist membership separately.
  - Uses `ignore_errors` stream context on all `file_get_contents` calls so HTTP 4xx responses return a parseable JSON body rather than `false`.
  - Brief downtime (no videos visible) during the sync window is accepted and expected.
  - Playlists are seeded via `data/seed_video_categories.sql`; new playlists must be added to `video_playlist` manually before they are picked up by the sync.
