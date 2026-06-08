/**
 * playlist-store.ts
 * ─────────────────
 * localStorage-backed playlist manager.
 * No auth, no server — works offline and persists across sessions.
 *
 * Public API
 * ──────────
 *   getPlaylists()                → PlaylistStore
 *   createPlaylist(name)          → Playlist
 *   deletePlaylist(id)            → void
 *   renamePlaylist(id, name)      → void
 *   addTrack(playlistId, track)   → void   (no-op if already present)
 *   removeTrack(playlistId, trackId) → void
 *   isInAnyPlaylist(trackId)      → boolean
 *   isInPlaylist(playlistId, trackId) → boolean
 *   subscribe(listener)           → () => void   (call returned fn to unsub)
 */

const STORAGE_KEY = 'gb_playlists';

// ── Types ─────────────────────────────────────────────────────────────────

/** Matches the track shape AudioPlayer already expects — zero conversion needed. */
export interface PlaylistTrack {
  track_id:          string;
  track_name:        string;
  display_name?:     string;
  audio_file_path:   string;
  lyrics_file_path?: string;
  download_allowed:  number;
  /** 'B' = bhajan · 'S' = sloka · 'N' = sankirtan · 'A' = album track */
  type:              'B' | 'S' | 'N' | 'A';
  added:             string;   // ISO timestamp
}

export interface Playlist {
  id:      string;
  name:    string;
  created: string;
  tracks:  PlaylistTrack[];
}

export type PlaylistStore = Playlist[];

// ── Path rewrite: old media layout → new media/audio/ layout (June 2026) ────
// Maps legacy folder names to the restructured hierarchy.
const PATH_REWRITES: [RegExp, string][] = [
  [/^media\/audio-bhajans\/([^/]+)\//, 'media/audio/bhajan/$1/'],
  [/^media\/slokas-audio\/([^/]+)\//,  'media/audio/sloka/$1/'],
  [/^media\/audio-sankirtans\/([^/]+)\//, 'media/audio/sankirtan/$1/'],
  [/^media\/albums\/([^/]+)\//,        'media/audio/album/$1/'],
  [/^media\/gokula-ganam\/vol(\d+)\/(?:ready\/)?/, 'media/audio/album/GG$1/'],
  [/^media\/base-tracks\//,            'media/audio/base/'],
  [/^media\/audio-bhajans-samples-others\//, 'media/audio/versions/'],
  [/^media\/audio-bhajans-samples\//,  'media/audio/versions/'],
];

function rewritePath(p: string): string {
  for (const [pattern, replacement] of PATH_REWRITES) {
    if (pattern.test(p)) return p.replace(pattern, replacement);
  }
  return p;
}

// ── Migration: v1 → v2 field rename ──────────────────────────────────────
// Renames media_id→track_id, title→track_name, audio_path→audio_file_path,
// lyrics_path→lyrics_file_path in any playlists stored before Phase 1.
// Also rewrites legacy audio_file_path values to the new media/audio/ layout.

function migrateStore(): void {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return;
    const store = JSON.parse(raw) as any[];
    let dirty = false;
    for (const pl of store) {
      if (!Array.isArray(pl.tracks)) continue;
      for (const t of pl.tracks) {
        if ('media_id' in t && !('track_id' in t)) {
          t.track_id = t.media_id; delete t.media_id; dirty = true;
        }
        if ('title' in t && !('track_name' in t)) {
          t.track_name = t.title; delete t.title; dirty = true;
        }
        if ('audio_path' in t && !('audio_file_path' in t)) {
          t.audio_file_path = t.audio_path; delete t.audio_path; dirty = true;
        }
        if ('lyrics_path' in t && !('lyrics_file_path' in t)) {
          t.lyrics_file_path = t.lyrics_path; delete t.lyrics_path; dirty = true;
        }
        // Rewrite legacy audio_file_path to new media/audio/ hierarchy
        if (t.audio_file_path) {
          const rewritten = rewritePath(t.audio_file_path);
          if (rewritten !== t.audio_file_path) {
            t.audio_file_path = rewritten; dirty = true;
          }
        }
      }
    }
    if (dirty) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(store));
    }
  } catch {
    // ignore migration errors
  }
}

// Run migration once on module load (handles existing saved playlists)
migrateStore();

// ── Internal helpers ──────────────────────────────────────────────────────

function load(): PlaylistStore {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as PlaylistStore) : [];
  } catch {
    return [];
  }
}

function save(store: PlaylistStore): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(store));
  } catch {
    // storage quota exceeded — fail silently
  }
  // Notify all listeners
  listeners.forEach(fn => fn(store));
}

function uid(): string {
  return 'pl_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
}

// ── Subscribers ───────────────────────────────────────────────────────────

type Listener = (store: PlaylistStore) => void;
const listeners = new Set<Listener>();

export function subscribe(listener: Listener): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

// ── Read ──────────────────────────────────────────────────────────────────

export function getPlaylists(): PlaylistStore {
  return load();
}

export function isInPlaylist(playlistId: string, trackId: string): boolean {
  const store = load();
  const pl = store.find(p => p.id === playlistId);
  return pl ? pl.tracks.some(t => t.track_id === trackId) : false;
}

export function isInAnyPlaylist(trackId: string): boolean {
  return load().some(pl => pl.tracks.some(t => t.track_id === trackId));
}

export function totalTrackCount(): number {
  return load().reduce((sum, pl) => sum + pl.tracks.length, 0);
}

// ── Write ─────────────────────────────────────────────────────────────────

export function createPlaylist(name: string): Playlist {
  const store = load();
  const pl: Playlist = {
    id:      uid(),
    name:    name.trim() || 'My Playlist',
    created: new Date().toISOString().slice(0, 10),
    tracks:  [],
  };
  store.push(pl);
  save(store);
  return pl;
}

export function deletePlaylist(id: string): void {
  save(load().filter(p => p.id !== id));
}

export function renamePlaylist(id: string, name: string): void {
  const store = load();
  const pl = store.find(p => p.id === id);
  if (pl) { pl.name = name.trim() || pl.name; save(store); }
}

export function addTrack(playlistId: string, track: Omit<PlaylistTrack, 'added'>): void {
  const store = load();
  const pl = store.find(p => p.id === playlistId);
  if (!pl) return;
  // Prevent duplicates
  if (pl.tracks.some(t => t.track_id === track.track_id)) return;
  pl.tracks.push({ ...track, added: new Date().toISOString() });
  save(store);
}

export function removeTrack(playlistId: string, trackId: string): void {
  const store = load();
  const pl = store.find(p => p.id === playlistId);
  if (!pl) return;
  pl.tracks = pl.tracks.filter(t => t.track_id !== trackId);
  save(store);
}

export function moveTrack(playlistId: string, trackId: string, direction: 'up' | 'down'): void {
  const store = load();
  const pl = store.find(p => p.id === playlistId);
  if (!pl) return;
  const idx = pl.tracks.findIndex(t => t.track_id === trackId);
  if (idx === -1) return;
  const newIdx = direction === 'up' ? idx - 1 : idx + 1;
  if (newIdx < 0 || newIdx >= pl.tracks.length) return;
  [pl.tracks[idx], pl.tracks[newIdx]] = [pl.tracks[newIdx], pl.tracks[idx]];
  save(store);
}
