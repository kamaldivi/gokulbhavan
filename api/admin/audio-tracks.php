<?php
/**
 * Admin API — audio track management (Bhajan, Sloka, Sankirtan)
 *
 * GET    /api/admin/audio-tracks.php?category=A
 *   Returns all tracks for a category with their singer versions.
 *
 * GET    /api/admin/audio-tracks.php?suggest_id=A
 *   Returns the next suggested track_id for a given category_code.
 *   e.g. category has A-01..A-64 → returns "A-65"
 *
 * POST   /api/admin/audio-tracks.php
 *   Create a new track (and optionally singer versions).
 *   Body: {
 *     "track_id":         "A-65",
 *     "track_name":       "New Bhajan",
 *     "category_code":    "A",
 *     "audio_file_path":  "media/audio/bhajan/A/A-65-New Bhajan.mp3",  // from upload
 *     "base_track_path":  "media/audio/base/A-65-New Bhajan - Base.mp3", // optional
 *     "lyrics_file_path": null,   // optional
 *     "download_allowed": 1,
 *     "singers": [                // optional
 *       { "singer": "Priya", "audio_file_path": "media/audio/versions/A-65-New Bhajan - Priya.mp3" }
 *     ]
 *   }
 *
 * PUT    /api/admin/audio-tracks.php
 *   Update track metadata and/or singer versions.
 *   Body: same shape as POST, track_id identifies the record.
 *   singers array: full replacement — send complete desired state.
 *   To remove all singers: send "singers": []
 *
 * DELETE /api/admin/audio-tracks.php
 *   Delete a track, its singer versions, and optionally its files.
 *   Body: { "track_id": "A-65" }
 *   Files are handled by audio-upload.php (moved to media/deleted/).
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Suggest next track ID for a category
    if (isset($_GET['suggest_id'])) {
        $catCode = strtoupper(trim($_GET['suggest_id']));
        if ($catCode === '') {
            http_response_code(400);
            echo json_encode(['message' => 'suggest_id (category_code) required']);
            exit;
        }
        try {
            $pdo  = get_db();
            $stmt = $pdo->prepare("
                SELECT track_id FROM audio_track
                WHERE category_code = :cat
                ORDER BY track_id ASC
            ");
            $stmt->execute([':cat' => $catCode]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Extract max numeric suffix for this category prefix
            $max = 0;
            foreach ($ids as $id) {
                // Match pattern like "A-64", "BG-05", "M-12"
                if (preg_match('/^' . preg_quote($catCode, '/') . '-(\d+)$/i', $id, $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }
            $next   = $max + 1;
            $padded = str_pad($next, 2, '0', STR_PAD_LEFT); // e.g. 05, 65
            echo json_encode(['suggested_id' => "$catCode-$padded"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Database error']);
        }
        exit;
    }

    // List tracks for a category
    $catCode = strtoupper(trim($_GET['category'] ?? ''));
    if ($catCode === '') {
        http_response_code(400);
        echo json_encode(['message' => 'category parameter required']);
        exit;
    }

    try {
        $pdo = get_db();

        $tracks = $pdo->prepare("
            SELECT
                t.track_id,
                t.track_name,
                t.category_code,
                t.audio_file_path,
                t.base_track_path,
                t.lyrics_file_path,
                1 AS download_allowed
            FROM audio_track t
            WHERE t.category_code = :cat
            ORDER BY t.track_id ASC
        ");
        $tracks->execute([':cat' => $catCode]);
        $rows = $tracks->fetchAll();

        if (empty($rows)) {
            echo json_encode([]);
            exit;
        }

        $trackIds     = array_column($rows, 'track_id');
        $placeholders = implode(',', array_fill(0, count($trackIds), '?'));

        $singers = $pdo->prepare("
            SELECT track_id, id AS version_id, singer, audio_file_path
            FROM audio_singer_version
            WHERE track_id IN ($placeholders)
            ORDER BY singer ASC
        ");
        $singers->execute($trackIds);

        $singerMap = [];
        foreach ($singers->fetchAll() as $s) {
            $singerMap[$s['track_id']][] = $s;
        }

        foreach ($rows as &$r) {
            $r['singers'] = $singerMap[$r['track_id']] ?? [];
        }

        echo json_encode(array_values($rows), JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

// ── Parse JSON body ───────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST — create track ───────────────────────────────────────────────────────
if ($method === 'POST') {
    $trackId   = strtoupper(trim($body['track_id']        ?? ''));
    $trackName = trim($body['track_name']                  ?? '');
    $catCode   = strtoupper(trim($body['category_code']   ?? ''));
    $audioPath = trim($body['audio_file_path']             ?? '');
    $basePath  = trim($body['base_track_path']             ?? '') ?: null;
    $lyricsPath= trim($body['lyrics_file_path']            ?? '') ?: null;
    $singers   = $body['singers']                          ?? [];

    if ($trackId === '' || $trackName === '' || $catCode === '' || $audioPath === '') {
        http_response_code(400);
        echo json_encode(['message' => 'track_id, track_name, category_code, and audio_file_path are required']);
        exit;
    }

    try {
        $pdo = get_db();
        $pdo->beginTransaction();

        $pdo->prepare("
            INSERT INTO audio_track
                (track_id, track_name, category_code, audio_file_path, base_track_path, lyrics_file_path)
            VALUES
                (:id, :name, :cat, :audio, :base, :lyrics)
        ")->execute([
            ':id'     => $trackId,
            ':name'   => $trackName,
            ':cat'    => $catCode,
            ':audio'  => $audioPath,
            ':base'   => $basePath,
            ':lyrics' => $lyricsPath,
        ]);

        if (!empty($singers)) {
            $sStmt = $pdo->prepare("
                INSERT INTO audio_singer_version (track_id, singer, audio_file_path)
                VALUES (:tid, :singer, :path)
            ");
            foreach ($singers as $s) {
                $singer   = trim($s['singer'] ?? '');
                $singerPath = trim($s['audio_file_path'] ?? '');
                if ($singer === '' || $singerPath === '') continue;
                $sStmt->execute([':tid' => $trackId, ':singer' => $singer, ':path' => $singerPath]);
            }
        }

        $pdo->commit();
        http_response_code(201);
        echo json_encode(['message' => 'Created', 'track_id' => $trackId]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(['message' => "Track ID '$trackId' already exists"]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Database error']);
        }
    }
    exit;
}

// ── PUT — update track ────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $trackId    = strtoupper(trim($body['track_id']       ?? ''));
    $trackName  = trim($body['track_name']                 ?? '');
    $audioPath  = trim($body['audio_file_path'] ?? '') ?: null;
    $basePath   = array_key_exists('base_track_path',  $body) ? (is_string($body['base_track_path'])  ? (trim($body['base_track_path'])  ?: null) : null) : false;
    $lyricsPath = array_key_exists('lyrics_file_path', $body) ? (is_string($body['lyrics_file_path']) ? (trim($body['lyrics_file_path']) ?: null) : null) : false;
    $singers    = array_key_exists('singers', $body) ? $body['singers'] : false;

    if ($trackId === '') {
        http_response_code(400);
        echo json_encode(['message' => 'track_id required']);
        exit;
    }

    try {
        $pdo = get_db();
        $pdo->beginTransaction();

        // Build dynamic SET clause — only update fields that were sent
        $sets   = [];
        $params = [':id' => $trackId];
        if ($trackName !== '')    { $sets[] = 'track_name = :name';          $params[':name']   = $trackName; }
        if ($audioPath !== null)  { $sets[] = 'audio_file_path = :audio';    $params[':audio']  = $audioPath; }
        if ($basePath !== false)  { $sets[] = 'base_track_path = :base';     $params[':base']   = $basePath; }
        if ($lyricsPath !== false){ $sets[] = 'lyrics_file_path = :lyrics';  $params[':lyrics'] = $lyricsPath; }

        if (!empty($sets)) {
            $stmt = $pdo->prepare("UPDATE audio_track SET " . implode(', ', $sets) . " WHERE track_id = :id");
            $stmt->execute($params);
            if ($stmt->rowCount() === 0 && empty($sets)) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['message' => "Track '$trackId' not found"]);
                exit;
            }
        }

        // Singer versions — full replacement if singers key was present
        if ($singers !== false) {
            $pdo->prepare("DELETE FROM audio_singer_version WHERE track_id = :id")
                ->execute([':id' => $trackId]);

            if (!empty($singers)) {
                $sStmt = $pdo->prepare("
                    INSERT INTO audio_singer_version (track_id, singer, audio_file_path)
                    VALUES (:tid, :singer, :path)
                ");
                foreach ($singers as $s) {
                    $singer     = trim($s['singer'] ?? '');
                    $singerPath = trim($s['audio_file_path'] ?? '');
                    if ($singer === '' || $singerPath === '') continue;
                    $sStmt->execute([':tid' => $trackId, ':singer' => $singer, ':path' => $singerPath]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['message' => 'Updated', 'track_id' => $trackId]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $trackId = strtoupper(trim($body['track_id'] ?? ''));
    if ($trackId === '') {
        http_response_code(400);
        echo json_encode(['message' => 'track_id required']);
        exit;
    }

    try {
        $pdo = get_db();

        // Fetch all file paths before deleting so caller can soft-delete files
        $track = $pdo->prepare("
            SELECT audio_file_path, base_track_path FROM audio_track WHERE track_id = :id
        ");
        $track->execute([':id' => $trackId]);
        $trackRow = $track->fetch();

        if (!$trackRow) {
            http_response_code(404);
            echo json_encode(['message' => "Track '$trackId' not found"]);
            exit;
        }

        $singerPaths = $pdo->prepare("
            SELECT audio_file_path FROM audio_singer_version WHERE track_id = :id
        ");
        $singerPaths->execute([':id' => $trackId]);
        $allSingerPaths = $singerPaths->fetchAll(PDO::FETCH_COLUMN);

        // Delete DB records (singer versions cascade via FK or explicit delete)
        $pdo->prepare("DELETE FROM audio_singer_version WHERE track_id = :id")->execute([':id' => $trackId]);
        $pdo->prepare("DELETE FROM audio_track WHERE track_id = :id")->execute([':id' => $trackId]);

        // Return file paths so the UI can call audio-upload.php to soft-delete them
        $filesToDelete = array_filter(array_merge(
            [$trackRow['audio_file_path'], $trackRow['base_track_path']],
            $allSingerPaths
        ));

        echo json_encode([
            'message'          => 'Deleted',
            'track_id'         => $trackId,
            'files_to_archive' => array_values($filesToDelete),
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['message' => 'Database error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['message' => 'Method not allowed']);
