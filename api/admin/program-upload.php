<?php
/**
 * Admin API — Program cover image upload and soft-delete
 *
 * POST /api/admin/program-upload.php
 *   Upload a cover image for a program.
 *   Multipart form fields:
 *     file        — image file (JPEG / WebP / PNG, max 5 MB)
 *     program_id  — program ID (required, positive integer)
 *
 *   Cover image rules:
 *     - Must be landscape (width > height)
 *     - Minimum: 800 × 400 px
 *     - Recommended: 1200 × 630 px (standard OG/social size)
 *     - Saved as: media/programs/{program_id}/cover.{ext}
 *     - If a cover already exists it is archived first (soft-delete)
 *
 *   Response: { "path": "media/programs/5/cover.jpg" }
 *
 * DELETE /api/admin/program-upload.php
 *   Soft-delete: move file to media/deleted/ with datetime suffix.
 *   Body: { "path": "media/programs/5/cover.jpg" }
 *   Response: { "archived_as": "media/deleted/cover_2026-06-18_120000.jpg" }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$docRoot    = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$deletedDir = $docRoot . '/media/deleted';

// ── DELETE — soft-delete / archive ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $relPath = trim($body['path'] ?? '');

    if ($relPath === '') {
        http_response_code(400);
        echo json_encode(['message' => 'path required']);
        exit;
    }

    if (!preg_match('#^media/programs/#', $relPath)) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid path']);
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

// Validate program_id
$programId = (int) ($_POST['program_id'] ?? 0);
if ($programId < 1) {
    http_response_code(400);
    echo json_encode(['message' => 'program_id is required and must be a positive integer']);
    exit;
}

// Validate file presence
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? -1;
    $msg = match ($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds size limit (max 5 MB)',
        UPLOAD_ERR_NO_FILE  => 'No file uploaded',
        default             => 'Upload error (code ' . $errCode . ')',
    };
    http_response_code(400);
    echo json_encode(['message' => $msg]);
    exit;
}

if ($_FILES['file']['size'] > 600 * 1024) {
    http_response_code(400);
    echo json_encode(['message' => 'Image exceeds 600 KB limit. Compress the file and try again.']);
    exit;
}

// Validate MIME type — JPEG only (WhatsApp OG requirement)
$tmpPath     = $_FILES['file']['tmp_name'];
$mime        = mime_content_type($tmpPath);

if ($mime !== 'image/jpeg') {
    http_response_code(400);
    echo json_encode(['message' => "Only JPEG images are accepted ($mime supplied). Convert to JPEG and try again."]);
    exit;
}

$ext = 'jpg';

// Dimension check — WhatsApp OG minimum 1200×630
$size = getimagesize($tmpPath);
if ($size === false) {
    http_response_code(400);
    echo json_encode(['message' => 'Could not read image dimensions.']);
    exit;
}
[$imgW, $imgH] = $size;

if ($imgW < 1200 || $imgH < 630) {
    http_response_code(400);
    echo json_encode([
        'message' => "Image is too small ({$imgW}×{$imgH}px). "
                   . "WhatsApp OG requires at least 1200×630 px.",
    ]);
    exit;
}

// Build destination path
$relDir  = "media/programs/$programId";
$absDir  = "$docRoot/$relDir";
$relDest = "$relDir/cover.$ext";
$absDest = "$docRoot/$relDest";

if (!is_dir($absDir) && !@mkdir($absDir, 0755, true)) {
    http_response_code(500);
    echo json_encode(['message' => "Could not create directory $relDir. Check server write permissions on media/."]);
    exit;
}

// Archive existing cover if present
if (file_exists($absDest)) {
    if (!is_dir($deletedDir)) mkdir($deletedDir, 0755, true);
    $datetime   = date('Y-m-d_His');
    $archiveDest = $deletedDir . "/cover_prog{$programId}_$datetime.$ext";
    rename($absDest, $archiveDest);
}

if (!move_uploaded_file($tmpPath, $absDest)) {
    http_response_code(500);
    echo json_encode(['message' => "Failed to save image to $relDest"]);
    exit;
}

chmod($absDest, 0644);
echo json_encode(['path' => $relDest]);
