<?php
/**
 * Admin API — MP3 file upload and soft-delete
 *
 * POST /api/admin/audio-upload.php
 *   Upload an MP3 file. Returns the saved relative path.
 *
 *   Multipart form fields:
 *     file        — the MP3 file (required)
 *     track_id    — e.g. "A-65"           (required)
 *     track_name  — e.g. "New Bhajan"     (required)
 *     upload_type — "main" | "base" | "singer_version"  (required)
 *     singer      — singer name (required when upload_type = singer_version)
 *     family      — "bhajan" | "sloka" | "sankirtan"    (required for main)
 *     category    — category_code e.g. "A"              (required for main)
 *
 *   Response: { "path": "media/audio/bhajan/A/A-65-New Bhajan.mp3" }
 *
 * DELETE /api/admin/audio-upload.php
 *   Soft-delete: move file to media/deleted/ with datetime suffix.
 *   Body: { "path": "media/audio/bhajan/A/A-65-New Bhajan.mp3" }
 *   Response: { "archived_as": "media/deleted/A-65-New Bhajan_2026-06-07_143022.mp3" }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$docRoot    = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$mediaRoot  = $docRoot . '/media';
$deletedDir = $mediaRoot . '/deleted';

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Validate file is a real MP3 via magic bytes */
function is_valid_mp3(string $tmpPath): bool {
    $fh = fopen($tmpPath, 'rb');
    if (!$fh) return false;
    $header = fread($fh, 3);
    fclose($fh);
    // ID3 tag: "ID3"
    if ($header === 'ID3') return true;
    // Raw MPEG frame sync: first 11 bits set (FF E0 or higher in byte 2)
    $b = unpack('C3', $header);
    return $b[1] === 0xFF && ($b[2] & 0xE0) === 0xE0;
}

/** Sanitise a string for use in a filename */
function safe_filename(string $s): string {
    // Remove characters that are unsafe in filenames
    $s = preg_replace('/[\/\\\\:*?"<>|]/', '', $s);
    return trim($s);
}

/** Build the destination path from DB-derived components (never from raw user input) */
function build_dest_path(string $uploadType, string $family, string $category,
                          string $trackId, string $trackName, string $singer): string {
    $safeId   = safe_filename($trackId);
    $safeName = safe_filename($trackName);
    $mediaDir = 'media';

    switch ($uploadType) {
        case 'main':
            return "$mediaDir/audio/$family/$category/$safeId-$safeName.mp3";
        case 'base':
            return "$mediaDir/audio/base/$safeId-$safeName - Base.mp3";
        case 'singer_version':
            $safeSinger = safe_filename($singer);
            return "$mediaDir/audio/versions/$safeId-$safeName - $safeSinger.mp3";
    }
    return '';
}

// ── DELETE — soft-delete / archive ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $relPath  = trim($body['path'] ?? '');

    if ($relPath === '') {
        http_response_code(400);
        echo json_encode(['message' => 'path required']);
        exit;
    }

    // Security: path must start with media/audio/ or media/images/
    if (!preg_match('#^media/(audio|images)/#', $relPath)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid path']);
        exit;
    }

    $absPath = $docRoot . '/' . $relPath;
    if (!file_exists($absPath)) {
        // Already gone — not an error
        echo json_encode(['message' => 'File not found, nothing to archive']);
        exit;
    }

    // Ensure deleted/ dir exists
    if (!is_dir($deletedDir)) {
        mkdir($deletedDir, 0755, true);
    }

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

// Validate presence of uploaded file
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? -1;
    $msg = match($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds size limit (max 50MB)',
        UPLOAD_ERR_NO_FILE  => 'No file uploaded',
        default             => 'Upload error code ' . $errCode,
    };
    http_response_code(400);
    echo json_encode(['message' => $msg]);
    exit;
}

// Collect and validate form fields
$uploadType = trim($_POST['upload_type'] ?? '');
$trackId    = strtoupper(trim($_POST['track_id']   ?? ''));
$trackName  = trim($_POST['track_name']             ?? '');
$family     = strtolower(trim($_POST['family']      ?? ''));
$category   = strtoupper(trim($_POST['category']    ?? ''));
$singer     = trim($_POST['singer']                 ?? '');

$validTypes   = ['main', 'base', 'singer_version'];
$validFamilies = ['bhajan', 'sloka', 'sankirtan'];

if (!in_array($uploadType, $validTypes, true)) {
    http_response_code(400);
    echo json_encode(['message' => 'upload_type must be main, base, or singer_version']);
    exit;
}
if ($trackId === '' || $trackName === '') {
    http_response_code(400);
    echo json_encode(['message' => 'track_id and track_name are required']);
    exit;
}
if ($uploadType === 'main' && (!in_array($family, $validFamilies, true) || $category === '')) {
    http_response_code(400);
    echo json_encode(['message' => 'family and category are required for main upload']);
    exit;
}
if ($uploadType === 'singer_version' && $singer === '') {
    http_response_code(400);
    echo json_encode(['message' => 'singer is required for singer_version upload']);
    exit;
}

// Validate file size (50MB)
if ($_FILES['file']['size'] > 50 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['message' => 'File exceeds 50MB limit']);
    exit;
}

// Validate via magic bytes first (most reliable), fall back to MIME check
$tmpPath  = $_FILES['file']['tmp_name'];
$mimeType = mime_content_type($tmpPath);
$validMimes = ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/x-mp3'];
if (!is_valid_mp3($tmpPath) && !in_array($mimeType, $validMimes, true)) {
    http_response_code(400);
    echo json_encode(['message' => "File rejected: not a valid MP3 (detected type: $mimeType). Please upload an MP3 file."]);
    exit;
}

// Build destination path entirely from server-side data
$relDest = build_dest_path($uploadType, $family, $category, $trackId, $trackName, $singer);
if ($relDest === '') {
    http_response_code(500);
    echo json_encode(['message' => 'Could not determine destination path']);
    exit;
}

$absDest = $docRoot . '/' . $relDest;
$destDir = dirname($absDest);

// Create destination directory if needed
if (!is_dir($destDir)) {
    if (!@mkdir($destDir, 0755, true)) {
        $relDir = str_replace($docRoot . '/', '', $destDir);
        http_response_code(500);
        echo json_encode([
            'message' => "Upload failed: could not create directory \"$relDir\". "
                       . "Ensure the media/ folder is writable by the web server, "
                       . "or create the directory manually on the server.",
        ]);
        exit;
    }
}

// If a file already exists at this path, archive it first
if (file_exists($absDest)) {
    if (!is_dir($deletedDir)) mkdir($deletedDir, 0755, true);
    $basename   = pathinfo($relDest, PATHINFO_FILENAME);
    $ext        = pathinfo($relDest, PATHINFO_EXTENSION);
    $datetime   = date('Y-m-d_His');
    $archiveDest = $deletedDir . '/' . $basename . '_' . $datetime . '.' . $ext;
    rename($absDest, $archiveDest);
}

// Move uploaded file atomically
if (!move_uploaded_file($tmpPath, $absDest)) {
    http_response_code(500);
    echo json_encode(['message' => "Failed to save uploaded file to \"$relDest\". Check server write permissions on the media/ folder."]);
    exit;
}
chmod($absDest, 0644);

echo json_encode(['path' => $relDest]);
