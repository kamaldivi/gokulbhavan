<?php
/**
 * GET    /api/admin/posts.php        — list all posts (all statuses, no body)
 * GET    /api/admin/posts.php?id=N   — single post with media items
 * POST   /api/admin/posts.php        — create post + media
 * PUT    /api/admin/posts.php?id=N   — update post + replace all media rows
 * DELETE /api/admin/posts.php?id=N   — delete post (cascades to post_media)
 *
 * POST / PUT body (JSON):
 * {
 *   "post_type":        "blog" | "event",
 *   "title":            "string",
 *   "slug":             "string"         (auto-generated from title if omitted on create),
 *   "extract":          "string" | null,
 *   "body":             "string" | null,  — Quill HTML output
 *   "cover_image_path": "string" | null,  — relative path e.g. media/posts/42/cover.jpg
 *   "status":           "draft" | "published" | "archived",
 *   "event_date":       "YYYY-MM-DD" | null,
 *   "event_end_date":   "YYYY-MM-DD" | null,
 *   "event_location":   "string" | null,
 *   "event_placement":  "home,tamil" | null,  — comma-separated SET values for event widgets
 *   "media": [
 *     { "media_type": "youtube",   "media_ref": "dQw4w9WgXcQ", "caption": "...", "sort_order": 0 },
 *     { "media_type": "playlist",  "media_ref": "PLxxxxxx",    "caption": "...", "sort_order": 1 },
 *     { "media_type": "image",     "media_ref": "media/posts/42/img-abc.jpg", "caption": "", "sort_order": 2 },
 *     { "media_type": "harikatha", "media_ref": "https://...", "caption": "...", "sort_order": 3 },
 *     { "media_type": "link",      "media_ref": "https://...", "caption": "...", "sort_order": 4 }
 *   ]
 * }
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

const POST_FIELDS = [
    'post_type', 'slug', 'title', 'extract', 'body',
    'cover_image_path', 'status', 'published_at',
    'category_id', 'episode_number',
    'event_date', 'event_end_date', 'event_location',
];

const VALID_MEDIA_TYPES = ['image', 'youtube', 'playlist', 'harikatha', 'link'];

/**
 * Generate a URL-safe slug from a title, ensuring uniqueness in the DB.
 * Optionally exclude $excludeId (current post) when checking uniqueness.
 */
function generate_slug(string $title, PDO $pdo, int $excludeId = 0): string {
    $slug = mb_strtolower($title, 'UTF-8');
    $slug = preg_replace('/[^\w\s-]/u', '', $slug);
    $slug = preg_replace('/[\s_-]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 200);
    if ($slug === '') $slug = 'post';

    $base = $slug;
    $n    = 0;
    while (true) {
        $candidate = $n === 0 ? $base : "$base-$n";
        $sql  = $excludeId
            ? "SELECT COUNT(*) FROM post WHERE slug = :slug AND id != :id"
            : "SELECT COUNT(*) FROM post WHERE slug = :slug";
        $stmt = $pdo->prepare($sql);
        $p    = [':slug' => $candidate];
        if ($excludeId) $p[':id'] = $excludeId;
        $stmt->execute($p);
        if ((int) $stmt->fetchColumn() === 0) return $candidate;
        $n++;
    }
}

/** Delete and re-insert all media rows for a post. */
function save_media(PDO $pdo, int $postId, array $items): void {
    $pdo->prepare("DELETE FROM post_media WHERE post_id = :id")
        ->execute([':id' => $postId]);

    $order = 0;
    foreach ($items as $item) {
        $type = trim($item['media_type'] ?? '');
        $ref  = trim($item['media_ref']  ?? '');
        if (!in_array($type, VALID_MEDIA_TYPES, true) || $ref === '') continue;

        $pdo->prepare(
            "INSERT INTO post_media (post_id, media_type, media_ref, caption, sort_order)
             VALUES (:post_id, :media_type, :media_ref, :caption, :sort_order)"
        )->execute([
            ':post_id'    => $postId,
            ':media_type' => $type,
            ':media_ref'  => $ref,
            ':caption'    => trim($item['caption'] ?? '') ?: null,
            ':sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : $order,
        ]);
        $order++;
    }
}

try {
    $pdo    = get_db();
    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET ───────────────────────────────────────────────────────
    if ($method === 'GET') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;

        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM post WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $post = $stmt->fetch();

            if (!$post) {
                http_response_code(404);
                echo json_encode(['message' => 'Not found']);
                exit;
            }
            $post['id'] = (int) $post['id'];

            $mstmt = $pdo->prepare(
                "SELECT id, media_type, media_ref, caption, sort_order
                 FROM post_media WHERE post_id = :id ORDER BY sort_order ASC, id ASC"
            );
            $mstmt->execute([':id' => $id]);
            $media = $mstmt->fetchAll();
            foreach ($media as &$m) {
                $m['id']         = (int) $m['id'];
                $m['sort_order'] = (int) $m['sort_order'];
            }
            unset($m);
            $post['media'] = $media;

            echo json_encode($post, JSON_UNESCAPED_UNICODE);

        } else {
            $rows = $pdo->query(
                "SELECT p.id, p.post_type, p.category_id, p.episode_number,
                        p.slug, p.title, p.status, p.published_at,
                        p.event_date, p.event_location, p.created_at, p.updated_at,
                        c.name AS category_name, c.slug AS category_slug
                 FROM post p
                 LEFT JOIN post_category c ON c.id = p.category_id
                 ORDER BY COALESCE(p.published_at, p.created_at) DESC"
            )->fetchAll();

            foreach ($rows as &$r) {
                $r['id']             = (int) $r['id'];
                $r['category_id']    = $r['category_id'] ? (int) $r['category_id'] : null;
                $r['episode_number'] = $r['episode_number'] ? (int) $r['episode_number'] : null;
            }
            unset($r);

            echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        }

    // ── POST ──────────────────────────────────────────────────────
    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $title    = trim($body['title']     ?? '');
        $postType = trim($body['post_type'] ?? 'blog');

        if ($title === '') {
            http_response_code(422);
            echo json_encode(['message' => 'title is required']);
            exit;
        }
        if (!in_array($postType, ['blog', 'event'], true)) {
            http_response_code(422);
            echo json_encode(['message' => 'post_type must be blog or event']);
            exit;
        }

        $slug = trim($body['slug'] ?? '');
        if ($slug === '') {
            $slug = generate_slug($title, $pdo);
        }

        $cols   = ['post_type', 'slug', 'title'];
        $vals   = [':post_type', ':slug', ':title'];
        $params = [':post_type' => $postType, ':slug' => $slug, ':title' => $title];

        foreach (POST_FIELDS as $f) {
            if (in_array($f, ['post_type', 'slug', 'title'], true)) continue;
            if (!array_key_exists($f, $body)) continue;
            $cols[]        = "`$f`";
            $vals[]        = ":$f";
            $v             = $body[$f];
            $params[":$f"] = ($v === '' || $v === null) ? null : trim((string) $v);
        }

        // Auto-set published_at on first publish
        $status = trim($body['status'] ?? 'draft');
        if ($status === 'published' && !array_key_exists('published_at', $body)) {
            $cols[]              = 'published_at';
            $vals[]              = ':published_at';
            $params[':published_at'] = date('Y-m-d H:i:s');
        }

        $pdo->prepare(
            "INSERT INTO post (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")"
        )->execute($params);

        $postId = (int) $pdo->lastInsertId();

        if (!empty($body['media']) && is_array($body['media'])) {
            save_media($pdo, $postId, $body['media']);
        }

        http_response_code(201);
        echo json_encode(['id' => $postId, 'slug' => $slug, 'message' => 'Created']);

    // ── PUT ───────────────────────────────────────────────────────
    } elseif ($method === 'PUT') {
        $id   = (int) ($_GET['id'] ?? 0);
        $body = json_decode(file_get_contents('php://input'), true);

        if (!$id || !is_array($body)) {
            http_response_code(422);
            echo json_encode(['message' => 'Invalid request']);
            exit;
        }

        $sets   = [];
        $params = [':id' => $id];

        foreach (POST_FIELDS as $f) {
            if (!array_key_exists($f, $body)) continue;
            $sets[]        = "`$f` = :$f";
            $v             = $body[$f];
            $params[":$f"] = ($v === '' || $v === null) ? null : trim((string) $v);
        }

        // Auto-set published_at when first publishing
        if (isset($body['status']) && $body['status'] === 'published'
            && !array_key_exists('published_at', $body)) {
            $check = $pdo->prepare("SELECT published_at FROM post WHERE id = :id");
            $check->execute([':id' => $id]);
            if (!$check->fetchColumn()) {
                $sets[]              = 'published_at = :published_at';
                $params[':published_at'] = date('Y-m-d H:i:s');
            }
        }

        if (!empty($sets)) {
            $pdo->prepare("UPDATE post SET " . implode(', ', $sets) . " WHERE id = :id")
                ->execute($params);
        }

        if (array_key_exists('media', $body) && is_array($body['media'])) {
            save_media($pdo, $id, $body['media']);
        }

        echo json_encode(['message' => 'Updated']);

    // ── DELETE ────────────────────────────────────────────────────
    } elseif ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(422);
            echo json_encode(['message' => 'Missing id']);
            exit;
        }
        $pdo->prepare("DELETE FROM post WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(['message' => 'Deleted']);

    } else {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('admin/posts.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
}
