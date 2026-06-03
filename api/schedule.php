<?php
/**
 * Program Schedule API — gokulbhavan.org/api/schedule.php
 *
 * Returns active program schedules, optionally filtered by site.
 *
 * GET params:
 *   site_id  — filter by site (e.g. 'gokulbhavan', 'tamil'). Omit for all.
 *
 * Response: JSON array of schedule objects, ordered by order_num, day_of_week, time_est.
 *
 * Time zones: time_est is stored as America/New_York. The API converts to:
 *   time_ist     — Asia/Kolkata     (India)
 *   time_cst     — America/Chicago  (USA-Central)
 *   time_nigeria — Africa/Lagos     (Nigeria / WAT)
 * All conversions use proper PHP timezone IDs so DST is handled correctly.
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = get_db();

    $siteId = trim($_GET['site_id'] ?? '');

    // ?all=1 — return all programs including completed (used by the public programs page).
    // Default: only active/upcoming (end_date is null or in the future).
    $includeAll = isset($_GET['all']) && $_GET['all'] === '1';
    $dateFilter = $includeAll ? '' : 'AND (end_date IS NULL OR end_date >= CURDATE())';

    if ($siteId !== '') {
        $stmt = $db->prepare("
            SELECT id, title, description,
                   day_of_week, time_est,
                   zoom_url, youtube_live_url, video_playlist,
                   teacher, platform, duration_min,
                   language, site_id,
                   start_date, end_date, event_date, event_time
            FROM program
            WHERE site_id = :site_id $dateFilter
            ORDER BY order_num ASC, FIELD(day_of_week,
              'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'
            ), time_est ASC
        ");
        $stmt->execute([':site_id' => $siteId]);
    } else {
        $stmt = $db->query("
            SELECT id, title, description,
                   day_of_week, time_est,
                   zoom_url, youtube_live_url, video_playlist,
                   teacher, platform, duration_min,
                   language, site_id,
                   start_date, end_date, event_date, event_time
            FROM program
            WHERE 1=1 $dateFilter
            ORDER BY order_num ASC, FIELD(day_of_week,
              'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'
            ), time_est ASC
        ");
    }

    $rows = $stmt->fetchAll();

    // Convert EST to other time zones for each row.
    // Skip conversion for one-off / completed programs that have no day_of_week.
    foreach ($rows as &$row) {
        if ($row['time_est'] && $row['day_of_week']) {
            [$row['time_ist'],     $row['day_ist']]     = deriveDateTime($row['time_est'], $row['day_of_week'], 'Asia/Kolkata');
            [$row['time_cst'],     $row['day_cst']]     = deriveDateTime($row['time_est'], $row['day_of_week'], 'America/Chicago');
            [$row['time_nigeria'], $row['day_nigeria']] = deriveDateTime($row['time_est'], $row['day_of_week'], 'Africa/Lagos');
        } else {
            $row['time_ist'] = $row['day_ist'] = '';
            $row['time_cst'] = $row['day_cst'] = '';
            $row['time_nigeria'] = $row['day_nigeria'] = '';
        }
    }
    unset($row);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}

/**
 * Convert a time+day from America/New_York to a target PHP timezone ID.
 * Returns [timeString, dayName] — the day may differ from the source day
 * when the conversion crosses midnight (e.g. 8 PM EST Friday = 6:30 AM IST Saturday).
 *
 * DST-correct: anchors to the actual next upcoming occurrence of the given
 * day of week so PHP resolves the real current UTC offset (EDT vs EST etc.).
 *
 * @return array{0: string, 1: string}  [time, day_of_week]
 */
function deriveDateTime(string $estStr, string $sourceDayName, string $targetTz): array {
    if (!$estStr) return ['', $sourceDayName];
    try {
        // Find the actual next occurrence of sourceDayName from today.
        // This ensures PHP uses the real DST offset currently in effect.
        $now  = new DateTime('now', new DateTimeZone('America/New_York'));
        $next = clone $now;
        $next->modify("next $sourceDayName");

        // If today is already that day of week, use today's date.
        if ($now->format('l') === $sourceDayName) {
            $next = $now;
        }

        $dateStr = $next->format('Y-m-d');
        $dt = new DateTime("$dateStr $estStr", new DateTimeZone('America/New_York'));
        $dt->setTimezone(new DateTimeZone($targetTz));
        return [$dt->format('g:i A'), $dt->format('l')];
    } catch (Exception $e) {
        return [$estStr, $sourceDayName];
    }
}
