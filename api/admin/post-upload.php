<?php
/**
 * POST /api/admin/post-upload.php — upload a post image
 *
 * Accepts multipart/form-data:
 *   file     — image file (JPEG / WebP preferred; PNG accepted; max 5 MB)
 *   post_id  — post ID (required)
 *   slot     — "cover" | "media"  (default: "media")
 *   slug     — post slug (optional; used to name cover file as {slug}.{ext})
 *
 * Cover image rules (slot=cover):
 *   - Must be landscape (width > height)
 *   - Minimum 800 × 400 px
 *   - Recommended: 1200 × 630 px (standard social/OG size)
 *   - Saved as media/posts/{post_id}/{slug}.{ext}  (or cover.{ext} if no slug)
 *
 * Body image (slot=media):
 *   - No dimension restrictions
 *   - Saved as media/posts/{post_id}/img-{time}-{random}.{ext}
 *
 * Response: { "path": "media/posts/42/my-slug.jpg" }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

// ── Validate post_id ──────────────────────────────────────────
$postId = (int) ($_POST['post_id'] ?? 0);
if ($postId < 1) {
    http_response_code(400);
    echo json_encode(['message' => 'post_id is required and must be a positive integer']);
    exit;
}

// ── Validate upload presence ──────────────────────────────────
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

if ($_FILES['file']['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['message' => 'Image exceeds 5 MB limit']);
    exit;
}

// ── Validate MIME type ────────────────────────────────────────
$tmpPath     = $_FILES['file']['tmp_name'];
$mime        = mime_content_type($tmpPath);
$VALID_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
$EXTENSIONS  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

if (!in_array($mime, $VALID_MIMES, true)) {
    http_response_code(400);
    echo json_encode(['message' => "Invalid image type ($mime). Cover images must be JPEG, WebP, or PNG."]);
    exit;
}

$ext  = $EXTENSIONS[$mime];
$slot = trim($_POST['slot'] ?? 'media');

// ── Cover image: dimension + orientation check ────────────────
if ($slot === 'cover') {
    $size = getimagesize($tmpPath);
    if ($size === false) {
        http_response_code(400);
        echo json_encode(['message' => 'Could not read image dimensions.']);
        exit;
    }
    [$imgW, $imgH] = $size;

    if ($imgW <= $imgH) {
        http_response_code(400);
        echo json_encode([
            'message' => "Cover image must be landscape (width > height). "
                       . "Your image is {$imgW}×{$imgH}px. "
                       . "Recommended: 1200×630 px.",
        ]);
        exit;
    }
    if ($imgW < 800 || $imgH < 400) {
        http_response_code(400);
        echo json_encode([
            'message' => "Cover image is too small ({$imgW}×{$imgH}px). "
                       . "Minimum: 800×400 px. Recommended: 1200×630 px.",
        ]);
        exit;
    }
}

// ── Build filename ────────────────────────────────────────────
if ($slot === 'cover') {
    $rawSlug  = trim($_POST['slug'] ?? '');
    // Sanitise slug to safe filename characters
    $safeSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower($rawSlug));
    $safeSlug = trim($safeSlug, '-');
    $filename = ($safeSlug !== '' ? $safeSlug : 'cover') . ".$ext";
} else {
    $filename = 'img-' . time() . '-' . bin2hex(random_bytes(4)) . ".$ext";
}

// ── Build destination path ────────────────────────────────────
$relDir  = "media/posts/$postId";
$absDir  = "$docRoot/$relDir";
$relDest = "$relDir/$filename";
$absDest = "$docRoot/$relDest";

if (!is_dir($absDir) && !@mkdir($absDir, 0755, true)) {
    http_response_code(500);
    echo json_encode(['message' => "Could not create directory $relDir. Check server write permissions on media/."]);
    exit;
}

if (!move_uploaded_file($tmpPath, $absDest)) {
    http_response_code(500);
    echo json_encode(['message' => "Failed to save image to $relDest"]);
    exit;
}

chmod($absDest, 0644);
echo json_encode(['path' => $relDest]);
