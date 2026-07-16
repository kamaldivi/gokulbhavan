<?php
/**
 * YouTube Playlist Sync — gokulbhavan.org/api/youtubesync.php
 *
 * Two-phase rebuild strategy:
 *   Phase 1 — Clear all existing video and playlist-map data from the database.
 *   Phase 2 — Re-fetch every playlist from YouTube and insert fresh data.
 *
 * Deleted playlists (YouTube returns 404) are removed from video_playlist.
 * Private, deleted, and content-filtered videos are skipped.
 * A brief downtime window (no videos visible) occurs during the sync.
 *
 * Access:
 *   https://gokulbhavan.org/api/youtubesync.php?token=YOUR_SECRET_TOKEN
 *
 * Token is stored in config.php as SYNC_TOKEN.
 */

// ── Output buffering: flush progress to browser in real-time ─
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);
ob_implicit_flush(true);
if (ob_get_level()) ob_end_flush();

header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no');

require __DIR__ . '/config.php';

// ── Token check ───────────────────────────────────────────────
if (!defined('SYNC_TOKEN') || SYNC_TOKEN === '') {
    http_response_code(403);
    die('<b>Error:</b> SYNC_TOKEN not configured in config.php');
}
$provided = trim($_GET['token'] ?? '');
if (!hash_equals(SYNC_TOKEN, $provided)) {
    http_response_code(403);
    die('<b>Error:</b> Invalid token.');
}

// ── Helpers ───────────────────────────────────────────────────

/**
 * Extract audio track ID from video title.
 * Titles contain an optional tag like "[A-10]" or "[BJ-05]".
 */
function extractId(string $title): string {
    $start = strpos($title, '[');
    $end   = strpos($title, ']');
    if ($start !== false && $end !== false && $end > $start) {
        return substr($title, $start + 1, $end - $start - 1);
    }
    return '';
}

/**
 * Clean title — remove quotes, asterisks, and strip the ID tag.
 */
function cleanTitle(string $title, string $id): string {
    $title = str_replace(['"', "'", '*'], '', $title);
    if ($id !== '') {
        $bracketPos = strpos($title, '[');
        if ($bracketPos !== false) {
            $title = substr($title, 0, $bracketPos);
        }
    }
    return trim($title);
}

/**
 * Resolve a track_id from audio_track by the ID extracted from the title.
 * Returns the track_id string if found, null otherwise.
 */
function resolveTrackId(PDO $db, string $extractedId): ?string {
    if ($extractedId === '') return null;
    try {
        $stmt = $db->prepare("SELECT track_id FROM audio_track WHERE track_id = ? LIMIT 1");
        $stmt->execute([$extractedId]);
        $row = $stmt->fetch();
        return $row ? $row['track_id'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Fetch true published_date for a batch of video IDs via videos.list.
 * Returns a map of video_id => published_date string (Y-m-d).
 * Videos not returned by the API are omitted from the map.
 *
 * Uses ignore_errors so PHP returns the response body on 4xx/5xx
 * rather than false, allowing API errors to be detected cleanly.
 *
 * @param  string[] $videoIds  Up to 50 IDs
 * @return array<string,string>
 */
function fetchPublishedDates(string $apiKey, array $videoIds): array {
    if (empty($videoIds)) return [];

    $url = 'https://www.googleapis.com/youtube/v3/videos'
         . '?part=snippet'
         . '&id=' . urlencode(implode(',', $videoIds))
         . '&key=' . urlencode($apiKey);

    $ctx  = stream_context_create(['http' => ['ignore_errors' => true]]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) return [];

    $data = json_decode($json);
    if (isset($data->error) || !isset($data->items)) return [];

    $map = [];
    foreach ($data->items as $item) {
        $map[$item->id] = substr($item->snippet->publishedAt ?? '', 0, 10);
    }
    return $map;
}

/**
 * Insert a batch of videos and record their playlist memberships.
 * INSERT IGNORE on video deduplicates videos that appear in multiple playlists —
 * the first playlist's data wins, subsequent inserts for the same video_id are skipped.
 * video_playlist_map always gets one row per (video_id, playlist_id) pair.
 *
 * @param array<string,array> $videos  Keyed by video_id, each entry:
 *   [title, thumb, updated_date, extracted_id, published_date]
 */
function insertVideoBatch(PDO $db, string $playlistId, array $videos): void {
    $videoStmt = $db->prepare("
        INSERT IGNORE INTO video (video_id, video_title, thumbnail_url, published_date, updated_date, track_id)
        VALUES (:video_id, :title, :thumb, :published_date, :updated_date, :track_id)
    ");

    $mapStmt = $db->prepare("
        INSERT IGNORE INTO video_playlist_map (video_id, playlist_id)
        VALUES (:video_id, :playlist_id)
    ");

    foreach ($videos as $videoId => $v) {
        $trackId = resolveTrackId($db, $v['extracted_id']);

        $videoStmt->execute([
            ':video_id'       => $videoId,
            ':title'          => $v['title'],
            ':thumb'          => $v['thumb'],
            ':published_date' => $v['published_date'],
            ':updated_date'   => $v['updated_date'],
            ':track_id'       => $trackId,
        ]);

        $mapStmt->execute([':video_id' => $videoId, ':playlist_id' => $playlistId]);
    }
}

// ── Main sync ─────────────────────────────────────────────────
echo '<html><head><meta charset="utf-8">
<style>
  body { font-family: monospace; font-size: 13px; padding: 20px; background:#f9f6ee; }
  .ok   { color: #2a6a2a; }
  .skip { color: #999; }
  .warn { color: #c86400; font-weight: bold; }
  .err  { color: #c00; font-weight: bold; }
  .head { color: #082A4A; font-weight: bold; border-bottom: 1px solid #ccc; margin-top: 16px; }
  .sum  { font-size: 15px; font-weight: bold; color: #082A4A; margin-top: 20px; }
</style>
</head><body>';

echo '<p class="head">Gokul Bhavan — YouTube Sync</p>';
echo '<p>Started: ' . date('Y-m-d H:i:s') . '</p>';
flush();

try {
    $db = get_db();

    // Get YouTube API key from global table
    $row = $db->query("SELECT youtube_api_key FROM global LIMIT 1")->fetch();
    if (!$row || empty($row['youtube_api_key'])) {
        die('<p class="err">YouTube API key not found in global table.</p></body></html>');
    }
    $apiKey = $row['youtube_api_key'];

    // Load all playlists from DB before clearing video data
    $playlists = $db->query("
        SELECT vp.playlist_id, vp.playlist_name, vc.category_name
        FROM video_playlist vp
        JOIN video_category vc ON vc.category_id = vp.category_id
        ORDER BY vc.category_name, vp.playlist_name
    ")->fetchAll();

    echo '<p>Found <b>' . count($playlists) . '</b> playlists.</p>';
    flush();

    // ── Phase 1: Clear all existing video data ────────────────
    echo '<p class="head">Phase 1 — Clearing existing video data</p>';
    $db->exec("DELETE FROM video_playlist_map");
    $db->exec("DELETE FROM video");
    echo '<p>video and video_playlist_map tables cleared. Rebuilding from YouTube…</p>';
    flush();

    // ── Phase 2: Rebuild from YouTube ─────────────────────────
    echo '<p class="head">Phase 2 — Fetching from YouTube</p>';
    flush();

    $totalAdded           = 0;
    $totalSkippedPrivate  = 0;
    $totalSkippedFiltered = 0;
    $totalPlaylistRemoved = 0;

    foreach ($playlists as $pl) {
        $playlistId   = $pl['playlist_id'];
        $playlistName = $pl['playlist_name'];
        $categoryName = $pl['category_name'];

        echo '<p class="head">[' . htmlspecialchars($categoryName) . '] '
             . htmlspecialchars($playlistName) . ' (' . htmlspecialchars($playlistId) . ')</p>';
        flush();

        $pageToken       = '';
        $playlistDone    = 0;
        $playlistSkipped = 0;

        do {
            $url = 'https://www.googleapis.com/youtube/v3/playlistItems'
                 . '?part=snippet'
                 . '&maxResults=50'
                 . '&playlistId=' . urlencode($playlistId)
                 . '&key='        . urlencode($apiKey)
                 . ($pageToken ? '&pageToken=' . urlencode($pageToken) : '');

            // ignore_errors returns the response body even on 4xx,
            // so deleted playlists (404) are detected and removed from DB.
            $ctx  = stream_context_create(['http' => ['ignore_errors' => true]]);
            $json = @file_get_contents($url, false, $ctx);
            if ($json === false) {
                echo '<p class="err">&nbsp;&nbsp;Network error — skipping playlist.</p>';
                break;
            }

            $data = json_decode($json);
            if (isset($data->error)) {
                $reason = $data->error->errors[0]->reason ?? '';
                if ($data->error->code === 404 || $reason === 'playlistNotFound') {
                    echo '<p class="warn">&nbsp;&nbsp;Playlist not found on YouTube — removing from database.</p>';
                    $db->prepare("DELETE FROM video_playlist WHERE playlist_id = ?")
                       ->execute([$playlistId]);
                    $totalPlaylistRemoved++;
                } else {
                    echo '<p class="err">&nbsp;&nbsp;YouTube API error (' . (int)$data->error->code . '): '
                         . htmlspecialchars($data->error->message) . '</p>';
                }
                break;
            }

            // ── Collect videos from this page ─────────────────
            $pageBatch   = [];
            $allVideoIds = [];

            foreach ($data->items as $item) {
                $videoId  = $item->snippet->resourceId->videoId ?? '';
                if (!$videoId) continue;

                $rawTitle = $item->snippet->title ?? '';

                if (in_array($rawTitle, ['Deleted video', 'Private video', 'Unlisted video'])) {
                    $totalSkippedPrivate++;
                    $playlistSkipped++;
                    continue;
                }
                if (str_contains($rawTitle, 'Sun Pictures')) {
                    $totalSkippedFiltered++;
                    $playlistSkipped++;
                    continue;
                }

                $extractedId = extractId($rawTitle);
                $title       = cleanTitle($rawTitle, $extractedId);
                $thumb       = $item->snippet->thumbnails->medium->url
                               ?? ($item->snippet->thumbnails->default->url ?? '');
                $updatedDate = substr($item->snippet->publishedAt ?? '', 0, 10);

                $pageBatch[$videoId] = [
                    'title'          => $title,
                    'thumb'          => $thumb,
                    'updated_date'   => $updatedDate,
                    'extracted_id'   => $extractedId,
                    'published_date' => $updatedDate, // fallback; overwritten below
                ];
                $allVideoIds[] = $videoId;
            }

            // ── Fetch true published_date for all videos on this page ──
            // Called for every video since the table is rebuilt fresh each sync.
            if (!empty($allVideoIds)) {
                $publishedMap = fetchPublishedDates($apiKey, $allVideoIds);
                foreach ($allVideoIds as $vid) {
                    if (isset($publishedMap[$vid])) {
                        $pageBatch[$vid]['published_date'] = $publishedMap[$vid];
                    }
                }
            }

            // ── Insert batch ───────────────────────────────────
            insertVideoBatch($db, $playlistId, $pageBatch);

            foreach ($pageBatch as $v) {
                echo '<p class="ok">&nbsp;&nbsp;+' . htmlspecialchars($v['title']) . '</p>';
            }
            $playlistDone += count($pageBatch);
            $totalAdded   += count($pageBatch);
            flush();

            $pageToken = $data->nextPageToken ?? '';

        } while ($pageToken !== '');

        $skippedNote = $playlistSkipped > 0
            ? ' &nbsp;|&nbsp; ' . $playlistSkipped . ' skipped (private/deleted/filtered)'
            : '';
        echo '<p>&nbsp;&nbsp;<b>' . $playlistDone . ' videos added for this playlist.</b>'
             . $skippedNote . '</p>';
        flush();
    }

    echo '<p class="sum">Sync complete: '
         . $totalAdded           . ' video(s) added, '
         . $totalSkippedPrivate  . ' skipped (private/deleted), '
         . $totalSkippedFiltered . ' skipped (filtered), '
         . $totalPlaylistRemoved . ' playlist(s) removed from database.</p>';
    echo '<p>Finished: ' . date('Y-m-d H:i:s') . '</p>';

} catch (PDOException $e) {
    echo '<p class="err">Database error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '</body></html>';
