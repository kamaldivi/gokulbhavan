"""
youtube_snapshot.py
───────────────────
Fetches every video from every playlist listed in playlists.csv via the
YouTube Data API v3.  Applies the same ID-extraction and title-cleaning
logic used by youtubesync.php, then writes a single flat CSV snapshot.

No database connection required — completely standalone.

Setup
─────
    1. Fill in playlists.csv  (playlist_name, playlist_id)
       Playlist IDs look like "PLxxxxxxxxxxxxxxxxxxxxxx" — find them in
       the YouTube URL when you open a playlist on your channel.

    2. Fill in .env  (just YOUTUBE_API_KEY)

    3. Run:
           cd scripts/
           pip install -r requirements.txt
           python youtube_snapshot.py

Output: youtube_snapshot_YYYYMMDD_HHMMSS.csv  (in OUTPUT_DIR)
"""

import csv
import os
import re
import sys
import time
from datetime import datetime
from pathlib import Path

import requests
from dotenv import load_dotenv

# ── Load .env ──────────────────────────────────────────────────────────────
load_dotenv(Path(__file__).parent / ".env")

YT_API_KEY  = os.environ["YOUTUBE_API_KEY"]
OUTPUT_DIR  = Path(os.environ.get("OUTPUT_DIR", "."))
SCRIPTS_DIR = Path(__file__).parent

YT_PLAYLIST_URL = "https://www.googleapis.com/youtube/v3/playlistItems"

# Titles that indicate the video is unavailable on YouTube
SKIP_TITLES = {"Deleted video", "Private video", "Unlisted video"}


# ── Logic mirrored from youtubesync.php ───────────────────────────────────

def extract_id(title: str) -> str:
    """Pull the content tag from inside brackets, e.g. '[A-10]' → 'A-10'."""
    match = re.search(r"\[([^\]]+)\]", title)
    return match.group(1) if match else ""


def content_type(content_id: str) -> str:
    """
    Bhajan IDs have '-' at index 1  (e.g. 'A-10').
    Sloka  IDs have '-' at index 2  (e.g. 'BJ-05').
    Anything else → harikatha.
    """
    if not content_id:
        return "harikatha"
    dash = content_id.find("-")
    if dash == 1:
        return "bhajan"
    if dash == 2:
        return "sloka"
    return "harikatha"


def clean_title(title: str, content_id: str) -> str:
    """Remove quotes, asterisks, and strip the bracket tag."""
    title = title.replace('"', "").replace("'", "").replace("*", "")
    if content_id:
        bracket = title.find("[")
        if bracket != -1:
            title = title[:bracket]
    return title.strip()


# ── Playlist registry ──────────────────────────────────────────────────────

def get_playlists() -> list[dict]:
    """
    Read playlists.csv (playlist_name, playlist_id).
    Fill this file in manually — playlist IDs are in the YouTube URL
    when you open a playlist: youtube.com/playlist?list=<THIS_PART>
    """
    path = SCRIPTS_DIR / "playlists.csv"
    if not path.exists():
        sys.exit(f"playlists.csv not found at {path}")

    playlists = []
    with open(path, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            name = row.get("playlist_name", "").strip()
            pid  = row.get("playlist_id",   "").strip()
            if pid:                          # skip blank rows
                playlists.append({"play_list_name": name, "play_list_id": pid})

    if not playlists:
        sys.exit("playlists.csv is empty — add at least one playlist_id row.")

    return playlists


# ── YouTube fetch ──────────────────────────────────────────────────────────

def fetch_playlist_items(playlist_id: str) -> list[dict]:
    """
    Page through all items in a YouTube playlist.
    Returns raw snippet dicts from the API.
    """
    items = []
    page_token = None

    while True:
        params = {
            "part":       "snippet",
            "maxResults": 50,
            "playlistId": playlist_id,
            "key":        YT_API_KEY,
        }
        if page_token:
            params["pageToken"] = page_token

        resp = requests.get(YT_PLAYLIST_URL, params=params, timeout=20)
        resp.raise_for_status()
        data = resp.json()

        if "error" in data:
            raise RuntimeError(
                f"YouTube API error for playlist {playlist_id}: "
                f"{data['error'].get('message', data['error'])}"
            )

        items.extend(data.get("items", []))
        page_token = data.get("nextPageToken")
        if not page_token:
            break

        time.sleep(0.1)   # be polite to the API

    return items


# ── Main ───────────────────────────────────────────────────────────────────

CSV_FIELDS = [
    "playlist_name",
    "playlist_id",
    "position",          # 0-based position inside the playlist
    "video_id",
    "youtube_url",
    "raw_title",
    "clean_title",
    "content_id",        # e.g. "A-10", "BJ-05", or "" for harikatha
    "content_type",      # bhajan | sloka | harikatha
    "published_date",    # date video was added to the playlist (YYYY-MM-DD)
    "thumbnail_url",
    "status",            # "ok" | "skipped" (private/deleted/sun-pictures)
]


def main() -> None:
    print("Gokul Bhavan — YouTube Snapshot")
    print(f"Started: {datetime.now():%Y-%m-%d %H:%M:%S}\n")

    # ── Read playlist registry from playlists.csv ─────────────────
    print("Reading playlists.csv…")
    try:
        playlists = get_playlists()
    except SystemExit:
        raise
    except Exception as exc:
        sys.exit(f"Error reading playlists.csv: {exc}")

    print(f"Found {len(playlists)} playlists.\n")

    # ── Prepare output CSV ─────────────────────────────────────────
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    stamp    = datetime.now().strftime("%Y%m%d_%H%M%S")
    out_path = OUTPUT_DIR / f"youtube_snapshot_{stamp}.csv"

    total_ok      = 0
    total_skipped = 0

    with open(out_path, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=CSV_FIELDS)
        writer.writeheader()

        for pl in playlists:
            pl_name = pl["play_list_name"]
            pl_id   = pl["play_list_id"]
            print(f"  Playlist: {pl_name}  ({pl_id})")

            try:
                items = fetch_playlist_items(pl_id)
            except Exception as exc:
                print(f"    ⚠  Failed to fetch: {exc}")
                continue

            pl_ok = pl_skipped = 0

            for item in items:
                snippet   = item.get("snippet", {})
                raw_title = snippet.get("title", "")
                position  = snippet.get("position", "")
                video_id  = (snippet.get("resourceId") or {}).get("videoId", "")
                pub_date  = (snippet.get("publishedAt") or "")[:10]

                thumbs = snippet.get("thumbnails") or {}
                thumb  = (
                    (thumbs.get("medium") or {}).get("url")
                    or (thumbs.get("default") or {}).get("url")
                    or ""
                )

                # Detect skipped/unavailable videos
                is_skipped = (
                    raw_title in SKIP_TITLES
                    or "Sun Pictures" in raw_title
                    or not video_id
                )

                if is_skipped:
                    writer.writerow({
                        "playlist_name": pl_name,
                        "playlist_id":   pl_id,
                        "position":      position,
                        "video_id":      video_id,
                        "youtube_url":   f"https://youtube.com/watch?v={video_id}" if video_id else "",
                        "raw_title":     raw_title,
                        "clean_title":   "",
                        "content_id":    "",
                        "content_type":  "",
                        "published_date": pub_date,
                        "thumbnail_url": thumb,
                        "status":        "skipped",
                    })
                    pl_skipped += 1
                    continue

                cid  = extract_id(raw_title)
                ct   = content_type(cid)
                ctit = clean_title(raw_title, cid)

                writer.writerow({
                    "playlist_name":  pl_name,
                    "playlist_id":    pl_id,
                    "position":       position,
                    "video_id":       video_id,
                    "youtube_url":    f"https://youtube.com/watch?v={video_id}",
                    "raw_title":      raw_title,
                    "clean_title":    ctit,
                    "content_id":     cid,
                    "content_type":   ct,
                    "published_date": pub_date,
                    "thumbnail_url":  thumb,
                    "status":         "ok",
                })
                pl_ok += 1

            print(f"    {pl_ok} videos  |  {pl_skipped} skipped")
            total_ok      += pl_ok
            total_skipped += pl_skipped

    print(f"\nSnapshot complete.")
    print(f"  Total videos : {total_ok}")
    print(f"  Skipped      : {total_skipped}")
    print(f"  Output       : {out_path.resolve()}")
    print(f"Finished: {datetime.now():%Y-%m-%d %H:%M:%S}")


if __name__ == "__main__":
    main()
