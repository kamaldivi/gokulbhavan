<?php
/**
 * daily-selections.php — "Today's Selections" interactive share page
 * URL: gokulbhavan.org/share/daily-selections.php
 *
 * Fully interactive: bhajan/sankirtan play via HTML5 audio, sloka shows
 * lyrics + meaning inline, video embeds YouTube iframe.
 * OG tags provide rich WhatsApp / iMessage link preview.
 */

require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../api/config.php';

$siteBase  = 'https://gokulbhavan.org';
$mediaBase = $siteBase;   // audio paths are relative to web root, e.g. media/audio/...

// ── Determine "today" using the same 3am boundary as the API ─────────────────
$hour  = (int) date('H');
$today = $hour >= 3 ? date('Y-m-d') : date('Y-m-d', strtotime('-1 day'));

// ── Fetch all selections ──────────────────────────────────────────────────────
$sel        = [];   // keyed by type
$fetchError = false;

try {
    $db = get_db();

    $stmt = $db->query("SELECT content_type, ref_id FROM daily_highlight");
    $refs = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $refs[$row['content_type']] = $row['ref_id'];
    }

    // Audio track + category helper
    $audioStmt = $db->prepare("
        SELECT t.track_id, t.track_name, t.singer, a.author_name AS author,
               t.audio_file_path, t.lyrics_file_path,
               ac.category_name AS display_name
        FROM   audio_track t
        JOIN   audio_category ac ON ac.category_code = t.category_code
        LEFT JOIN audio_author a ON a.id = t.author_id
        WHERE  t.track_id = ?
        LIMIT  1
    ");

    // Lyrics helper (English first, fall back to first available)
    $lyrStmt = $db->prepare("
        SELECT content_type, lang, body
        FROM   lyrics
        WHERE  track_id = ?
        ORDER BY (lang = 'en') DESC, lang ASC
    ");

    foreach (['bhajan', 'sloka', 'sankirtan'] as $type) {
        if (!isset($refs[$type])) continue;
        $audioStmt->execute([$refs[$type]]);
        $row = $audioStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;

        $sel[$type] = $row;

        // Fetch lyrics/meaning for sloka (and bhajan if available)
        if ($type === 'sloka' || $type === 'bhajan') {
            $lyrStmt->execute([$row['track_id']]);
            $lyrics = $meaning = '';
            while ($lr = $lyrStmt->fetch(PDO::FETCH_ASSOC)) {
                if ($lr['content_type'] === 'lyrics'  && $lyrics  === '') $lyrics  = $lr['body'];
                if ($lr['content_type'] === 'meaning' && $meaning === '') $meaning = $lr['body'];
            }
            $sel[$type]['lyrics']  = $lyrics;
            $sel[$type]['meaning'] = $meaning;
        }
    }

    // Video
    if (isset($refs['video'])) {
        $vStmt = $db->prepare("
            SELECT video_id, video_title AS title, thumbnail_url
            FROM   video WHERE video_id = ? LIMIT 1
        ");
        $vStmt->execute([$refs['video']]);
        $row = $vStmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $sel['video'] = $row;
    }

} catch (Throwable $e) {
    $fetchError = true;
}

// ── OG values ─────────────────────────────────────────────────────────────────
$dateLabel = date('M j, Y', strtotime($today));

// Static title — og:image carries the visual; no description to keep preview clean.
$ogTitle = 'Gokul Bhavan Daily Sadhana';
$ogDesc  = '';
$ogImage  = '';
//$ogImage = $siteBase . '/assets/share/og-sadhana.jpg';
$ogUrl   = $siteBase . '/share/daily-selections.php';

// ── Page CSS ──────────────────────────────────────────────────────────────────
$extraCss = <<<'CSS'
    /* ── Date label ───────────────────────── */
    .date-label {
      font-size: 11px; font-weight: 700;
      letter-spacing: 0.1em; text-transform: uppercase;
      color: #C94277; margin-bottom: 6px;
    }

    /* ── Selection card ───────────────────── */
    .sel-card {
      border: 1px solid #EADDB7;
      border-radius: 16px;
      overflow: hidden;
      margin-bottom: 16px;
    }
    .sel-card:last-child { margin-bottom: 0; }

    /* Card header row */
    .sel-header {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 16px 18px;
    }
    .type-icon {
      width: 38px; height: 38px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .type-icon svg { width: 17px; height: 17px; }
    .icon-bhajan    { background: #fce7f0; }
    .icon-sloka     { background: #fef3c7; }
    .icon-sankirtan { background: #ccfbf1; }
    .icon-video     { background: #dbeafe; }

    .sel-meta-wrap { flex: 1; min-width: 0; }
    .sel-type-label {
      font-size: 10px; font-weight: 700;
      letter-spacing: 0.08em; text-transform: uppercase;
      margin-bottom: 2px;
    }
    .lbl-bhajan    { color: #C94277; }
    .lbl-sloka     { color: #92400e; }
    .lbl-sankirtan { color: #0f766e; }
    .lbl-video     { color: #1d4ed8; }

    .sel-title {
      font-family: 'Manrope', sans-serif;
      font-weight: 700; font-size: 15px;
      color: #082A4A; line-height: 1.3;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .sel-sub {
      font-size: 12px; color: #2A506A; margin-top: 2px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    /* ── Play button ──────────────────────── */
    .play-btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 16px; border-radius: 999px;
      border: none; cursor: pointer;
      font-size: 12px; font-weight: 700;
      transition: opacity 0.15s;
      flex-shrink: 0;
    }
    .play-btn:hover { opacity: 0.85; }
    .play-btn svg { width: 14px; height: 14px; }
    .btn-bhajan    { background: #C94277; color: #fff; }
    .btn-sankirtan { background: #0f766e; color: #fff; }

    /* ── HTML5 audio player ───────────────── */
    .audio-wrap {
      display: none;
      padding: 0 18px 16px;
    }
    .audio-wrap.open { display: block; }
    .audio-wrap audio {
      width: 100%; border-radius: 8px;
      accent-color: #C94277;
    }

    /* ── Sloka lyrics / meaning ───────────── */
    .sloka-body {
      padding: 0 18px 16px;
      border-top: 1px solid #EADDB7;
    }
    .sloka-text {
      font-size: 14px; line-height: 1.625;
      color: #082A4A; white-space: pre-wrap;
      word-break: break-word;
      padding-top: 14px;
    }
    .meaning-toggle {
      display: inline-flex; align-items: center; gap-4px;
      background: none; border: none; cursor: pointer;
      font-size: 12px; font-weight: 700;
      color: #92400e; padding: 10px 0 4px;
      letter-spacing: 0.05em; text-transform: uppercase;
    }
    .meaning-toggle svg { width: 14px; height: 14px; transition: transform 0.2s; }
    .meaning-toggle.open svg { transform: rotate(180deg); }
    .meaning-text {
      display: none;
      font-size: 13px; line-height: 1.625;
      color: #2A506A; white-space: pre-wrap;
      word-break: break-word;
      padding-top: 8px; border-top: 1px solid #EADDB7;
    }

    /* ── Bhajan lyrics (shown inside audio-wrap) ──────────────── */
    .audio-lyrics {
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px solid #EADDB7;
      font-size: 13px; line-height: 1.625;
      color: #082A4A; white-space: pre-wrap;
      word-break: break-word;
    }
    .audio-meaning-toggle {
      display: inline-flex; align-items: center; gap: 4px;
      background: none; border: none; cursor: pointer;
      font-size: 12px; font-weight: 700; color: #C94277;
      padding: 10px 0 4px;
      letter-spacing: 0.05em; text-transform: uppercase;
    }
    .audio-meaning-toggle svg { width: 14px; height: 14px; transition: transform 0.2s; }
    .audio-meaning-toggle.open svg { transform: rotate(180deg); }
    .audio-meaning {
      display: none;
      font-size: 13px; line-height: 1.625;
      color: #2A506A; white-space: pre-wrap;
      word-break: break-word;
      padding-top: 8px; border-top: 1px solid #EADDB7;
    }
    .audio-meaning.open { display: block; }
    .meaning-text.open { display: block; }

    /* ── YouTube embed ────────────────────── */
    .yt-toggle-btn {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; padding: 12px 18px;
      background: #082A4A; color: #fff;
      border: none; cursor: pointer;
      font-size: 13px; font-weight: 700;
      transition: background 0.15s;
    }
    .yt-toggle-btn:hover { background: #2A506A; }
    .yt-toggle-btn svg { width: 16px; height: 16px; fill: currentColor; }
    .yt-embed-wrap {
      display: none;
      position: relative; padding-bottom: 56.25%; height: 0;
    }
    .yt-embed-wrap.open { display: block; }
    .yt-embed-wrap iframe {
      position: absolute; top: 0; left: 0;
      width: 100%; height: 100%;
      border: none;
    }

    /* ── Visit link ───────────────────────── */
    .visit-link {
      display: block; text-align: center;
      font-size: 13px; font-weight: 600;
      color: #C94277; text-decoration: none;
      padding: 18px 24px;
    }
    .visit-link:hover { text-decoration: underline; }
CSS;

// ── Render ────────────────────────────────────────────────────────────────────
share_page_open(
    "Today's Sadhana",
    $ogTitle, $ogDesc, $ogImage, $ogUrl,
    $extraCss
);
share_logo();
?>

  <!-- Page header -->
  <div class="section">
    <div class="date-label"><?= e($dateLabel) ?></div>
    <h1 class="page-title">Today's Sadhana</h1>
  </div>

<?php if ($fetchError): ?>
  <?php share_error_state(true, null, 'selections', '/', 'Visit Gokul Bhavan'); ?>

<?php elseif (empty($sel)): ?>
  <div style="padding:32px 24px;text-align:center;color:#2A506A;font-size:14px">
    Today's selections haven't been set yet. Please check back soon.<br /><br />
    <a href="/" style="color:#C94277;font-weight:600">Visit Gokul Bhavan →</a>
  </div>

<?php else: ?>

<div class="section" style="border-bottom:none">

  <!-- ── Bhajan ────────────────────────────────────────────────── -->
  <?php if (!empty($sel['bhajan'])): $b = $sel['bhajan']; $hasAudio = !empty($b['audio_file_path']); ?>
  <div class="sel-card">
    <div class="sel-header">
      <div class="type-icon icon-bhajan">
        <svg viewBox="0 0 24 24" fill="#C94277"><path d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z"/></svg>
      </div>
      <div class="sel-meta-wrap">
        <div class="sel-type-label lbl-bhajan">Bhajan · <?= e($b['track_id']) ?></div>
        <div class="sel-title"><?= e($b['track_name']) ?></div>
        <?php $meta = implode(' · ', array_filter([$b['singer'] ?? '', $b['author'] ?? '']));
              if ($meta): ?><div class="sel-sub"><?= e($meta) ?></div><?php endif; ?>
      </div>
      <?php if ($hasAudio): ?>
      <button class="play-btn btn-bhajan" onclick="toggleAudio(this,'audio-bhajan')">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        Play
      </button>
      <?php endif; ?>
    </div>
    <?php if ($hasAudio): ?>
    <div class="audio-wrap" id="audio-bhajan">
      <audio controls preload="none">
        <source src="<?= e($mediaBase . '/' . ltrim($b['audio_file_path'], '/')) ?>" type="audio/mpeg" />
      </audio>
      <?php if (!empty($b['lyrics'])): ?>
      <div class="audio-lyrics"><?= e($b['lyrics']) ?></div>
      <?php endif; ?>
      <?php if (!empty($b['meaning'])): ?>
      <button class="audio-meaning-toggle" onclick="toggleAudioMeaning(this,'bhajan-meaning')">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.59 8.59L12 13.17 7.41 8.59 6 10l6 6 6-6z"/></svg>
        Meaning
      </button>
      <div class="audio-meaning" id="bhajan-meaning"><?= e($b['meaning']) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ── Śloka ─────────────────────────────────────────────────── -->
  <?php if (!empty($sel['sloka'])): $s = $sel['sloka']; $sHasAudio = !empty($s['audio_file_path']); ?>
  <div class="sel-card">
    <div class="sel-header">
      <div class="type-icon icon-sloka">
        <svg viewBox="0 0 24 24" fill="#92400e"><path d="M18 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm-1 16H7v-2h10v2zm0-4H7v-2h10v2zm0-4H7V8h10v2z"/></svg>
      </div>
      <div class="sel-meta-wrap">
        <div class="sel-type-label lbl-sloka">Śloka · <?= e($s['display_name']) ?></div>
        <div class="sel-title"><?= e($s['track_name']) ?></div>
        <div class="sel-sub"><?= e($s['track_id']) ?></div>
      </div>
      <?php if ($sHasAudio): ?>
      <button class="play-btn" style="background:#92400e;color:#fff"
              onclick="toggleAudio(this,'audio-sloka')">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        Play
      </button>
      <?php endif; ?>
    </div>
    <?php if ($sHasAudio): ?>
    <div class="audio-wrap" id="audio-sloka">
      <audio controls preload="none">
        <source src="<?= e($mediaBase . '/' . ltrim($s['audio_file_path'], '/')) ?>" type="audio/mpeg" />
      </audio>
      <?php if (!empty($s['lyrics'])): ?>
      <div class="audio-lyrics"><?= e($s['lyrics']) ?></div>
      <?php endif; ?>
      <?php if (!empty($s['meaning'])): ?>
      <button class="audio-meaning-toggle" onclick="toggleAudioMeaning(this,'sloka-meaning-audio')">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.59 8.59L12 13.17 7.41 8.59 6 10l6 6 6-6z"/></svg>
        Meaning
      </button>
      <div class="audio-meaning" id="sloka-meaning-audio"><?= e($s['meaning']) ?></div>
      <?php endif; ?>
    </div>
    <?php elseif (!empty($s['lyrics']) || !empty($s['meaning'])): ?>
    <div class="sloka-body">
      <?php if (!empty($s['lyrics'])): ?>
      <div class="sloka-text"><?= e($s['lyrics']) ?></div>
      <?php endif; ?>
      <?php if (!empty($s['meaning'])): ?>
      <button class="meaning-toggle" onclick="toggleMeaning(this,'meaning-sloka')">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.59 8.59L12 13.17 7.41 8.59 6 10l6 6 6-6z"/></svg>
        Meaning
      </button>
      <div class="meaning-text" id="meaning-sloka"><?= e($s['meaning']) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ── Nāma Saṅkīrtana ───────────────────────────────────────── -->
  <?php if (!empty($sel['sankirtan'])): $n = $sel['sankirtan']; $hasAudio = !empty($n['audio_file_path']); ?>
  <div class="sel-card">
    <div class="sel-header">
      <div class="type-icon icon-sankirtan">
        <svg viewBox="0 0 24 24" fill="#0f766e"><path d="M12 3a9 9 0 1 0 0 18A9 9 0 0 0 12 3zm-1 14v-4H8l4-8v4h3l-4 8z"/></svg>
      </div>
      <div class="sel-meta-wrap">
        <div class="sel-type-label lbl-sankirtan">Nāma Saṅkīrtana · <?= e($n['track_id']) ?></div>
        <div class="sel-title"><?= e($n['track_name']) ?></div>
      </div>
      <?php if ($hasAudio): ?>
      <button class="play-btn btn-sankirtan" onclick="toggleAudio(this,'audio-sankirtan')">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        Play
      </button>
      <?php endif; ?>
    </div>
    <?php if ($hasAudio): ?>
    <div class="audio-wrap" id="audio-sankirtan">
      <audio controls preload="none">
        <source src="<?= e($mediaBase . '/' . ltrim($n['audio_file_path'], '/')) ?>" type="audio/mpeg" />
      </audio>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ── Video (Harikatha) ─────────────────────────────────────── -->
  <?php if (!empty($sel['video'])): $v = $sel['video']; ?>
  <div class="sel-card">
    <div class="sel-header">
      <div class="type-icon icon-video">
        <svg viewBox="0 0 24 24" fill="#1d4ed8"><path d="M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4v-11l-4 4z"/></svg>
      </div>
      <div class="sel-meta-wrap">
        <div class="sel-type-label lbl-video">Harikatha · Video</div>
        <div class="sel-title"><?= e($v['title']) ?></div>
      </div>
    </div>
    <button class="yt-toggle-btn" onclick="toggleVideo(this,'yt-<?= e($v['video_id']) ?>')"
            data-vid="<?= e($v['video_id']) ?>">
      <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
      Watch Video
    </button>
    <div class="yt-embed-wrap" id="yt-<?= e($v['video_id']) ?>"></div>
  </div>
  <?php endif; ?>

</div>

  <a class="visit-link" href="/"><< Gokul Bhavan Home</a>

<?php endif;

share_page_close();
?>

<script>
// ── Audio toggle ─────────────────────────────────────────────────
var PLAY_SVG  = '<svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px"><path d="M8 5v14l11-7z"/></svg> Play';
var PAUSE_SVG = '<svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg> Pause';

function pauseYouTube() {
  var ytIframe = document.querySelector('.yt-embed-wrap iframe');
  if (ytIframe) {
    ytIframe.contentWindow.postMessage(
      '{"event":"command","func":"pauseVideo","args":""}', '*'
    );
  }
}

function stopAllAudio(exceptWrapperId) {
  document.querySelectorAll('.audio-wrap.open').forEach(function(wrap) {
    if (wrap.id === exceptWrapperId) return;
    var a = wrap.querySelector('audio');
    if (a) a.pause();
    wrap.classList.remove('open');
    // Reset the corresponding play button
    var card = wrap.closest('.sel-card');
    if (card) {
      var playBtn = card.querySelector('.play-btn');
      if (playBtn) playBtn.innerHTML = PLAY_SVG;
    }
  });
  pauseYouTube();
}

function toggleAudio(btn, wrapperId) {
  var wrap  = document.getElementById(wrapperId);
  var audio = wrap.querySelector('audio');
  var open  = wrap.classList.toggle('open');
  btn.innerHTML = open ? PAUSE_SVG : PLAY_SVG;
  if (open) {
    stopAllAudio(wrapperId);
    audio.play();
  } else {
    audio.pause();
  }
}

// ── Bhajan meaning toggle (inside audio panel) ──────────────────
function toggleAudioMeaning(btn, id) {
  var el   = document.getElementById(id);
  var open = el.classList.toggle('open');
  btn.classList.toggle('open', open);
}

// ── Meaning toggle ───────────────────────────────────────────────
function toggleMeaning(btn, id) {
  var el = document.getElementById(id);
  var open = el.classList.toggle('open');
  btn.classList.toggle('open', open);
  btn.querySelector('span') && (btn.querySelector('span').textContent = open ? 'Hide Meaning' : 'Meaning');
}

// ── YouTube embed (lazy — only loads iframe on click) ────────────
function toggleVideo(btn, wrapperId) {
  var wrap = document.getElementById(wrapperId);
  if (wrap.classList.contains('open')) return; // already open
  stopAllAudio(null);   // pause any playing audio first
  var vid = btn.dataset.vid;
  wrap.innerHTML = '<iframe src="https://www.youtube.com/embed/' + vid
    + '?autoplay=1&rel=0&enablejsapi=1" allowfullscreen allow="autoplay; encrypted-media"></iframe>';
  wrap.classList.add('open');
  btn.style.display = 'none';
}
</script>
