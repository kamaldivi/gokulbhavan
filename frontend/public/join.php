<?php
/**
 * join.php — WhatsApp-optimized Program Join Page
 * URL: gokulbhavan.org/join.php?id=3
 *
 * Fetches program data server-side so Open Graph meta tags are
 * present in the HTML when WhatsApp's link-preview crawler visits.
 * (WhatsApp does not execute JavaScript — OG tags must be in raw HTML.)
 *
 * ── Better alternatives to sharing raw IDs ────────────────────────────────
 * The cleanest UX improvement is to add a "Copy WhatsApp Link" button to the
 * admin program list. Admins never need to know the ID — they just click the
 * button and the correct URL (?id=X) is copied to their clipboard. Zero new
 * DB columns needed. A future improvement could add a short slug column
 * (e.g. "friday-bhagavatam") for even cleaner URLs like ?p=friday-bhagavatam.
 */

// ── Read & validate input ──────────────────────────────────────────────────
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// ── Fetch program from DB ──────────────────────────────────────────────────
$program = null;
$fetchError = false;

if ($id > 0) {
    try {
        require_once __DIR__ . '/api/config.php';
        $db = get_db();
        $stmt = $db->prepare("
            SELECT id, title, description, teacher, language,
                   day_of_week, time_est,
                   zoom_url, youtube_live_url,
                   start_date, end_date, event_date, event_time,
                   platform, duration_min
            FROM program
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $program = $row;
        }
    } catch (Throwable $e) {
        $fetchError = true;
    }
}

// ── Open Graph values ──────────────────────────────────────────────────────
$siteBase = 'https://gokulbhavan.org';
$ogTitle  = $program
    ? htmlspecialchars($program['title'], ENT_QUOTES) . ' — Gokul Bhavan'
    : 'Gokul Bhavan Programs';
$rawDesc  = $program
    ? ($program['description'] ?? 'Join us for this sacred program at Gokul Bhavan Gauḍīya Maṭha.')
    : 'Sacred programs at Gokul Bhavan Gauḍīya Maṭha.';
$ogDesc   = htmlspecialchars(mb_strimwidth($rawDesc, 0, 200, '…'), ENT_QUOTES);
$ogImage  = $siteBase . '/assets/gb_banner.png';
$ogUrl    = $siteBase . '/join.php?id=' . $id;

// ── Helpers ────────────────────────────────────────────────────────────────
function e(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}


// For one-off events: format a date string nicely
function formatEventDate(?string $d): string {
    if (!$d) return '';
    try {
        $dt = new DateTime($d);
        return $dt->format('l, F j, Y');
    } catch (Exception $e) { return $d; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $ogTitle ?></title>

  <!-- ── Open Graph (controls WhatsApp / iMessage / Facebook preview) ──── -->
  <meta property="og:type"        content="website" />
  <meta property="og:url"         content="<?= e($ogUrl) ?>" />
  <meta property="og:title"       content="<?= $ogTitle ?>" />
  <meta property="og:description" content="<?= $ogDesc ?>" />
  <meta property="og:image"       content="<?= e($ogImage) ?>" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:site_name"   content="Gokul Bhavan" />

  <!-- Twitter / X card (same values, some clients use these) -->
  <meta name="twitter:card"        content="summary_large_image" />
  <meta name="twitter:title"       content="<?= $ogTitle ?>" />
  <meta name="twitter:description" content="<?= $ogDesc ?>" />
  <meta name="twitter:image"       content="<?= e($ogImage) ?>" />

  <meta name="description" content="<?= $ogDesc ?>" />

  <!-- Google Fonts: Manrope (display) + Inter (body) -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Manrope:wght@700;800&display=swap" rel="stylesheet" />

  <style>
    /* ── Reset & base ──────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { -webkit-text-size-adjust: 100%; }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: #FFF7DF;        /* brand-parchment */
      color: #082A4A;             /* brand-navy */
      min-height: 100dvh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 24px 16px 48px;
    }

    /* ── Card ──────────────────────────────────────────────── */
    .card {
      width: 100%;
      max-width: 480px;
      background: #fff;
      border: 1px solid #EADDB7;  /* brand-tan */
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 4px 32px rgba(8, 42, 74, 0.10);
    }

    /* ── Logo strip ────────────────────────────────────────── */
    .logo-strip {
      background: #2A506A;        /* brand-slate */
      padding: 20px 24px 16px;
      text-align: center;
    }
    .logo-strip img {
      height: 48px;
      width: auto;
      display: inline-block;
    }
    .logo-strip .site-name {
      font-family: 'Manrope', sans-serif;
      font-weight: 800;
      font-size: 13px;
      color: #E8A207;             /* brand-gold */
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-top: 8px;
    }

    /* ── Program header ────────────────────────────────────── */
    .prog-header {
      padding: 28px 24px 20px;
      border-bottom: 1px solid #EADDB7;
    }

    .badge-row {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 10px;
    }
    .badge {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      padding: 3px 10px;
      border-radius: 999px;
    }
    .badge-day  { background: #EADDB7; color: #082A4A; }
    .badge-lang { background: #e0f2fe; color: #0369a1; }
    .badge-oneoff { background: #fef3c7; color: #92400e; }

    .prog-title {
      font-family: 'Manrope', sans-serif;
      font-weight: 800;
      font-size: clamp(22px, 6vw, 30px);
      line-height: 1.15;
      color: #082A4A;
      margin-bottom: 10px;
    }
    .prog-meta {
      font-size: 13px;
      color: #2A506A;             /* brand-slate */
      line-height: 1.5;
    }
    .prog-meta strong { color: #082A4A; font-weight: 600; }

    .prog-desc {
      margin-top: 12px;
      font-size: 14px;
      line-height: 1.65;
      color: #2A506A;
    }

    /* ── Local time display ────────────────────────────────── */
    .times-section {
      padding: 20px 24px;
      border-bottom: 1px solid #EADDB7;
    }
    .times-label {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: #C94277;             /* brand-lotus */
      margin-bottom: 10px;
    }
    .local-time {
      font-size: 17px;
      font-weight: 700;
      color: #082A4A;
      line-height: 1.4;
    }
    .local-tz {
      font-size: 13px;
      font-weight: 400;
      color: #2A506A;
      margin-left: 4px;
    }

    /* ── Join buttons ──────────────────────────────────────── */
    .buttons-section {
      padding: 20px 24px 24px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .join-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      padding: 18px 20px;
      border-radius: 14px;
      border: none;
      font-family: 'Manrope', sans-serif;
      font-weight: 800;
      font-size: 16px;
      letter-spacing: 0.03em;
      text-decoration: none;
      cursor: pointer;
      transition: filter 0.15s, transform 0.1s;
      -webkit-tap-highlight-color: transparent;
    }
    .join-btn:active { transform: scale(0.975); }
    .join-btn svg { flex-shrink: 0; }

    .btn-zoom {
      background: #082A4A;
      color: #fff;
    }
    .btn-zoom:hover { filter: brightness(1.15); }

    .btn-youtube {
      background: #DC2626;
      color: #fff;
    }
    .btn-youtube:hover { filter: brightness(1.1); }

    .btn-disabled {
      background: #EADDB7;
      color: #2A506A;
      opacity: 0.6;
      cursor: not-allowed;
      pointer-events: none;
    }

    /* ── State pages (error / not found) ───────────────────── */
    .state-box {
      padding: 40px 24px;
      text-align: center;
    }
    .state-box .icon { font-size: 40px; margin-bottom: 12px; }
    .state-box h2 {
      font-family: 'Manrope', sans-serif;
      font-size: 20px; font-weight: 800;
      color: #082A4A; margin-bottom: 8px;
    }
    .state-box p { font-size: 14px; color: #2A506A; line-height: 1.6; }
    .state-box a { color: #C94277; font-weight: 600; text-decoration: none; }

    /* ── Page footer ───────────────────────────────────────── */
    .page-footer {
      margin-top: 24px;
      font-size: 12px;
      color: #2A506A;
      text-align: center;
      opacity: 0.7;
    }
    .page-footer a { color: #C94277; text-decoration: none; font-weight: 600; }
  </style>
</head>
<body>

<div class="card">

  <!-- ── Logo strip ─────────────────────────────────────────── -->
  <div class="logo-strip">
    <img src="/assets/logo.png" alt="Gokul Bhavan" onerror="this.style.display='none'" />
    <div class="site-name">Gokul Bhavan Gaud&#299;ya Ma&#7789;ha</div>
  </div>

<?php if (!$program): ?>

  <!-- ── Error / Not Found state ────────────────────────────── -->
  <div class="state-box">
    <div class="icon"><?= $fetchError ? '⚠️' : '🔍' ?></div>
    <h2><?= $fetchError ? 'Something went wrong' : 'Program not found' ?></h2>
    <p>
      <?php if ($fetchError): ?>
        We couldn't load this program right now. Please try again in a moment.
      <?php elseif ($id <= 0): ?>
        No program ID was provided. Please use the link shared with you.
      <?php else: ?>
        This program link (ID: <?= e($id) ?>) doesn't match an active program.
        It may have ended or the link may be incorrect.
      <?php endif; ?>
      <br /><br />
      <a href="/programs">View all programs →</a>
    </p>
  </div>

<?php else:
    $isOneOff  = $program['event_date'] && $program['start_date'] === $program['end_date'];
    $hasZoom   = !empty($program['zoom_url']);
    $hasYT     = !empty($program['youtube_live_url']);
    $hasTimes  = !empty($program['time_est']) && !empty($program['day_of_week']);
?>

  <!-- ── Program header ─────────────────────────────────────── -->
  <div class="prog-header">

    <div class="badge-row">
      <?php if ($isOneOff): ?>
        <span class="badge badge-oneoff">Special Event</span>
      <?php elseif ($program['day_of_week']): ?>
        <span class="badge badge-day">Every <?= e($program['day_of_week']) ?></span>
      <?php endif; ?>
      <?php if ($program['language']): ?>
        <span class="badge badge-lang"><?= e($program['language']) ?></span>
      <?php endif; ?>
    </div>

    <h1 class="prog-title"><?= e($program['title']) ?></h1>

    <div class="prog-meta">
      <?php if ($isOneOff && $program['event_date']): ?>
        <strong><?= e(formatEventDate($program['event_date'])) ?></strong>
        <?php if ($program['event_time']): ?>
          &nbsp;·&nbsp;<?= e($program['event_time']) ?> ET
        <?php endif; ?>
      <?php elseif ($program['start_date'] && $program['start_date'] > date('Y-m-d')): ?>
        <strong>Starting</strong> <?= e($program['start_date']) ?>
        <?php if (!empty($program['end_date']) && $program['end_date'] !== $program['start_date']): ?>
          &nbsp;·&nbsp;ends <?= e($program['end_date']) ?>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($program['teacher']): ?>
        <br />By <strong><?= e($program['teacher']) ?></strong>
      <?php endif; ?>
      <?php if ($program['duration_min']): ?>
        &nbsp;·&nbsp;<?= (int)$program['duration_min'] ?> minutes
      <?php endif; ?>
    </div>

    <?php if ($program['description']): ?>
      <p class="prog-desc"><?= nl2br(e($program['description'])) ?></p>
    <?php endif; ?>

  </div>

  <!-- ── Local time (JS-computed) ──────────────────────────── -->
  <?php if ($hasTimes || ($isOneOff && $program['event_time'])): ?>
  <div class="times-section">
    <div class="times-label">Program Time</div>
    <div class="local-time" id="prog-local-time">—</div>
  </div>
  <script>
    (function () {
      var isOneOff  = <?= json_encode($isOneOff) ?>;
      var dayOfWeek = <?= json_encode($program['day_of_week'] ?? '') ?>;
      var timeEst   = <?= json_encode($program['time_est']   ?? '') ?>;
      var eventDate = <?= json_encode($program['event_date'] ?? '') ?>;
      var eventTime = <?= json_encode($program['event_time'] ?? '') ?>;

      var el = document.getElementById('prog-local-time');

      // Parse a time string "HH:MM" (24h) or "H:MM AM/PM" → { h, min }
      function parseTime(s) {
        var m12 = s.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
        var m24 = s.match(/^(\d{1,2}):(\d{2})$/);
        if (!m12 && !m24) return null;
        var h = 0, min = 0;
        if (m12) {
          h = parseInt(m12[1]); min = parseInt(m12[2]);
          if (m12[3].toUpperCase() === 'PM' && h !== 12) h += 12;
          if (m12[3].toUpperCase() === 'AM' && h === 12) h = 0;
        } else {
          h = parseInt(m24[1]); min = parseInt(m24[2]);
        }
        return { h: h, min: min };
      }

      // Convert an ET day+time to a UTC Date, handling DST correctly.
      // Mirrors the same probe technique used in programs.astro.
      function etToUtc(dayName, parsed) {
        var DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        var srcIdx = DAYS.indexOf(dayName);
        if (srcIdx === -1 || !parsed) return null;
        var now  = new Date();
        var diff = (srcIdx - now.getDay() + 7) % 7;
        var ref  = new Date(now);
        ref.setDate(now.getDate() + diff);

        var probe = new Date(Date.UTC(ref.getFullYear(), ref.getMonth(), ref.getDate(), parsed.h, parsed.min));
        var etHourRaw = parseInt(
          new Intl.DateTimeFormat('en-US', { timeZone: 'America/New_York', hour: 'numeric', hour12: false }).format(probe)
        );
        var etHour = etHourRaw === 24 ? 0 : etHourRaw;
        var offsetH = parsed.h - etHour;
        if (offsetH > 12)  offsetH -= 24;
        if (offsetH < -12) offsetH += 24;
        return new Date(probe.getTime() + offsetH * 3600000);
      }

      try {
        var utcDate;

        if (isOneOff && eventDate && eventTime) {
          // One-off: specific date + ET time
          var parsed = parseTime(eventTime);
          utcDate = etToUtc(new Date(eventDate + 'T12:00:00').toLocaleDateString('en-US', { weekday: 'long' }), parsed);
        } else if (dayOfWeek && timeEst) {
          // Recurring: weekly day + ET time
          utcDate = etToUtc(dayOfWeek, parseTime(timeEst));
        }

        if (!utcDate) { el.textContent = '—'; return; }

        var dayFull = new Intl.DateTimeFormat('en-US', { weekday: 'long' }).format(utcDate);
        var time    = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).format(utcDate);
        var tzAbbr  = new Intl.DateTimeFormat('en-US', { timeZoneName: 'short' })
                        .formatToParts(utcDate).find(function(p){ return p.type === 'timeZoneName'; });

        el.innerHTML = (isOneOff ? '' : 'Every ') + dayFull + ' &middot; ' + time
          + (tzAbbr ? '<span class="local-tz">' + tzAbbr.value + '</span>' : '');
      } catch (err) {
        el.textContent = '—';
      }
    })();
  </script>
  <?php endif; ?>

  <!-- ── Join buttons ───────────────────────────────────────── -->
  <div class="buttons-section">

    <?php if ($hasZoom): ?>
    <a href="<?= e($program['zoom_url']) ?>"
       target="_blank" rel="noopener noreferrer"
       class="join-btn btn-zoom">
      <!-- Zoom icon -->
      <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M24 12c0 6.627-5.373 12-12 12S0 18.627 0 12 5.373 0 12 0s12 5.373 12 12zm-6.508-4.5-3.743 2.688V7.5H6.257C5.563 7.5 5 8.063 5 8.757v6.985h8.249c.694 0 1.257-.563 1.257-1.257V12l3.743 2.7A.375.375 0 0 0 19 14.4V7.8a.375.375 0 0 0-.508-.3z"/>
      </svg>
      Join on Zoom
    </a>
    <?php else: ?>
    <span class="join-btn btn-disabled">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M24 12c0 6.627-5.373 12-12 12S0 18.627 0 12 5.373 0 12 0s12 5.373 12 12zm-6.508-4.5-3.743 2.688V7.5H6.257C5.563 7.5 5 8.063 5 8.757v6.985h8.249c.694 0 1.257-.563 1.257-1.257V12l3.743 2.7A.375.375 0 0 0 19 14.4V7.8a.375.375 0 0 0-.508-.3z"/>
      </svg>
      Zoom not available
    </span>
    <?php endif; ?>

    <?php if ($hasYT): ?>
    <a href="<?= e($program['youtube_live_url']) ?>"
       target="_blank" rel="noopener noreferrer"
       class="join-btn btn-youtube">
      <!-- YouTube icon -->
      <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M23.495 6.205a3.007 3.007 0 0 0-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 0 0 .527 6.205a31.247 31.247 0 0 0-.522 5.805 31.247 31.247 0 0 0 .522 5.783 3.007 3.007 0 0 0 2.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 0 0 2.088-2.088 31.247 31.247 0 0 0 .5-5.783 31.247 31.247 0 0 0-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/>
      </svg>
      Watch on YouTube
    </a>
    <?php else: ?>
    <span class="join-btn btn-disabled">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M23.495 6.205a3.007 3.007 0 0 0-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 0 0 .527 6.205a31.247 31.247 0 0 0-.522 5.805 31.247 31.247 0 0 0 .522 5.783 3.007 3.007 0 0 0 2.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 0 0 2.088-2.088 31.247 31.247 0 0 0 .5-5.783 31.247 31.247 0 0 0-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/>
      </svg>
      YouTube not available
    </span>
    <?php endif; ?>

    <?php if (!$hasZoom && !$hasYT): ?>
    <p style="text-align:center;font-size:13px;color:#2A506A;padding:8px 0 0;">
      Join details will be shared closer to the program. Check back soon.
    </p>
    <?php endif; ?>

  </div>

<?php endif; ?>
</div>

<!-- ── Page footer ─────────────────────────────────────────── -->
<p class="page-footer">
  <a href="https://gokulbhavan.org">gokulbhavan.org</a>
  &nbsp;·&nbsp;
  Gokul Bhavan Gauḍīya Maṭha
</p>

</body>
</html>
