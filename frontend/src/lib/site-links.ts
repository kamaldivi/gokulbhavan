/**
 * Global site links — single source of truth for Astro components.
 * Values come from VITE_ env vars (set in frontend/.env).
 * Keep in sync with ZOOM_URL / YOUTUBE_URL in api/config.php.
 */
export const ZOOM_URL    = import.meta.env.VITE_ZOOM_URL    ?? '';
export const YOUTUBE_URL = import.meta.env.VITE_YOUTUBE_URL ?? '';
