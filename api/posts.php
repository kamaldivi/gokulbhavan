<?php
/**
 * GET /api/posts.php — public read-only posts endpoint
 *
 * Query parameters:
 *   slug=my-post             — single post by slug; returns post + media + category info
 *   type=blog|event          — filter by post_type (listing mode)
 *   category_slug=bk-blogs   — filter by post_category.slug
 *   cat_placement=home|tamil|programs — filter events by their category's placement
 *   upcoming=1               — events only: event_date >= today, sorted ASC
 *   page=N                   — page number (default 1)
 *   per_page=N               — items per page (default 12, max 50)
 *
 * Listing response:
 *   { posts: [...], total, page, per_page, pages }
 *   Each post: id, post_type, category_id, episode_number, slug, title, extract,
 *              cover_image_path, status, published_at, event_date, event_end_date,
 *              event_location, created_at
 *
 * Single-post response (adds):
 *   body, updated_at, category_name, category_slug, category_is_sequential,
 *   media: [{ id, media_type, media_ref, caption, sort_order }]
 */

require __DIR__ . '/_cors.php';
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

try {
    $db   = get_db();
    $slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;

    // ── Single post by slug ──────────────────────────────────────
    if ($slug !== null) {
        $stmt = $db->prepare(
            "SELECT p.id, p.post_type, p.category_id, p.episode_number,
                    p.slug, p.title, p.extract, p.body, p.cover_image_path,
                    p.status, p.published_at, p.event_date, p.event_end_date, p.event_location,
                    p.created_at, p.updated_at,
                    c.name  AS category_name,
                    c.slug  AS category_slug,
                    c.is_sequential AS category_is_sequential
             FROM post p
             LEFT JOIN post_category c ON c.id = p.category_id
             WHERE p.slug = :slug AND p.status = 'published'"
        );
        $stmt->execute([':slug' => $slug]);
        $post = $stmt->fetch();

        if (!$post) {
            http_response_code(404);
            echo json_encode(['message' => 'Post not found']);
            exit;
        }

        $post['id']                   = (int) $post['id'];
        $post['category_id']          = $post['category_id'] ? (int) $post['category_id'] : null;
        $post['episode_number']       = $post['episode_number'] ? (int) $post['episode_number'] : null;
        $post['category_is_sequential'] = (int) ($post['category_is_sequential'] ?? 0);

        $mstmt = $db->prepare(
            "SELECT id, media_type, media_ref, caption, sort_order
             FROM post_media
             WHERE post_id = :post_id
             ORDER BY sort_order ASC, id ASC"
        );
        $mstmt->execute([':post_id' => $post['id']]);
        $media = $mstmt->fetchAll();
        foreach ($media as &$m) {
            $m['id']         = (int) $m['id'];
            $m['sort_order'] = (int) $m['sort_order'];
        }
        unset($m);

        $post['media'] = $media;
        echo json_encode($post, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Listing ───────────────────────────────────────────────────
    $type         = isset($_GET['type'])          ? trim($_GET['type'])          : null;
    $categorySlug = isset($_GET['category_slug']) ? trim($_GET['category_slug']) : null;
    $catPlacement = isset($_GET['cat_placement']) ? trim($_GET['cat_placement']) : null;
    $upcoming     = !empty($_GET['upcoming']);
    $page         = max(1, (int) ($_GET['page']     ?? 1));
    $perPage      = min(200, max(1, (int) ($_GET['per_page'] ?? 12)));
    $offset       = ($page - 1) * $perPage;

    $where  = ["p.status = 'published'"];
    $params = [];
    $join   = "LEFT JOIN post_category c ON c.id = p.category_id";

    if ($type !== null && in_array($type, ['blog', 'event'], true)) {
        $where[]         = "p.post_type = :type";
        $params[':type'] = $type;
    }

    if ($categorySlug !== null) {
        $where[]                 = "c.slug = :cat_slug";
        $params[':cat_slug']     = $categorySlug;
    }

    if ($catPlacement !== null && in_array($catPlacement, ['home', 'tamil', 'programs'], true)) {
        $where[]                   = "c.placement = :cat_placement";
        $params[':cat_placement']  = $catPlacement;
    }

    if ($upcoming) {
        $where[] = "p.event_date >= CURDATE()";
        $orderBy = "p.event_date ASC";
    } elseif ($categorySlug !== null) {
        // For a specific category: sequential ones sort by episode, others by date
        $orderBy = "CASE WHEN c.is_sequential = 1 THEN p.episode_number ELSE NULL END ASC, p.published_at DESC";
    } else {
        $orderBy = "p.published_at DESC";
    }

    $whereSql = implode(' AND ', $where);

    $cstmt = $db->prepare("SELECT COUNT(*) FROM post p $join WHERE $whereSql");
    $cstmt->execute($params);
    $total = (int) $cstmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT p.id, p.post_type, p.category_id, p.episode_number,
                p.slug, p.title, p.`extract`, p.cover_image_path,
                p.status, p.published_at, p.event_date, p.event_end_date, p.event_location,
                p.created_at
         FROM post p $join
         WHERE $whereSql
         ORDER BY $orderBy
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['id']            = (int) $r['id'];
        $r['category_id']   = $r['category_id'] ? (int) $r['category_id'] : null;
        $r['episode_number']= $r['episode_number'] ? (int) $r['episode_number'] : null;
    }
    unset($r);

    echo json_encode([
        'posts'    => $rows,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => (int) ceil($total / $perPage),
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    error_log('posts.php PDO error: ' . $e->getMessage());
    echo json_encode(['message' => 'Server error. Please try again later.']);
}
