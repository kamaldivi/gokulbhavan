<?php
/**
 * GET    /api/admin/post-categories.php        — list all categories
 * GET    /api/admin/post-categories.php?post_type=blog|event — filtered list
 * POST   /api/admin/post-categories.php        — create category
 * PUT    /api/admin/post-categories.php?id=N   — update category
 * DELETE /api/admin/post-categories.php?id=N   — delete category (blocked if posts assigned)
 */

require __DIR__ . '/../_cors.php';
require __DIR__ . '/_auth.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = get_db();

    // ── GET ───────────────────────────────────────────────────────
    if ($method === 'GET') {
        $postType = isset($_GET['post_type']) ? trim($_GET['post_type']) : null;

        if ($postType !== null && in_array($postType, ['blog', 'event'], true)) {
            $stmt = $db->prepare(
                "SELECT id, name, slug, description, post_type, is_sequential, placement, sort_order, created_at
                 FROM post_category WHERE post_type = :pt
                 ORDER BY sort_order ASC, name ASC"
            );
            $stmt->execute([':pt' => $postType]);
        } else {
            $stmt = $db->query(
                "SELECT id, name, slug, description, post_type, is_sequential, placement, sort_order, created_at
                 FROM post_category ORDER BY post_type ASC, sort_order ASC, name ASC"
            );
        }

        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['id']            = (int) $r['id'];
            $r['is_sequential'] = (int) $r['is_sequential'];
            $r['sort_order']    = (int) $r['sort_order'];
        }
        unset($r);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);

    // ── POST ──────────────────────────────────────────────────────
    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($body['name'] ?? '');
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['message' => 'name is required']);
            exit;
        }

        $slug = trim($body['slug'] ?? '');
        if ($slug === '') {
            $slug = generate_cat_slug($name, $db);
        }

        $postType     = in_array($body['post_type'] ?? '', ['blog', 'event'], true) ? $body['post_type'] : 'blog';
        $isSequential = (int) ($body['is_sequential'] ?? 0);
        $placement    = ($body['placement'] ?? null) ?: null;
        $sortOrder    = (int) ($body['sort_order'] ?? 0);
        $description  = ($body['description'] ?? null) ?: null;

        $db->prepare(
            "INSERT INTO post_category (name, slug, description, post_type, is_sequential, placement, sort_order)
             VALUES (:n, :s, :d, :pt, :is, :pl, :so)"
        )->execute([
            ':n'  => $name,   ':s'  => $slug,    ':d'  => $description,
            ':pt' => $postType, ':is' => $isSequential,
            ':pl' => $placement, ':so' => $sortOrder,
        ]);

        $id = (int) $db->lastInsertId();
        http_response_code(201);
        echo json_encode(['id' => $id, 'slug' => $slug, 'message' => 'Created']);

    // ── PUT ───────────────────────────────────────────────────────
    } elseif ($method === 'PUT') {
        $id   = (int) ($_GET['id'] ?? 0);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!$id) {
            http_response_code(422);
            echo json_encode(['message' => 'id is required']);
            exit;
        }

        $name = trim($body['name'] ?? '');
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['message' => 'name is required']);
            exit;
        }

        $db->prepare(
            "UPDATE post_category
             SET name=:n, description=:d, post_type=:pt,
                 is_sequential=:is, placement=:pl, sort_order=:so
             WHERE id=:id"
        )->execute([
            ':n'  => $name,
            ':d'  => ($body['description'] ?? null) ?: null,
            ':pt' => in_array($body['post_type'] ?? '', ['blog', 'event'], true) ? $body['post_type'] : 'blog',
            ':is' => (int) ($body['is_sequential'] ?? 0),
            ':pl' => ($body['placement'] ?? null) ?: null,
            ':so' => (int) ($body['sort_order'] ?? 0),
            ':id' => $id,
        ]);
        echo json_encode(['message' => 'Updated']);

    // ── DELETE ────────────────────────────────────────────────────
    } elseif ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(422);
            echo json_encode(['message' => 'id is required']);
            exit;
        }

        $check = $db->prepare("SELECT COUNT(*) FROM post WHERE category_id = :id");
        $check->execute([':id' => $id]);
        if ((int) $check->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['message' => 'Cannot delete: category has posts assigned to it. Reassign them first.']);
            exit;
        }

        $db->prepare("DELETE FROM post_category WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(['message' => 'Deleted']);

    } else {
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('admin/post-categories.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Server error']);
}

function generate_cat_slug(string $name, PDO $db): string {
    $slug = mb_strtolower($name, 'UTF-8');
    $slug = preg_replace('/[^\w\s-]/u', '', $slug);
    $slug = preg_replace('/[\s_-]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') $slug = 'category-' . time();

    $base = $slug;
    $i    = 1;
    while (true) {
        $s = $db->prepare("SELECT id FROM post_category WHERE slug = :s");
        $s->execute([':s' => $slug]);
        if (!$s->fetch()) break;
        $slug = $base . '-' . $i++;
    }
    return $slug;
}
