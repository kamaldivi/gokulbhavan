<?php
/**
 * POST/GET /api/admin/migrate-audio-paths.php
 *
 * One-time migration: reorganises media/ folder from legacy layout to
 * the clean media/audio/{family}/{category}/ hierarchy and updates all
 * DB path columns to match.
 *
 * ALWAYS run dry-run first (default, no ?apply param).
 * Pass ?apply=1 to execute moves + DB updates inside a transaction.
 *
 * Old → New mapping
 * -----------------
 *  media/audio-bhajans/{cat}/{file}              → media/audio/bhajan/{cat}/{file}
 *  media/slokas-audio/{cat}/{file}               → media/audio/sloka/{cat}/{file}
 *  media/audio-sankirtans/{letter}/{file}        → media/audio/sankirtan/{letter}/{file}
 *  media/albums/{GGN}/{file}                     → media/audio/album/{GGN}/{file}
 *  media/gokula-ganam/vol{N}/ready/{file}        → media/audio/album/GG{N}/{file}
 *  media/base-tracks/{file}                      → media/audio/base/{file}
 *  media/audio-bhajans-samples/{file}            → media/audio/versions/{file}
 *  media/audio-bhajans-samples-others/{file}     → media/audio/versions/{file}
 *  media/gokula-ganam/vol{N}/images/{file}       → media/images/album/GG{N}/{file}
 *
 * DB tables / columns updated
 * ---------------------------
 *  audio_track.audio_file_path
 *  audio_track.base_track_path
 *  audio_singer_version.audio_file_path
 *  audio_category.image_path
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$apply   = ($_GET['apply'] ?? '') === '1';
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

// ── Path rewrite function ─────────────────────────────────────────────────────
function rewrite_path(string $old): ?string
{
    // Strip leading slash if present
    $p = ltrim($old, '/');

    // bhajans
    if (preg_match('#^media/audio-bhajans/([^/]+)/(.+)$#', $p, $m)) {
        return "media/audio/bhajan/{$m[1]}/{$m[2]}";
    }
    // slokas
    if (preg_match('#^media/slokas-audio/([^/]+)/(.+)$#', $p, $m)) {
        return "media/audio/sloka/{$m[1]}/{$m[2]}";
    }
    // sankirtans
    if (preg_match('#^media/audio-sankirtans/([^/]+)/(.+)$#', $p, $m)) {
        return "media/audio/sankirtan/{$m[1]}/{$m[2]}";
    }
    // albums (direct albums/{GGN}/ layout)
    if (preg_match('#^media/albums/([^/]+)/(.+)$#', $p, $m)) {
        return "media/audio/album/{$m[1]}/{$m[2]}";
    }
    // gokula-ganam/vol{N}/images/ → media/images/album/GGN/
    if (preg_match('#^media/gokula-ganam/vol(\d+)/images/(.+)$#', $p, $m)) {
        return "media/images/album/GG{$m[1]}/{$m[2]}";
    }
    // gokula-ganam/vol{N}/ready/ (or bare vol{N}/) → media/audio/album/GGN/
    if (preg_match('#^media/gokula-ganam/vol(\d+)/(?:ready/)?(.+)$#', $p, $m)) {
        return "media/audio/album/GG{$m[1]}/{$m[2]}";
    }
    // base tracks (flat)
    if (preg_match('#^media/base-tracks/(.+)$#', $p, $m)) {
        return "media/audio/base/{$m[1]}";
    }
    // bhajan singer versions (GB singers)
    if (preg_match('#^media/audio-bhajans-samples/(.+)$#', $p, $m)) {
        return "media/audio/versions/{$m[1]}";
    }
    // bhajan singer versions (external singers)
    if (preg_match('#^media/audio-bhajans-samples-others/(.+)$#', $p, $m)) {
        return "media/audio/versions/{$m[1]}";
    }

    return null; // path not recognised — leave untouched
}

// ── Collect DB paths ─────────────────────────────────────────────────────────
try {
    $pdo = get_db();

    // audio_track: audio_file_path + base_track_path
    $trackRows = $pdo->query("
        SELECT track_id, audio_file_path, base_track_path
        FROM audio_track
        WHERE audio_file_path IS NOT NULL OR base_track_path IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);

    // audio_singer_version: audio_file_path
    $singerRows = $pdo->query("
        SELECT id, track_id, singer, audio_file_path
        FROM audio_singer_version
        WHERE audio_file_path IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);

    // audio_category: image_path (album CD covers)
    $categoryRows = $pdo->query("
        SELECT category_code, image_path
        FROM audio_category
        WHERE image_path IS NOT NULL AND image_path <> ''
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
    exit;
}

// ── Build change plan ────────────────────────────────────────────────────────
$changes = [];    // [{ table, pk, column, old_path, new_path, file_exists, conflict }]
$errors  = [];    // paths that couldn't be resolved

function plan(string $table, string $pk_col, $pk_val, string $col, ?string $old): ?array
{
    if ($old === null || $old === '') return null;
    $new = rewrite_path($old);
    if ($new === null) {
        return ['error' => "Unrecognised path: $old (table=$table pk=$pk_val col=$col)"];
    }
    if ($new === ltrim($old, '/')) return null; // already at new location
    return [
        'table'    => $table,
        'pk_col'   => $pk_col,
        'pk_val'   => $pk_val,
        'column'   => $col,
        'old_path' => $old,
        'new_path' => $new,
    ];
}

foreach ($trackRows as $r) {
    foreach (['audio_file_path', 'base_track_path'] as $col) {
        $result = plan('audio_track', 'track_id', $r['track_id'], $col, $r[$col]);
        if ($result) {
            if (isset($result['error'])) $errors[] = $result['error'];
            else $changes[] = $result;
        }
    }
}

foreach ($singerRows as $r) {
    $result = plan('audio_singer_version', 'id', $r['id'], 'audio_file_path', $r['audio_file_path']);
    if ($result) {
        if (isset($result['error'])) $errors[] = $result['error'];
        else $changes[] = $result;
    }
}

foreach ($categoryRows as $r) {
    $result = plan('audio_category', 'category_code', $r['category_code'], 'image_path', $r['image_path']);
    if ($result) {
        if (isset($result['error'])) $errors[] = $result['error'];
        else $changes[] = $result;
    }
}

// Annotate each change with filesystem status
foreach ($changes as &$c) {
    $oldAbs = $docRoot . '/' . $c['old_path'];
    $newAbs = $docRoot . '/' . $c['new_path'];
    $c['file_exists']       = file_exists($oldAbs);
    $c['dest_already_exists'] = file_exists($newAbs);
}
unset($c);

// Summary counts
$total      = count($changes);
$filesMoved = 0;
$dbUpdated  = 0;
$skipped    = 0;

// ── Dry-run: return plan and exit ────────────────────────────────────────────
if (!$apply) {
    $missing   = array_filter($changes, fn($c) => !$c['file_exists']);
    $conflicts = array_filter($changes, fn($c) => $c['dest_already_exists'] && $c['file_exists']);
    echo json_encode([
        'mode'     => 'dry-run',
        'summary'  => [
            'total_changes'   => $total,
            'errors'          => count($errors),
            'file_not_found'  => count($missing),
            'dest_conflict'   => count($conflicts),
        ],
        'errors'   => $errors,
        'changes'  => $changes,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Apply mode ───────────────────────────────────────────────────────────────
$log = [];

try {
    $pdo->beginTransaction();

    foreach ($changes as $c) {
        $oldAbs = $docRoot . '/' . $c['old_path'];
        $newAbs = $docRoot . '/' . $c['new_path'];

        // 1. Ensure destination directory exists
        $destDir = dirname($newAbs);
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                $log[] = ['status' => 'error', 'msg' => "mkdir failed: $destDir", 'change' => $c];
                $skipped++;
                continue;
            }
        }

        // 2. Move the file (atomic on same filesystem)
        if ($c['file_exists']) {
            if ($c['dest_already_exists']) {
                // Destination already has this file — skip move, still update DB
                $log[] = ['status' => 'skip_move', 'msg' => 'Dest exists, only updating DB', 'change' => $c];
            } else {
                if (!rename($oldAbs, $newAbs)) {
                    $log[] = ['status' => 'error', 'msg' => "rename() failed: $oldAbs → $newAbs", 'change' => $c];
                    $skipped++;
                    continue;
                }
                chmod($newAbs, 0644);
                $filesMoved++;
                $log[] = ['status' => 'moved', 'old' => $c['old_path'], 'new' => $c['new_path']];
            }
        } else {
            // File not on this server copy — still update DB path
            $log[] = ['status' => 'no_file', 'msg' => 'File not found locally, updating DB only', 'change' => $c];
        }

        // 3. Update DB
        $sql  = "UPDATE {$c['table']} SET {$c['column']} = :new WHERE {$c['pk_col']} = :pk";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':new' => $c['new_path'], ':pk' => $c['pk_val']]);
        $dbUpdated++;
    }

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'mode'  => 'apply',
        'error' => 'Rolled back: ' . $e->getMessage(),
        'log'   => $log,
    ]);
    exit;
}

echo json_encode([
    'mode'       => 'apply',
    'summary'    => [
        'total_changes' => $total,
        'files_moved'   => $filesMoved,
        'db_updated'    => $dbUpdated,
        'skipped'       => $skipped,
        'errors_pre'    => count($errors),
    ],
    'errors'     => $errors,
    'log'        => $log,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
