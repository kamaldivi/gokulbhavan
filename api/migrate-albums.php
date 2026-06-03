<?php
/**
 * One-time migration: populate audio_track with Gokula Ganam album tracks.
 * DELETE THIS FILE after migration is complete.
 *
 * Usage:
 *   https://gokulbhavan.org/api/migrate-albums.php?token=A6CD0196A0274D9EC9AE1B430D5D89F8
 *   Add &dry_run=1 to preview without writing to DB.
 */

require __DIR__ . '/config.php';

set_time_limit(120);

// Token guard
if (($_GET['token'] ?? '') !== SYNC_TOKEN) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$dryRun    = !empty($_GET['dry_run']);
$mediaRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/media/gokula-ganam';
$mediaWeb  = 'media/gokula-ganam';

const TOTAL_VOLS = 10;

header('Content-Type: text/plain; charset=utf-8');

if ($dryRun) {
    echo "=== DRY RUN - no changes will be written ===\n\n";
}

if (!is_dir($mediaRoot)) {
    echo "ERROR: Directory not found: $mediaRoot\n";
    exit(1);
}

// Parse album filename into components.
// Handles two formats:
//   Standard:  {02d}-{CAT}-{02d}-{Title} [ - Singer]  e.g. 01-A-03-Guru Carana Kamala - Priya
//   Standalone: {02d}-{Title} [ - Singer]              e.g. 24-I Have Some Questions Gurudeva - Tejas
function parse_album_filename(string $filename): ?array {
    $name = basename($filename, '.mp3');

    // Standard format — has embedded bhajan_id
    if (preg_match('/^(\d{2})-([A-Z][A-Z0-9]*)-(\d{2})-(.+)$/', $name, $m)) {
        $track_num = (int) $m[1];
        $bhajan_id = "{$m[2]}-{$m[3]}";
        $rest      = $m[4];
        $sep       = strrpos($rest, ' - ');
        $title     = $sep !== false ? trim(substr($rest, 0, $sep)) : $rest;
        $singer    = $sep !== false ? trim(substr($rest, $sep + 3)) : null;
        return [$track_num, $bhajan_id, $title, $singer];
    }

    // Standalone format — no bhajan_id reference
    if (preg_match('/^(\d{2})-(.+)$/', $name, $m)) {
        $track_num = (int) $m[1];
        $rest      = $m[2];
        $sep       = strrpos($rest, ' - ');
        $title     = $sep !== false ? trim(substr($rest, 0, $sep)) : $rest;
        $singer    = $sep !== false ? trim(substr($rest, $sep + 3)) : null;
        return [$track_num, null, $title, $singer];
    }

    return null;
}

$pdo = get_db();

// Cache lyrics paths from audio_track to inherit into album tracks
$lyricsCache = [];
function get_lyrics_path(PDO $pdo, string $bhajan_id): ?string {
    global $lyricsCache;
    if (array_key_exists($bhajan_id, $lyricsCache)) {
        return $lyricsCache[$bhajan_id];
    }
    $stmt = $pdo->prepare('SELECT lyrics_file_path FROM audio_track WHERE track_id = ? LIMIT 1');
    $stmt->execute([$bhajan_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $lyricsCache[$bhajan_id] = $row ? $row['lyrics_file_path'] : null;
    return $lyricsCache[$bhajan_id];
}

$insertSql = '
    INSERT INTO audio_track
      (track_id, category_code, track_name, singer, track_num, audio_file_path, lyrics_file_path, base_track_path)
    VALUES
      (:track_id, :category_code, :track_name, :singer, :track_num, :audio_file_path, :lyrics_file_path, NULL)
';

$totalInserted = 0;
$totalSkipped  = 0;
$errors        = [];

// Optional: run a single volume via ?vol=N (for timeout-limited hosts)
$onlyVol = isset($_GET['vol']) ? (int)$_GET['vol'] : null;
$volStart = $onlyVol ?? 1;
$volEnd   = $onlyVol ?? TOTAL_VOLS;

for ($vol = $volStart; $vol <= $volEnd; $vol++) {
    $categoryCode = "GG$vol";
    $readyDir     = "$mediaRoot/vol$vol/ready";

    if (!is_dir($readyDir)) {
        echo "[vol $vol] SKIP - directory not found: $readyDir\n";
        $totalSkipped++;
        continue;
    }

    $files = glob("$readyDir/*.mp3") ?: [];
    if (empty($files)) {
        echo "[vol $vol] SKIP - no mp3 files in $readyDir\n";
        $totalSkipped++;
        continue;
    }

    sort($files);
    echo "[vol $vol] Found " . count($files) . " tracks\n";
    ob_flush(); flush();

    foreach ($files as $file) {
        $parsed = parse_album_filename(basename($file));
        if ($parsed === null) {
            $errors[] = "[vol $vol] Could not parse: " . basename($file);
            continue;
        }

        [$track_num, $bhajan_id, $title, $singer] = $parsed;
        $track_id         = sprintf('GG%d-%02d', $vol, $track_num);
        $audio_file_path  = "$mediaWeb/vol$vol/ready/" . basename($file);
        $lyrics_file_path = get_lyrics_path($pdo, $bhajan_id);

        if ($dryRun) {
            echo sprintf(
                "  %s | %-30s | singer: %-20s | lyrics: %s\n",
                $track_id,
                $title,
                $singer ?? '(none)',
                $lyrics_file_path ?? '(none)'
            );
        } else {
            try {
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute([
                    ':track_id'         => $track_id,
                    ':category_code'    => $categoryCode,
                    ':track_name'       => $title,
                    ':singer'           => $singer,
                    ':track_num'        => $track_num,
                    ':audio_file_path'  => $audio_file_path,
                    ':lyrics_file_path' => $lyrics_file_path,
                ]);
                echo "  Inserted $track_id - $title" . ($singer ? " ($singer)" : '') . "\n";
                $totalInserted++;
            } catch (PDOException $e) {
                $errors[] = "[vol $vol] FAILED $track_id: " . $e->getMessage();
            }
        }
    }
    echo "\n";
    ob_flush(); flush();
}

echo "=== Done ===\n";
if (!$dryRun) {
    echo "Inserted: $totalInserted\n";
    echo "Skipped volumes: $totalSkipped\n";
}

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $e) {
        echo "  $e\n";
    }
}
