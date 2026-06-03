<?php
/**
 * YouTube Playlist Sync — gokulbhavan.org/api/youtubesync.php
 *
 * Reads playlists from video_playlist, calls the YouTube Data API v3
 * for each, and upserts results into video + video_playlist_map.
 *
 * One row per unique video in `video` (deduplicated by video_id).
 * Playlist membership is recorded in video_playlist_map (many-to-many).
 * track_id is resolved from audio_track via the [ID] tag in the title.
 *
 * Access:
 *   https://gokulbhavan.org/api/youtubesync.php?token=YOUR_SECRET_TOKEN
 *
 * Token is stored in config.php as SYNC_TOKEN.
 * Safe to run multiple times — INSERT ... ON DUPLICATE KEY UPDATE and
 * INSERT IGNORE ensure no data is lost if interrupted mid-way.
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
        // audio_track table not yet created — track linkage deferred to audio migration phase
        return null;
    }
}

/**
 * Fetch true published_date for a batch of new video IDs via videos.list.
 * Returns a map of video_id => published_date string (Y-m-d).
 * Videos not returned by the API (deleted, private) are omitted.
 *
 * @param  string[] $videoIds  Up to 50 IDs
 * @return array<string,string>
 */
function fetchPublishedDates(string $apiKey, array $videoIds): array {
    if (empty($videoIds)) return [];

    $url  = 'https://www.googleapis.com/youtube/v3/videos'
          . '?part=snippet'
          . '&id=' . urlencode(implode(',', $videoIds))
          . '&key=' . urlencode($apiKey);

    $json = @file_get_contents($url);
    if ($json === false) return [];

    $data = json_decode($json);
    if (!isset($data->items)) return [];

    $map = [];
    foreach ($data->items as $item) {
        $map[$item->id] = substr($item->snippet->publishedAt ?? '', 0, 10);
    }
    return $map;
}

/**
 * Upsert a batch of videos and record their playlist memberships.
 *
 * video is deduplicated by video_id — one row per unique YouTube video.
 * For new videos: published_date comes from videos.list (true upload date),
 *   updated_date from playlistItems publishedAt (playlist-added date).
 * For existing videos: published_date is never overwritten; only
 *   video_title, thumbnail_url, and updated_date are refreshed.
 * track_id uses COALESCE so a resolved link is never cleared by a later
 *   sync pass where the title tag is absent.
 *
 * @param array<string,array> $videos  Keyed by video_id, each entry:
 *   [title, thumb, updated_date, extracted_id, is_new, published_date]
 */
function upsertVideoBatch(PDO $db, string $playlistId, array $videos): void {
    $videoStmt = $db->prepare("
        INSERT INTO video (video_id, video_title, thumbnail_url, published_date, updated_date, track_id)
        VALUES (:video_id, :title, :thumb, :published_date, :updated_date, :track_id)
        ON DUPLICATE KEY UPDATE
            video_title    = VALUES(video_title),
            thumbnail_url  = VALUES(thumbnail_url),
            updated_date   = VALUES(updated_date),
            track_id       = COALESCE(VALUES(track_id), track_id)
    ");

    $mapStmt = $db->prepare("
        INSERT IGNORE INTO video_playlist_map (video_id, playlist_id)
        VALUES (:video_id, :playlist_id)
    ");

    foreach ($videos as $videoId => $v) {
        $trackId = resolveTrackId($db, $v['extracted_id']);

        $videoStmt->execute([
            ':video_id'      => $videoId,
            ':title'         => $v['title'],
            ':thumb'         => $v['thumb'],
            ':published_date'=> $v['published_date'],
            ':updated_date'  => $v['updated_date'],
            ':track_id'      => $trackId,
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

    // Get YouTube API key from global
    $row = $db->query("SELECT youtube_api_key FROM global LIMIT 1")->fetch();
    if (!$row || empty($row['youtube_api_key'])) {
        die('<p class="err">YouTube API key not found in global.</p></body></html>');
    }
    $apiKey = $row['youtube_api_key'];

    // Fetch all playlists from the new schema
    $playlists = $db->query("
        SELECT vp.playlist_id, vp.playlist_name, vc.category_name
        FROM video_playlist vp
        JOIN video_category vc ON vc.category_id = vp.category_id
        ORDER BY vc.category_name, vp.playlist_name
    ")->fetchAll();

    echo '<p>Found <b>' . count($playlists) . '</b> playlists to sync.</p>';
    flush();

    $totalAdded      = 0;
    $totalUpdated    = 0;
    $totalSkippedPrivate   = 0;
    $totalSkippedFiltered  = 0;
    $totalMapRemoved = 0;

    foreach ($playlists as $pl) {
        $playlistId   = $pl['playlist_id'];
        $playlistName = $pl['playlist_name'];
        $categoryName = $pl['category_name'];

        echo '<p class="head">[' . htmlspecialchars($categoryName) . '] '
             . htmlspecialchars($playlistName) . ' (' . htmlspecialchars($playlistId) . ')</p>';
        flush();

        $pageToken        = '';
        $playlistDone     = 0;
        $syncedVideoIds   = [];   // all video_ids seen from YouTube this run (for reconciliation)
        $skippedVideoIds  = [];   // video_ids skipped (private/deleted/filtered) for this playlist
        $syncSuccess      = true; // set false on any API/network error — skips reconciliation

        do {
            $url = 'https://www.googleapis.com/youtube/v3/playlistItems'
                 . '?part=snippet'
                 . '&maxResults=50'
                 . '&playlistId=' . urlencode($playlistId)
                 . '&key='        . urlencode($apiKey)
                 . ($pageToken ? '&pageToken=' . urlencode($pageToken) : '');

            $json = @file_get_contents($url);
            if ($json === false) {
                echo '<p class="err">&nbsp;&nbsp;Network error fetching playlist — skipping reconciliation.</p>';
                $syncSuccess = false;
                break;
            }

            $data = json_decode($json);
            if (isset($data->error)) {
                $reason = $data->error->errors[0]->reason ?? '';

                // ── Playlist deleted on YouTube ────────────────
                if ($data->error->code === 404 || $reason === 'playlistNotFound') {
                    echo '<p class="warn">&nbsp;&nbsp;Playlist not found on YouTube — removing from database.</p>';
                    $db->prepare("DELETE FROM video_playlist_map WHERE playlist_id = ?")
                       ->execute([$playlistId]);
                    $db->prepare("DELETE FROM video_playlist WHERE playlist_id = ?")
                       ->execute([$playlistId]);
                    echo '<p class="warn">&nbsp;&nbsp;Removed playlist and its video mappings.</p>';
                    $syncSuccess = false; // no reconciliation needed — already cleaned up
                } else {
                    echo '<p class="err">&nbsp;&nbsp;YouTube API error (' . (int)$data->error->code . '): '
                         . htmlspecialchars($data->error->message) . ' — skipping reconciliation.</p>';
                    $syncSuccess = false;
                }
                break;
            }

            // ── Collect videos from this page ─────────────────
            $pageBatch   = [];   // video_id => data (no published_date yet)
            $newVideoIds = [];   // video_ids not yet in DB — need videos.list call

            foreach ($data->items as $item) {
                $videoId  = $item->snippet->resourceId->videoId ?? '';
                if (!$videoId) continue;

                // Always track video_id as seen — even private/deleted entries keep their
                // existing DB row so a temporarily-private video isn't wiped from the map.
                $syncedVideoIds[] = $videoId;

                $rawTitle = $item->snippet->title ?? '';

                // Skip upserting private/deleted/unlisted videos
                if (in_array($rawTitle, ['Deleted video', 'Private video', 'Unlisted video'])) {
                    $skippedVideoIds[] = $videoId . ' (' . $rawTitle . ')';
                    $totalSkippedPrivate++;
                    continue;
                }
                if (str_contains($rawTitle, 'Sun Pictures')) {
                    $skippedVideoIds[] = $videoId . ' (filtered)';
                    $totalSkippedFiltered++;
                    continue;
                }

                $extractedId = extractId($rawTitle);
                $title       = cleanTitle($rawTitle, $extractedId);
                $thumb       = $item->snippet->thumbnails->medium->url
                               ?? ($item->snippet->thumbnails->default->url ?? '');
                $updatedDate = substr($item->snippet->publishedAt ?? '', 0, 10);

                // Check if this video already exists in DB
                $exists = $db->prepare("SELECT 1 FROM video WHERE video_id = ? LIMIT 1");
                $exists->execute([$videoId]);
                $isNew = !$exists->fetch();

                $pageBatch[$videoId] = [
                    'title'          => $title,
                    'thumb'          => $thumb,
                    'updated_date'   => $updatedDate,
                    'extracted_id'   => $extractedId,
                    'is_new'         => $isNew,
                    'published_date' => $updatedDate, // fallback; overwritten for new videos below
                ];

                if ($isNew) {
                    $newVideoIds[] = $videoId;
                }
            }

            // ── Fetch true published_date for new videos ───────
            // One videos.list call per page (up to 50 IDs) — only for new rows.
            if (!empty($newVideoIds)) {
                $publishedMap = fetchPublishedDates($apiKey, $newVideoIds);
                foreach ($newVideoIds as $vid) {
                    if (isset($publishedMap[$vid])) {
                        $pageBatch[$vid]['published_date'] = $publishedMap[$vid];
                    }
                }
            }

            // ── Upsert batch and report ────────────────────────
            upsertVideoBatch($db, $playlistId, $pageBatch);

            foreach ($pageBatch as $videoId => $v) {
                if ($v['is_new']) {
                    echo '<p class="ok">&nbsp;&nbsp;+added: ' . htmlspecialchars($v['title']) . '</p>';
                    $totalAdded++;
                } else {
                    echo '<p class="skip">&nbsp;&nbsp;updated: ' . htmlspecialchars($v['title']) . '</p>';
                    $totalUpdated++;
                }
                $playlistDone++;
                flush();
            }

            $pageToken = $data->nextPageToken ?? '';

        } while ($pageToken !== '');

        $skippedNote = !empty($skippedVideoIds)
            ? ' &nbsp;|&nbsp; skipped: ' . implode(', ', array_map('htmlspecialchars', $skippedVideoIds))
            : '';
        echo '<p>&nbsp;&nbsp;<b>' . $playlistDone . ' videos processed for this playlist.</b>'
             . $skippedNote . '</p>';

        // ── Reconcile: remove videos YouTube no longer has in this playlist ──
        // Only runs when all pages fetched successfully (syncSuccess = true).
        if ($syncSuccess && !empty($syncedVideoIds)) {
            $placeholders = implode(',', array_fill(0, count($syncedVideoIds), '?'));
            $delStmt = $db->prepare("
                DELETE FROM video_playlist_map
                WHERE playlist_id = ?
                  AND video_id NOT IN ($placeholders)
            ");
            $delStmt->execute(array_merge([$playlistId], $syncedVideoIds));
            $removed = $delStmt->rowCount();
            if ($removed > 0) {
                echo '<p class="warn">&nbsp;&nbsp;-reconciled: ' . $removed
                     . ' video(s) removed from playlist map (no longer on YouTube).</p>';
                $totalMapRemoved += $removed;
            }
        } elseif ($syncSuccess && empty($syncedVideoIds)) {
            // YouTube returned zero videos with no error.
            // Check totalResults: 0 means the playlist genuinely has no videos
            // (or doesn't exist on YouTube) — safe to remove from DB.
            // If totalResults is missing entirely, skip to be safe.
            if (isset($data->pageInfo->totalResults) && $data->pageInfo->totalResults === 0) {
                echo '<p class="warn">&nbsp;&nbsp;Playlist exists but has no videos on YouTube — removing from database.</p>';
                $db->prepare("DELETE FROM video_playlist_map WHERE playlist_id = ?")
                   ->execute([$playlistId]);
                $db->prepare("DELETE FROM video_playlist WHERE playlist_id = ?")
                   ->execute([$playlistId]);
                echo '<p class="warn">&nbsp;&nbsp;Removed playlist and its video mappings.</p>';
            } else {
                echo '<p class="warn">&nbsp;&nbsp;YouTube returned 0 videos (unexpected) — skipping. Manual check recommended.</p>';
            }
        }

        flush();
    }

    // ── Orphan cleanup: remove videos no longer in any playlist ─────────────
    $orphanStmt  = $db->query("
        DELETE FROM video
        WHERE video_id NOT IN (SELECT video_id FROM video_playlist_map)
    ");
    $orphanCount = $orphanStmt->rowCount();
    if ($orphanCount > 0) {
        echo '<p class="warn">Removed ' . $orphanCount
             . ' orphaned video(s) no longer in any playlist.</p>';
    }

    echo '<p class="sum">Sync complete: '
         . $totalAdded           . ' added, '
         . $totalUpdated         . ' updated, '
         . $totalSkippedPrivate  . ' skipped (private/deleted), '
         . $totalSkippedFiltered . ' skipped (filtered), '
         . $totalMapRemoved      . ' map row(s) reconciled, '
         . $orphanCount          . ' orphan(s) removed.</p>';
    echo '<p>Finished: ' . date('Y-m-d H:i:s') . '</p>';

} catch (PDOException $e) {
    echo '<p class="err">Database error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '</body></html>';
