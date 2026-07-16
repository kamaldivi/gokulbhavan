<?php
/**
 * sloka.php — Śloka share card with OG tags
 * URL: gokulbhavan.org/share/sloka.php?id=42
 *
 * Fetches from the sloka table (integer id).
 * Displays: scripture + category header, sloka text, scripture ref, translation.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../api/config.php';

// ── Input validation ──────────────────────────────────────────────────────────
$id       = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$siteBase = 'https://gokulbhavan.org';

// ── Fetch sloka ───────────────────────────────────────────────────────────────
$sloka      = null;
$fetchError = false;

if ($id > 0) {
    try {
        $db   = get_db();
        $stmt = $db->prepare("
            SELECT
                s.id,
                s.title,
                s.search_title,
                s.sloka_text,
                s.scripture_ref,
                s.translation,
                s.word_by_word,
                s.audio_file_path,
                sc.category_name,
                scr.name        AS scripture_name,
                scr.short_title AS scripture_short
            FROM  sloka s
            JOIN  sloka_category sc  ON sc.category_code = s.category_code
            LEFT JOIN scripture scr  ON scr.id = s.scripture_id
            WHERE s.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $sloka = $row;
    } catch (Throwable $e) {
        $fetchError = true;
    }
}

// ── OG values ─────────────────────────────────────────────────────────────────
$ogTitle = 'Gokul Bhavan Ślokas';
$ogDesc  = '';
$ogImage = '';
$ogUrl   = $id > 0 ? $siteBase . '/share/sloka.php?id=' . $id : $siteBase . '/slokas-new';

// ── Page-specific CSS ─────────────────────────────────────────────────────────
$extraCss = <<<'CSS'
    .sloka-header {
      padding: 20px 24px 16px;
      border-bottom: 1px solid #EADDB7;
    }
    .sloka-header-meta {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #C94277;
      margin-bottom: 8px;
    }
    .sloka-title {
      font-family: 'Manrope', sans-serif;
      font-weight: 800;
      font-size: clamp(18px, 5vw, 24px);
      line-height: 1.2;
      color: #082A4A;
    }

    /* Sloka text — lotus pink, bold, centered, pre-wrap */
    .sloka-text-wrap {
      padding: 24px 24px 0;
    }
    .sloka-text {
      font-family: 'Inter', system-ui, sans-serif;
      font-weight: 700;
      font-size: 17px;
      line-height: 2;
      color: #C94277;
      text-align: center;
      white-space: pre-wrap;
      word-break: break-word;
      background: #FFF7DF;
      border: 1px solid #EADDB7;
      border-radius: 16px;
      padding: 20px 20px;
    }
    .sloka-ref {
      font-size: 11px;
      font-family: 'Inter', monospace;
      color: #2A506A;
      text-align: right;
      padding: 6px 4px 0;
    }

    /* Translation */
    .sloka-translation {
      padding: 20px 24px;
      border-top: 1px solid #EADDB7;
    }
    .sloka-translation .section-label { margin-bottom: 8px; }
    .sloka-translation p {
      font-size: 14px;
      line-height: 1.75;
      color: #2A506A;
      white-space: pre-wrap;
      word-break: break-word;
    }

    /* Audio player */
    .sloka-audio {
      padding: 16px 24px;
      border-top: 1px solid #EADDB7;
    }
    .sloka-audio .section-label { margin-bottom: 10px; }
    .sloka-audio audio {
      width: 100%;
      border-radius: 8px;
      accent-color: #C94277;
    }

    /* Browse link */
    .browse-link {
      display: block;
      text-align: center;
      font-size: 13px;
      font-weight: 600;
      color: #C94277;
      text-decoration: none;
      padding: 16px 24px;
    }
    .browse-link:hover { text-decoration: underline; }
CSS;

// Sloka pages need Noto Sans fonts for Sanskrit diacritics
$fontsUrl = 'https://fonts.googleapis.com/css2?'
    . 'family=Inter:wght@400;500;600;700'
    . '&family=Manrope:wght@700;800'
    . '&display=swap';

// ── Render page ───────────────────────────────────────────────────────────────
share_page_open(
    $sloka ? ($sloka['search_title'] ?: $sloka['title'] ?: 'Śloka') : 'Śloka',
    $ogTitle, $ogDesc, $ogImage, $ogUrl,
    $extraCss, $fontsUrl
);
share_logo();

if (!$sloka): ?>

  <?php share_error_state($fetchError, $id, 'śloka', '/slokas', 'Browse all ślokas'); ?>

<?php else: ?>

  <!-- Header: scripture — category + optional title -->
  <div class="sloka-header">
    <div class="sloka-header-meta"><?= e($headerLabel) ?></div>
    <?php if ($sloka['search_title'] || $sloka['title']): ?>
      <h1 class="sloka-title"><?= e($sloka['search_title'] ?: $sloka['title']) ?></h1>
    <?php endif; ?>
  </div>

  <!-- Sloka text -->
  <div class="sloka-text-wrap">
    <div class="sloka-text"><?= e($sloka['sloka_text'] ?? '') ?></div>
    <?php if ($sloka['scripture_ref']): ?>
      <p class="sloka-ref">· <?= e($sloka['scripture_ref']) ?></p>
    <?php endif; ?>
  </div>

  <!-- Translation -->
  <?php if ($sloka['translation']): ?>
  <div class="sloka-translation">
    <div class="section-label">Meaning</div>
    <p><?= e($sloka['translation']) ?></p>
  </div>
  <?php endif; ?>

  <!-- Word-by-Word -->
  <?php if ($sloka['word_by_word']): ?>
  <div class="sloka-translation">
    <div class="section-label">Word-by-Word</div>
    <p><?= e($sloka['word_by_word']) ?></p>
  </div>
  <?php endif; ?>

  <!-- Audio player -->
  <?php if (!empty($sloka['audio_file_path'])): ?>
  <div class="sloka-audio">
    <div class="section-label">Listen</div>
    <audio controls preload="none">
      <source src="<?= e('/' . ltrim($sloka['audio_file_path'], '/')) ?>" type="audio/mpeg" />
    </audio>
  </div>
  <?php endif; ?>

  <a class="browse-link" href="/slokas-new">Browse all Ślokas →</a>

<?php endif;

share_page_close();
