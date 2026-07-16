<?php
/**
 * _layout.php — Shared layout helpers for gokulbhavan.org/share/* pages.
 *
 * Include at the very top of each share page (before any output).
 * Provides: e(), share_page_open(), share_logo(), share_page_close(),
 *           share_error_state()
 *
 * This file produces NO output when included.
 */

// ── HTML escape helper ────────────────────────────────────────────────────────
function e(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

// ── Open the page: outputs <!DOCTYPE> … <body> + opens .card div ─────────────
/**
 * @param string $pageTitle  Shown in <title> — " | Gokul Bhavan" appended automatically
 * @param string $ogTitle    og:title  (WhatsApp preview headline)
 * @param string $ogDesc     og:description  (keep under 200 chars)
 * @param string $ogImage    Absolute URL to 1200×630 OG image
 * @param string $ogUrl      Canonical URL for this share page
 * @param string $extraCss   Page-specific CSS appended after shared base styles
 * @param string $fontsUrl   Full Google Fonts stylesheet URL (overrides default)
 */
function share_page_open(
    string $pageTitle,
    string $ogTitle,
    string $ogDesc,
    string $ogImage,
    string $ogUrl,
    string $extraCss = '',
    string $fontsUrl  = ''
): void {
    if (!$fontsUrl) {
        $fontsUrl = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Manrope:wght@700;800&display=swap';
    }

    $title     = e($pageTitle) . ' | Gokul Bhavan';
    $ogTitleE  = e($ogTitle);
    $ogDescE   = e($ogDesc);
    $ogImageE  = e($ogImage);
    $ogUrlE    = e($ogUrl);
    $fontsUrlE = e($fontsUrl);
    $css       = _share_base_css() . "\n" . $extraCss;

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{$title}</title>

  <!-- Open Graph — controls WhatsApp / iMessage / Facebook link preview -->
  <meta property="og:type"         content="website" />
  <meta property="og:url"          content="{$ogUrlE}" />
HTML;
    if ($ogTitleE !== '') echo "  <meta property=\"og:title\"       content=\"{$ogTitleE}\" />\n";
    if ($ogDescE  !== '') echo "  <meta property=\"og:description\"  content=\"{$ogDescE}\" />\n";
    echo <<<HTML
  <meta property="og:image"            content="{$ogImageE}" />
  <meta property="og:image:secure_url" content="{$ogImageE}" />
  <meta property="og:image:type"       content="image/jpeg" />
  <meta property="og:image:width"      content="1200" />
  <meta property="og:image:height"     content="630" />
  <meta property="og:site_name"        content="Gokul Bhavan" />

  <!-- Twitter / X card -->
  <meta name="twitter:card"  content="summary_large_image" />
HTML;
    if ($ogTitleE !== '') echo "  <meta name=\"twitter:title\"       content=\"{$ogTitleE}\" />\n";
    if ($ogDescE  !== '') echo "  <meta name=\"twitter:description\" content=\"{$ogDescE}\" />\n";
    echo "  <meta name=\"twitter:image\" content=\"{$ogImageE}\" />\n";
    if ($ogDescE  !== '') echo "  <meta name=\"description\"         content=\"{$ogDescE}\" />\n";
    echo <<<HTML

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="{$fontsUrlE}" rel="stylesheet" />

  <style>
{$css}
  </style>
</head>
<body>
<div class="card">
HTML;
}

// ── Logo strip (top of card) ──────────────────────────────────────────────────
function share_logo(): void { ?>
  <div class="logo-strip">
    <img src="/assets/logo.png" alt="Gokul Bhavan" onerror="this.style.display='none'" />
    <div class="site-name">Gokul Bhavan Gaud&#299;ya Ma&#7789;ha</div>
  </div>
<?php }

// ── Close the page: footer + </body></html> ───────────────────────────────────
function share_page_close(): void { ?>
</div><!-- /.card -->
<p class="page-footer">
  <a href="https://gokulbhavan.org">gokulbhavan.org</a>
  &nbsp;·&nbsp;
  Gokul Bhavan Gau&#7693;&#299;ya Ma&#7789;ha
</p>
</body>
</html>
<?php }

// ── Error / Not-found state rendered inside the card ─────────────────────────
/**
 * @param bool       $isFetchError  true = server/DB error; false = record not found
 * @param mixed      $id            The requested ID value (used in message)
 * @param string     $typeName      Human-readable type: "program", "post", "sloka" …
 * @param string     $backUrl       URL for the fallback link
 * @param string     $backLabel     Text for the fallback link
 */
function share_error_state(
    bool   $isFetchError,
    mixed  $id,
    string $typeName  = 'content',
    string $backUrl   = '/',
    string $backLabel = 'Go to homepage'
): void {
    if ($isFetchError) {
        $icon = '⚠️';
        $h2   = 'Something went wrong';
        $msg  = "We couldn't load this {$typeName} right now. Please try again in a moment.";
    } elseif (!$id) {
        $icon = '🔗';
        $h2   = 'No ' . $typeName . ' specified';
        $msg  = 'Please use the link that was shared with you.';
    } else {
        $icon = '🔍';
        $h2   = ucfirst($typeName) . ' not found';
        $msg  = "This link doesn't match an active {$typeName}. It may have been removed or the link may be incorrect.";
    }
    ?>
  <div class="state-box">
    <div class="icon"><?= $icon ?></div>
    <h2><?= e($h2) ?></h2>
    <p><?= e($msg) ?><br /><br />
      <a href="<?= e($backUrl) ?>"><?= e($backLabel) ?> →</a>
    </p>
  </div>
    <?php
}

// ── Shared base CSS (private) ─────────────────────────────────────────────────
function _share_base_css(): string { return <<<'CSS'
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { -webkit-text-size-adjust: 100%; }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: #FFF7DF;
      color: #082A4A;
      min-height: 100dvh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 24px 16px 48px;
    }

    /* ── Card ────────────────────────────────────────────────── */
    .card {
      width: 100%;
      max-width: 480px;
      background: #fff;
      border: 1px solid #EADDB7;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 4px 32px rgba(8, 42, 74, 0.10);
    }

    /* ── Logo strip ──────────────────────────────────────────── */
    .logo-strip {
      background: #2A506A;
      padding: 20px 24px 16px;
      text-align: center;
    }
    .logo-strip img { height: 48px; width: auto; display: inline-block; }
    .logo-strip .site-name {
      font-family: 'Manrope', sans-serif;
      font-weight: 800;
      font-size: 13px;
      color: #E8A207;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-top: 8px;
    }

    /* ── Generic section block ───────────────────────────────── */
    .section {
      padding: 20px 24px;
      border-bottom: 1px solid #EADDB7;
    }

    .section-label {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: #C94277;
      margin-bottom: 10px;
    }

    /* ── Badges ──────────────────────────────────────────────── */
    .badge-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
    .badge {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      padding: 3px 10px;
      border-radius: 999px;
    }
    .badge-tan   { background: #EADDB7; color: #082A4A; }
    .badge-blue  { background: #e0f2fe; color: #0369a1; }
    .badge-amber { background: #fef3c7; color: #92400e; }
    .badge-lotus { background: #fce7f0; color: #9d174d; }
    .badge-green { background: #dcfce7; color: #166534; }

    /* ── Typography ──────────────────────────────────────────── */
    .page-title {
      font-family: 'Manrope', sans-serif;
      font-weight: 800;
      font-size: clamp(22px, 6vw, 30px);
      line-height: 1.15;
      color: #082A4A;
      margin-bottom: 10px;
    }
    .meta-text        { font-size: 13px; color: #2A506A; line-height: 1.5; }
    .meta-text strong { color: #082A4A; font-weight: 600; }
    .body-text        { font-size: 14px; line-height: 1.65; color: #2A506A; }

    /* ── Error / not-found state ─────────────────────────────── */
    .state-box              { padding: 40px 24px; text-align: center; }
    .state-box .icon        { font-size: 40px; margin-bottom: 12px; }
    .state-box h2 {
      font-family: 'Manrope', sans-serif;
      font-size: 20px;
      font-weight: 800;
      color: #082A4A;
      margin-bottom: 8px;
    }
    .state-box p { font-size: 14px; color: #2A506A; line-height: 1.6; }
    .state-box a { color: #C94277; font-weight: 600; text-decoration: none; }

    /* ── Page footer ─────────────────────────────────────────── */
    .page-footer {
      margin-top: 24px;
      font-size: 12px;
      color: #2A506A;
      text-align: center;
      opacity: 0.7;
    }
    .page-footer a { color: #C94277; text-decoration: none; font-weight: 600; }

    /* ── Inline loading spinner ──────────────────────────────── */
    .spinner {
      display: inline-block;
      width: 14px; height: 14px;
      border: 2px solid #EADDB7;
      border-top-color: #C94277;
      border-radius: 50%;
      animation: gb-spin 0.7s linear infinite;
      vertical-align: middle;
      flex-shrink: 0;
    }
    @keyframes gb-spin { to { transform: rotate(360deg); } }
CSS; }
