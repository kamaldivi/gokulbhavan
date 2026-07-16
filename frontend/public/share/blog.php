<?php
/**
 * blog.php — Blog post share page with WhatsApp link preview
 * URL: gokulbhavan.org/share/blog/12
 *
 * OG image priority:
 *   1. post.cover_image_path  (if stored in DB)
 *   2. /assets/share/og-blog.jpg  (global fallback)
 *
 * To add per-category fallback images later, add a post_category table
 * with an og_image_path column and extend the OG image logic below.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../api/config.php';

// ── Input validation ──────────────────────────────────────────────────────────
$id         = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$siteBase   = 'https://gokulbhavan.org';

// ── Fetch post ────────────────────────────────────────────────────────────────
$post       = null;
$fetchError = false;

if ($id > 0) {
    try {
        $db   = get_db();
        $stmt = $db->prepare("
            SELECT id, slug, title, extract, body, cover_image_path, published_at
            FROM   post
            WHERE  id          = :id
              AND  post_type   = 'blog'
              AND  status      = 'published'
            LIMIT  1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $post = $row;
    } catch (Throwable $e) {
        $fetchError = true;
    }
}

// ── Helper: build absolute URL from any path ──────────────────────────────────
function absoluteUrl(string $path, string $base): string {
    if ($path === '') return '';
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
    return $base . '/' . ltrim($path, '/');
}

// ── OG values ─────────────────────────────────────────────────────────────────
$ogTitle = $post
    ? htmlspecialchars($post['title'] . ' — Gokul Bhavan', ENT_QUOTES)
    : 'Blog — Gokul Bhavan';

$rawDesc = $post && $post['extract']
    ? mb_strimwidth(strip_tags($post['extract']), 0, 200, '…')
    : 'Read this post on gokulbhavan.org';
$ogDesc  = htmlspecialchars($rawDesc, ENT_QUOTES);

$ogImage = ($post && $post['cover_image_path'])
    ? absoluteUrl($post['cover_image_path'], $siteBase)
    : $siteBase . '/assets/share/og-blog.jpg';

$ogUrl = $id ? $siteBase . '/share/blog.php?id=' . $id : $siteBase . '/blogs';

// ── Formatted publish date ────────────────────────────────────────────────────
$publishedLabel = '';
if ($post && $post['published_at']) {
    try {
        $publishedLabel = (new DateTime($post['published_at']))->format('F j, Y');
    } catch (Exception $e) { }
}

// ── Page-specific CSS ─────────────────────────────────────────────────────────
$extraCss = <<<'CSS'
    /* Cover image — full bleed inside card */
    .cover-wrap {
      width: 100%;
      aspect-ratio: 16 / 9;
      overflow: hidden;
      background: #EADDB7;
    }
    .cover-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    /* Blog content */
    .blog-content { padding: 24px 24px 20px; border-bottom: 1px solid #EADDB7; }
    .blog-date    { font-size: 11px; color: #2A506A; margin-bottom: 10px; letter-spacing: 0.04em; }
    .blog-title   {
      font-family: 'Manrope', sans-serif; font-weight: 800;
      font-size: clamp(20px, 5.5vw, 28px); line-height: 1.2;
      color: #082A4A; margin-bottom: 12px;
    }
    .blog-extract { font-size: 14px; line-height: 1.7; color: #2A506A;
                    border-left: 4px solid #C94277; padding-left: 12px;
                    margin-bottom: 16px; font-style: italic; }
    /* Body prose */
    .blog-body { font-size: 14px; line-height: 1.8; color: #2A506A; }
    .blog-body p  { margin-bottom: 10px; }
    /* Collapse empty paragraphs that Quill inserts as spacers */
    .blog-body p:empty,
    .blog-body p:has(> br:only-child) { display: none; }
    .blog-body h2 { font-size: 17px; font-weight: 700; color: #082A4A; margin: 20px 0 8px; }
    .blog-body h3 { font-size: 15px; font-weight: 600; color: #082A4A; margin: 16px 0 6px; }
    .blog-body ul { list-style: disc; padding-left: 20px; margin-bottom: 14px; }
    .blog-body ol { list-style: decimal; padding-left: 20px; margin-bottom: 14px; }
    .blog-body li { margin-bottom: 4px; }
    .blog-body blockquote { border-left: 4px solid #EADDB7; padding-left: 12px; color: #5a7a90; margin: 14px 0; }
    .blog-body a  { color: #C94277; text-decoration: underline; }
    .blog-body strong { font-weight: 600; }

    /* Read more link */
    .read-more {
      display: block; text-align: center;
      font-size: 13px; font-weight: 600;
      color: #C94277; text-decoration: none;
      padding: 16px 24px;
    }
    .read-more:hover { text-decoration: underline; }
CSS;

// ── Render page ───────────────────────────────────────────────────────────────
share_page_open(
    $post ? $post['title'] : 'Blog',
    $ogTitle, $ogDesc, $ogImage, $ogUrl,
    $extraCss
);
share_logo();

if (!$post): ?>

  <?php share_error_state($fetchError, $id, 'post', '/blogs', 'Browse all posts'); ?>

<?php else: ?>

  <!-- Cover image (only shown when one is configured) -->
  <?php if ($post['cover_image_path']): ?>
  <div class="cover-wrap">
    <img src="<?= e(absoluteUrl($post['cover_image_path'], $siteBase)) ?>"
         alt="<?= e($post['title']) ?>"
         loading="eager" />
  </div>
  <?php endif; ?>

  <!-- Post content -->
  <div class="blog-content">
    <?php if ($publishedLabel): ?>
      <div class="blog-date"><?= e($publishedLabel) ?></div>
    <?php endif; ?>
    <h1 class="blog-title"><?= e($post['title']) ?></h1>
    <?php if ($post['extract']): ?>
      <p class="blog-extract"><?= nl2br(e($post['extract'])) ?></p>
    <?php endif; ?>
    <?php if ($post['body']): ?>
      <div class="blog-body"><?= $post['body'] ?></div>
    <?php endif; ?>
  </div>

  <a class="read-more" href="/blogs?slug=<?= urlencode($post['slug']) ?>">View on gokulbhavan.org →</a>

<?php endif;

share_page_close();
