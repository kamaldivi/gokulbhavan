<?php
/**
 * GET /api/daily-highlights.php
 *
 * Returns the 4 daily curated content selections:
 *   bhajan    — 1 random bhajan (title, singer, author, audio)
 *   sloka     — 1 random sloka  (title, lyrics + meaning inlined)
 *   sankirtan — 1 random Nāma Saṅkīrtana (N-01 to N-88)
 *   video     — 1 random video
 *
 * Refreshes daily at 3am server time with no cron required — auto-detected on
 * the first request after the 3am boundary.
 * Avoids repeating any selection from the past 7 days per content type.
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

// ── "Today" anchored to 3am server time ──────────────────────────────────────
// A request arriving between midnight and 2:59am still belongs to the previous day.
$hour  = (int) date('H');
$today = ($hour >= 3) ? date('Y-m-d') : date('Y-m-d', strtotime('-1 day'));

try {
    $pdo = get_db();

    // ── Load existing daily selections ────────────────────────────────────────
    $stmt    = $pdo->query("SELECT content_type, ref_id, selected_date FROM daily_highlight");
    $current = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current[$row['content_type']] = $row;
    }

    // ── Check if any type is missing or stale ────────────────────────────────
    $types        = ['bhajan', 'sloka', 'sankirtan', 'video'];
    $needsRefresh = false;
    foreach ($types as $t) {
        if (!isset($current[$t]) || $current[$t]['selected_date'] !== $today) {
            $needsRefresh = true;
            break;
        }
    }

    if ($needsRefresh) {
        // ── Fetch 7-day exclusion history per type ────────────────────────────
        $history = array_fill_keys($types, []);
        $hStmt   = $pdo->query("
            SELECT content_type, ref_id FROM highlight_history
            WHERE shown_on >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        while ($h = $hStmt->fetch(PDO::FETCH_ASSOC)) {
            $history[$h['content_type']][] = $h['ref_id'];
        }

        $newSelections = [
            'bhajan'    => pickAudioTrack($pdo, 'bhajan',    $history['bhajan']),
            'sloka'     => pickAudioTrack($pdo, 'sloka',     $history['sloka']),
            'sankirtan' => pickSankirtan($history['sankirtan']),
            'video'     => pickVideo($pdo, $history['video']),
        ];

        // ── Persist new selections ────────────────────────────────────────────
        $upsert = $pdo->prepare("
            INSERT INTO daily_highlight (content_type, ref_id, selected_date)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE ref_id = VALUES(ref_id), selected_date = VALUES(selected_date)
        ");
        $histInsert = $pdo->prepare("
            INSERT IGNORE INTO highlight_history (content_type, ref_id, shown_on)
            VALUES (?, ?, ?)
        ");

        foreach ($newSelections as $type => $refId) {
            if ($refId === null) continue;
            $upsert->execute([$type, $refId, $today]);
            $histInsert->execute([$type, $refId, $today]);
        }

        // Reload current from DB
        $stmt    = $pdo->query("SELECT content_type, ref_id FROM daily_highlight");
        $current = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $current[$row['content_type']] = $row;
        }
    }

    // ── Build full response for each selection ────────────────────────────────
    $result = [];

    // Bhajan
    if (isset($current['bhajan'])) {
        $row = fetchAudioTrackRow($pdo, $current['bhajan']['ref_id']);
        if ($row) {
            $result['bhajan'] = [
                'track_id'         => $row['track_id'],
                'track_name'       => $row['track_name'],
                'singer'           => $row['singer'],
                'author'           => $row['author'],
                'audio_file_path'  => $row['audio_file_path'],
                'lyrics_file_path' => $row['lyrics_file_path'],
                'display_name'     => $row['display_name'],
                'download_allowed' => 1,
                'type'             => 'B',
            ];
        }
    }

    // Sloka — lyrics fetched inline so no second round-trip from the browser
    if (isset($current['sloka'])) {
        $row = fetchAudioTrackRow($pdo, $current['sloka']['ref_id']);
        if ($row) {
            [$lyrics, $meaning] = fetchLyricsBody($pdo, $row['track_id'], 'en');
            $result['sloka'] = [
                'track_id'         => $row['track_id'],
                'track_name'       => $row['track_name'],
                'display_name'     => $row['display_name'],
                'audio_file_path'  => $row['audio_file_path'],
                'lyrics_file_path' => $row['lyrics_file_path'],
                'lyrics'           => $lyrics,
                'meaning'          => $meaning,
            ];
        }
    }

    // Sankirtan
    if (isset($current['sankirtan'])) {
        $row = fetchAudioTrackRow($pdo, $current['sankirtan']['ref_id']);
        if ($row) {
            $result['sankirtan'] = [
                'track_id'         => $row['track_id'],
                'track_name'       => $row['track_name'],
                'audio_file_path'  => $row['audio_file_path'],
                'lyrics_file_path' => $row['lyrics_file_path'],
                'display_name'     => $row['display_name'],
                'download_allowed' => 1,
                'type'             => 'N',
            ];
        }
    }

    // Video
    if (isset($current['video'])) {
        $vStmt = $pdo->prepare("
            SELECT video_id, video_title AS title, thumbnail_url
            FROM video WHERE video_id = ?
        ");
        $vStmt->execute([$current['video']['ref_id']]);
        $row = $vStmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $result['video'] = [
                'video_id'      => $row['video_id'],
                'title'         => $row['title'],
                'thumbnail_url' => $row['thumbnail_url'],
            ];
        }
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('daily-highlights.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error']);
}

// ── Helper functions ──────────────────────────────────────────────────────────

/**
 * Pick a random track_id of the given audio_family, excluding recently shown ones.
 * Falls back to any track in the family if the exclusion list covers everything.
 */
function pickAudioTrack(PDO $pdo, string $family, array $exclude): ?string {
    if ($exclude) {
        $ph   = implode(',', array_fill(0, count($exclude), '?'));
        $stmt = $pdo->prepare("
            SELECT t.track_id FROM audio_track t
            JOIN audio_category c ON c.category_code = t.category_code
            WHERE c.audio_family = ?
              AND t.track_id NOT IN ($ph)
            ORDER BY RAND() LIMIT 1
        ");
        $stmt->execute(array_merge([$family], $exclude));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row['track_id'];
    }

    // Fallback: exclusion list exhausted — pick any
    $stmt = $pdo->prepare("
        SELECT t.track_id FROM audio_track t
        JOIN audio_category c ON c.category_code = t.category_code
        WHERE c.audio_family = ?
        ORDER BY RAND() LIMIT 1
    ");
    $stmt->execute([$family]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['track_id'] : null;
}

/**
 * Pick a random Nāma Saṅkīrtana track from N-01 to N-88,
 * avoiding recently shown ones. Falls back to least-recently-seen if pool exhausted.
 */
function pickSankirtan(array $exclude): ?string {
    $nums = range(1, 88);
    shuffle($nums);
    foreach ($nums as $n) {
        $tid = 'N-' . str_pad($n, 2, '0', STR_PAD_LEFT);
        if (!in_array($tid, $exclude, true)) {
            return $tid;
        }
    }
    // Pool exhausted — return the first shuffled (least recently seen will rotate naturally)
    return 'N-' . str_pad($nums[0], 2, '0', STR_PAD_LEFT);
}

/**
 * Pick a random video_id from the whitelisted video categories (HIGHLIGHT_VIDEO_CATEGORIES).
 * Join path: video_category → video_playlist → video_playlist_map → video_id.
 * Excludes recently shown ones. Falls back to any video in the categories if pool exhausted.
 */
function pickVideo(PDO $pdo, array $exclude): ?string {
    $allowedCategories = defined('HIGHLIGHT_VIDEO_CATEGORIES') ? HIGHLIGHT_VIDEO_CATEGORIES : [];

    if (empty($allowedCategories)) {
        // No whitelist configured — pick from all videos
        if ($exclude) {
            $ph   = implode(',', array_fill(0, count($exclude), '?'));
            $stmt = $pdo->prepare("SELECT video_id FROM video WHERE video_id NOT IN ($ph) ORDER BY RAND() LIMIT 1");
            $stmt->execute($exclude);
        } else {
            $stmt = $pdo->query("SELECT video_id FROM video ORDER BY RAND() LIMIT 1");
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['video_id'] : null;
    }

    $catPh  = implode(',', array_fill(0, count($allowedCategories), '?'));
    $params = $allowedCategories;

    if ($exclude) {
        $exPh   = implode(',', array_fill(0, count($exclude), '?'));
        $params = array_merge($allowedCategories, $exclude);
        $stmt   = $pdo->prepare("
            SELECT DISTINCT m.video_id
            FROM video_playlist_map m
            JOIN video_playlist p ON p.playlist_id = m.playlist_id
            JOIN video_category c ON c.category_id = p.category_id
            WHERE c.category_name IN ($catPh)
              AND m.video_id NOT IN ($exPh)
            ORDER BY RAND() LIMIT 1
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row['video_id'];
    }

    // Fallback: exclusion list exhausted — pick any from whitelisted categories
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.video_id
        FROM video_playlist_map m
        JOIN video_playlist p ON p.playlist_id = m.playlist_id
        JOIN video_category c ON c.category_id = p.category_id
        WHERE c.category_name IN ($catPh)
        ORDER BY RAND() LIMIT 1
    ");
    $stmt->execute($allowedCategories);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['video_id'] : null;
}

/**
 * Fetch a single audio_track row joined with its category name.
 */
function fetchAudioTrackRow(PDO $pdo, string $trackId): ?array {
    $stmt = $pdo->prepare("
        SELECT t.track_id, t.track_name, t.singer, a.author_name AS author,
               t.audio_file_path, t.lyrics_file_path,
               c.category_name AS display_name
        FROM audio_track t
        JOIN audio_category c ON c.category_code = t.category_code
        LEFT JOIN audio_author a ON a.id = t.author_id
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Fetch lyrics + meaning body for a given track_id and language.
 * Returns [lyrics_string, meaning_string] (either may be empty string).
 */
function fetchLyricsBody(PDO $pdo, string $trackId, string $lang): array {
    $stmt = $pdo->prepare("
        SELECT content_type, body FROM lyrics
        WHERE track_id = ? AND lang = ?
    ");
    $stmt->execute([$trackId, $lang]);
    $lyrics = '';
    $meaning = '';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['content_type'] === 'lyrics')  $lyrics  = $row['body'];
        if ($row['content_type'] === 'meaning') $meaning = $row['body'];
    }
    return [$lyrics, $meaning];
}
