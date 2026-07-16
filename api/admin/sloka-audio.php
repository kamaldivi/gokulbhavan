<?php
/**
 * Admin API — sloka MP3 upload / archive
 *
 * POST /api/admin/sloka-audio.php
 *   Upload an MP3 for a sloka. Stored as:
 *     media/audio/sloka/{sloka_id} - {first_4_words_of_search_title}.mp3
 *
 *   Multipart form fields:
 *     file         — the MP3 file (required)
 *     sloka_id     — numeric id of the sloka (required)
 *     search_title — plain-ASCII label used for filename (optional, falls back to "sloka")
 *
 *   Response: { "path": "media/audio/sloka/42 - om namo bhagavate vasudevaya.mp3" }
 *
 * DELETE /api/admin/sloka-audio.php
 *   Soft-delete: move file to media/deleted/ with datetime suffix.
 *   Body: { "path": "media/audio/sloka/..." }
 *   Response: { "archived_as": "media/deleted/..." }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$docRoot    = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$mediaRoot  = $docRoot . '/media';
$deletedDir = $mediaRoot . '/deleted';
$slokaDir   = $mediaRoot . '/audio/sloka';

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Validate file is a real MP3 via magic bytes */
function is_valid_mp3(string $tmpPath): bool {
    $fh = fopen($tmpPath, 'rb');
    if (!$fh) return false;
    $header = fread($fh, 3);
    fclose($fh);
    if ($header === 'ID3') return true;
    $b = unpack('C3', $header);
    return $b[1] === 0xFF && ($b[2] & 0xE0) === 0xE0;
}

/**
 * Build the sloka audio filename from id + search_title.
 * Format: "{id} - {first 4 words of search_title}.mp3"
 */
function make_sloka_filename(int $id, string $searchTitle): string {
    $words = preg_split('/\s+/', trim($searchTitle), -1, PREG_SPLIT_NO_EMPTY);
    $words = array_slice($words, 0, 4);
    $label = $words ? implode(' ', $words) : 'sloka';
    // Strip characters that are unsafe in filenames
    $label = preg_replace('/[\/\\\\:*?"<>|]/', '', $label);
    $label = trim($label) ?: 'sloka';
    return "{$id} - {$label}.mp3";
}

// ── DELETE — soft-delete / archive ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $relPath = trim($body['path'] ?? '');

    if ($relPath === '') {
        http_response_code(400);
        echo json_encode(['message' => 'path is required']);
        exit;
    }
    if (!preg_match('#^media/audio/sloka/#', $relPath)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid path — must be under media/audio/sloka/']);
        exit;
    }

    $absPath = $docRoot . '/' . $relPath;
    if (!file_exists($absPath)) {
        echo json_encode(['message' => 'File not found, nothing to archive']);
        exit;
    }

    if (!is_dir($deletedDir)) mkdir($deletedDir, 0755, true);

    $basename   = pathinfo($relPath, PATHINFO_FILENAME);
    $ext        = pathinfo($relPath, PATHINFO_EXTENSION);
    $datetime   = date('Y-m-d_His');
    $archivedAs = 'media/deleted/' . $basename . '_' . $datetime . '.' . $ext;
    $absArchive = $docRoot . '/' . $archivedAs;

    if (!rename($absPath, $absArchive)) {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to archive file']);
        exit;
    }

    echo json_encode(['archived_as' => $archivedAs]);
    exit;
}

// ── POST — upload ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? -1;
    $msg = match($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds size limit (max 50MB)',
        UPLOAD_ERR_NO_FILE                         => 'No file uploaded',
        default                                    => 'Upload error code ' . $errCode,
    };
    http_response_code(400);
    echo json_encode(['message' => $msg]);
    exit;
}

$slokaId    = (int) ($_POST['sloka_id']     ?? 0);
$searchTitle = trim($_POST['search_title']  ?? '');

if ($slokaId <= 0) {
    http_response_code(400);
    echo json_encode(['message' => 'sloka_id is required']);
    exit;
}

// Validate file size (50MB)
if ($_FILES['file']['size'] > 50 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['message' => 'File exceeds 50MB limit']);
    exit;
}

$tmpPath  = $_FILES['file']['tmp_name'];
$mimeType = mime_content_type($tmpPath);
$validMimes = ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/x-mp3'];
if (!is_valid_mp3($tmpPath) && !in_array($mimeType, $validMimes, true)) {
    http_response_code(400);
    echo json_encode(['message' => "File rejected: not a valid MP3 (detected: $mimeType)"]);
    exit;
}

$filename = make_sloka_filename($slokaId, $searchTitle);
$relDest  = 'media/audio/sloka/' . $filename;
$absDest  = $docRoot . '/' . $relDest;

// Create directory if needed
if (!is_dir($slokaDir)) {
    if (!@mkdir($slokaDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['message' => 'Could not create media/audio/sloka/ — check server write permissions']);
        exit;
    }
}

// Archive existing file at this path
if (file_exists($absDest)) {
    if (!is_dir($deletedDir)) mkdir($deletedDir, 0755, true);
    $basename   = pathinfo($relDest, PATHINFO_FILENAME);
    $datetime   = date('Y-m-d_His');
    rename($absDest, $deletedDir . '/' . $basename . '_' . $datetime . '.mp3');
}

if (!move_uploaded_file($tmpPath, $absDest)) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to save file — check server write permissions on media/']);
    exit;
}
chmod($absDest, 0644);

echo json_encode(['path' => $relDest]);
