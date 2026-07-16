<?php
/**
 * program.php — Program share page with WhatsApp link preview
 * URL: gokulbhavan.org/share/program/3
 *
 * Server-side DB fetch ensures OG meta tags are present in raw HTML
 * for WhatsApp / iMessage preview crawlers (which do not execute JS).
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../api/config.php';

// ── Input validation ──────────────────────────────────────────────────────────
$id         = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$siteBase   = 'https://gokulbhavan.org';

// ── Fetch program ─────────────────────────────────────────────────────────────
$program    = null;
$fetchError = false;

if ($id > 0) {
    try {
        $db   = get_db();
        $stmt = $db->prepare("
            SELECT id, title, description, teacher, language,
                   day_of_week, time_est,
                   zoom_url, youtube_live_url,
                   start_date, end_date, event_date, event_time,
                   platform, duration_min, cover_image_path
            FROM program
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $program = $row;
            // Override with global config URLs
            if (defined('ZOOM_URL'))    $program['zoom_url']         = ZOOM_URL;
            if (defined('YOUTUBE_URL')) $program['youtube_live_url'] = YOUTUBE_URL;
        }
    } catch (Throwable $e) {
        $fetchError = true;
    }
}

// ── OG values ────────────────────────────────────────────────────────────────
// Intentionally no og:title / og:description — the cover image carries the
// full program info so WhatsApp preview shows only the image.
$ogTitle = '';
$ogDesc  = '';

$ogImage = ($program && !empty($program['cover_image_path']))
    ? $siteBase . '/' . ltrim($program['cover_image_path'], '/')
    : $siteBase . '/assets/share/og-program.jpg';

$ogUrl   = $siteBase . '/share/program.php?id=' . $id;

// ── Helper ────────────────────────────────────────────────────────────────────
function formatEventDate(?string $d): string {
    if (!$d) return '';
    try { return (new DateTime($d))->format('l, F j, Y'); }
    catch (Exception $e) { return $d; }
}

// ── Page-specific CSS ────────────────────────────────────────────────────────
$extraCss = <<<'CSS'
    /* Cover banner */
    .prog-cover {
      width: 100%; aspect-ratio: 16 / 9;
      object-fit: cover; display: block;
    }

    /* Program header */
    .prog-header {
      padding: 28px 24px 20px;
      border-bottom: 1px solid #EADDB7;
    }
    .prog-title {
      font-family: 'Manrope', sans-serif;
      font-weight: 800;
      font-size: clamp(22px, 6vw, 30px);
      line-height: 1.15;
      color: #082A4A;
      margin-bottom: 10px;
    }
    .prog-meta { font-size: 13px; color: #2A506A; line-height: 1.5; }
    .prog-meta strong { color: #082A4A; font-weight: 600; }
    .prog-desc { margin-top: 12px; font-size: 14px; line-height: 1.65; color: #2A506A; }

    /* Local time */
    .times-section { padding: 20px 24px; border-bottom: 1px solid #EADDB7; }
    .local-time { font-size: 17px; font-weight: 700; color: #082A4A; line-height: 1.4; }
    .local-tz   { font-size: 13px; font-weight: 400; color: #2A506A; margin-left: 4px; }

    /* Join buttons */
    .buttons-section { padding: 20px 24px 24px; display: flex; flex-direction: column; gap: 12px; }
    .join-btn {
      display: flex; align-items: center; justify-content: center; gap: 10px;
      width: 100%; padding: 18px 20px;
      border-radius: 14px; border: none;
      font-family: 'Manrope', sans-serif; font-weight: 800;
      font-size: 16px; letter-spacing: 0.03em;
      text-decoration: none; cursor: pointer;
      transition: filter 0.15s, transform 0.1s;
      -webkit-tap-highlight-color: transparent;
    }
    .join-btn:active { transform: scale(0.975); }
    .join-btn svg   { flex-shrink: 0; }
    .btn-zoom    { background: #082A4A; color: #fff; }
    .btn-zoom:hover { filter: brightness(1.15); }
    .btn-youtube { background: #DC2626; color: #fff; }
    .btn-youtube:hover { filter: brightness(1.1); }
    .btn-disabled {
      background: #EADDB7; color: #2A506A;
      opacity: 0.6; cursor: not-allowed; pointer-events: none;
    }
CSS;

// ── Render page ───────────────────────────────────────────────────────────────
share_page_open(
    $program ? $program['title'] : 'Program',
    $ogTitle, $ogDesc, $ogImage, $ogUrl,
    $extraCss
);
share_logo();

if (!$program): ?>

  <?php share_error_state($fetchError, $id, 'program', '/programs', 'View all programs'); ?>

<?php else:
    $isOneOff = $program['event_date'] && $program['start_date'] === $program['end_date'];
    $hasZoom  = !empty($program['zoom_url']);
    $hasYT    = !empty($program['youtube_live_url']);
    $hasTimes = !empty($program['time_est']) && !empty($program['day_of_week']);
?>

  <?php if (!empty($program['cover_image_path'])): ?>
  <img class="prog-cover"
       src="<?= e($siteBase . '/' . ltrim($program['cover_image_path'], '/')) ?>"
       alt="<?= e($program['title']) ?>" />
  <?php endif; ?>

  <!-- Program header -->
  <div class="prog-header">
    <div class="badge-row">
      <?php if ($isOneOff): ?>
        <span class="badge badge-amber">Special Event</span>
      <?php elseif ($program['day_of_week']): ?>
        <span class="badge badge-tan">Every <?= e($program['day_of_week']) ?></span>
      <?php endif; ?>
      <?php if ($program['language']): ?>
        <span class="badge badge-blue"><?= e($program['language']) ?></span>
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

  <!-- Local time (JS-computed from ET) -->
  <?php if ($hasTimes || ($isOneOff && $program['event_time'])): ?>
  <div class="times-section">
    <div class="section-label">Program Time</div>
    <div class="local-time" id="prog-local-time">—</div>
  </div>
  <script>
    (function () {
      var isOneOff  = <?= json_encode($isOneOff) ?>;
      var dayOfWeek = <?= json_encode($program['day_of_week'] ?? '') ?>;
      var timeEst   = <?= json_encode($program['time_est']   ?? '') ?>;
      var eventDate = <?= json_encode($program['event_date'] ?? '') ?>;
      var eventTime = <?= json_encode($program['event_time'] ?? '') ?>;
      var el        = document.getElementById('prog-local-time');

      function parseTime(s) {
        var m12 = s.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
        var m24 = s.match(/^(\d{1,2}):(\d{2})$/);
        if (!m12 && !m24) return null;
        var h = 0, min = 0;
        if (m12) {
          h = parseInt(m12[1]); min = parseInt(m12[2]);
          if (m12[3].toUpperCase() === 'PM' && h !== 12) h += 12;
          if (m12[3].toUpperCase() === 'AM' && h === 12) h = 0;
        } else { h = parseInt(m24[1]); min = parseInt(m24[2]); }
        return { h: h, min: min };
      }

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
        var etHour  = etHourRaw === 24 ? 0 : etHourRaw;
        var offsetH = parsed.h - etHour;
        if (offsetH > 12)  offsetH -= 24;
        if (offsetH < -12) offsetH += 24;
        return new Date(probe.getTime() + offsetH * 3600000);
      }

      try {
        var utcDate;
        if (isOneOff && eventDate && eventTime) {
          var parsed = parseTime(eventTime);
          utcDate = etToUtc(new Date(eventDate + 'T12:00:00').toLocaleDateString('en-US', { weekday: 'long' }), parsed);
        } else if (dayOfWeek && timeEst) {
          utcDate = etToUtc(dayOfWeek, parseTime(timeEst));
        }
        if (!utcDate) { el.textContent = '—'; return; }

        var dayFull = new Intl.DateTimeFormat('en-US', { weekday: 'long' }).format(utcDate);
        var time    = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).format(utcDate);
        var tzAbbr  = new Intl.DateTimeFormat('en-US', { timeZoneName: 'short' })
                        .formatToParts(utcDate).find(function(p){ return p.type === 'timeZoneName'; });
        el.innerHTML = (isOneOff ? '' : 'Every ') + dayFull + ' &middot; ' + time
          + (tzAbbr ? '<span class="local-tz">' + tzAbbr.value + '</span>' : '');
      } catch (err) { el.textContent = '—'; }
    })();
  </script>
  <?php endif; ?>

  <!-- Join buttons -->
  <div class="buttons-section">
    <?php if ($hasZoom): ?>
    <a href="<?= e($program['zoom_url']) ?>"
       target="_blank" rel="noopener noreferrer"
       class="join-btn btn-zoom">
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

<?php endif;

share_page_close();
