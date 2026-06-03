<?php
/**
 * GET /api/videos.php
 *
 * Query parameters (all optional, combinable):
 *   playlist_id=PLxxx              — single YouTube playlist ID
 *   playlist_ids=PLxxx,PLyyy       — comma-separated YouTube playlist IDs
 *   playlist_name=Jaiva+Dharma     — playlist by name (used by programs.astro)
 *   category_id=4                  — filter by category
 *   category=Course-English        — filter by category name (convenience for static params)
 *   limit=N                        — max rows to return
 *   sort=title                     — sort column: title (default), published_date, updated_date
 *   sort_order=asc                 — asc or desc (default: asc for title, desc for dates)
 *
 * Filter priority: playlist_ids > playlist_id > playlist_name > category_id > category > (all)
 * Default sort: title ASC. Use sort=published_date for "latest videos" queries.
 *
 * Response (array of):
 * {
 *   "video_id":       "dQw4w9WgXcQ",
 *   "title":          "Bhagavad Gita Ch 1",
 *   "thumbnail_url":  "https://i.ytimg.com/...",
 *   "date":           "2024-03-15",   — published_date (true upload date)
 *   "published_date": "2024-03-15",
 *   "playlist_id":    "PLxxx",
 *   "playlist":       "Bhagavad Gita",
 *   "category":       "Course-English"
 * }
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

// ── Parse params ─────────────────────────────────────────────
$limit        = isset($_GET['limit'])        ? max(1, (int) $_GET['limit']) : null;
$playlistId   = isset($_GET['playlist_id'])  ? trim($_GET['playlist_id'])   : null;
$playlistName = isset($_GET['playlist_name'])? trim($_GET['playlist_name']) : null;
$categoryId   = isset($_GET['category_id'])  ? (int) $_GET['category_id']  : null;
$category     = isset($_GET['category'])     ? trim($_GET['category'])      : null;

// Sort — whitelist column and order
$SORT_COLUMNS = [
    'published_date' => 'v.published_date',
    'title'          => 'v.video_title',
    'updated_date'   => 'v.updated_date',
];
$sortColKey  = strtolower(trim($_GET['sort'] ?? 'title'));
$sortCol     = $SORT_COLUMNS[$sortColKey] ?? 'v.video_title';
$sortOrderRaw = strtolower(trim($_GET['sort_order'] ?? ''));
// Default: ASC for title, DESC for dates
$defaultOrder = ($sortColKey === 'title') ? 'ASC' : 'DESC';
$sortOrder    = ($sortOrderRaw === 'asc') ? 'ASC' : (($sortOrderRaw === 'desc') ? 'DESC' : $defaultOrder);

$playlistIds = [];
if (!empty($_GET['playlist_ids'])) {
    $playlistIds = array_values(array_filter(
        array_map('trim', explode(',', $_GET['playlist_ids']))
    ));
}

// ── Base query ────────────────────────────────────────────────
// Joins back to video_playlist_map to get playlist/category context.
// GROUP BY v.video_id on the unfiltered query ensures one row per video.
$baseSelect = "
    SELECT
        v.video_id,
        v.video_title          AS title,
        v.thumbnail_url,
        v.published_date       AS date,
        v.published_date,
        vp.playlist_id,
        vp.playlist_name       AS playlist,
        vc.category_name       AS category
    FROM video v
    JOIN video_playlist_map vpm ON vpm.video_id    = v.video_id
    JOIN video_playlist     vp  ON vp.playlist_id  = vpm.playlist_id
    JOIN video_category     vc  ON vc.category_id  = vp.category_id
";

$where  = [];
$params = [];

try {
    $db = get_db();

    // ── Build WHERE ───────────────────────────────────────────

    // Priority 1: multiple playlist IDs
    if (!empty($playlistIds)) {
        $placeholders = [];
        foreach ($playlistIds as $i => $pid) {
            $key = ":pid$i";
            $placeholders[] = $key;
            $params[$key]   = $pid;
        }
        $where[] = "vpm.playlist_id IN (" . implode(',', $placeholders) . ")";
    }
    // Priority 2: single playlist ID
    elseif ($playlistId !== null) {
        $where[]              = "vpm.playlist_id = :playlist_id";
        $params[':playlist_id'] = $playlistId;
    }
    // Priority 3: playlist by name
    elseif ($playlistName !== null) {
        $where[]               = "vp.playlist_name = :playlist_name";
        $params[':playlist_name'] = $playlistName;
    }
    // Priority 4: category by ID
    elseif ($categoryId !== null) {
        $where[]               = "vc.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }
    // Priority 5: category by name
    elseif ($category !== null) {
        $where[]              = "vc.category_name = :category";
        $params[':category']  = $category;
    }

    $sql = $baseSelect;
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    } else {
        // No filter: one row per video (video may appear in multiple playlists)
        $sql .= " GROUP BY v.video_id";
    }

    $sql .= " ORDER BY $sortCol $sortOrder";

    if ($limit !== null) {
        $sql .= " LIMIT " . $limit;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('videos.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Server error. Please try again later.']);
}
