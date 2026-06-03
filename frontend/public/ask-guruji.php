<?php
/**
 * ask-guruji.php — "Ask Guruji" standalone form page
 * URL: gokulbhavan.org/ask-guruji.php
 *
 * Two-step flow:
 *   Step 1 — Find your registered profile (name or email search)
 *   Step 2 — Type your question (submit enabled only after profile confirmed)
 */

require_once __DIR__ . '/api/config.php';
$siteKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ask Guruji — Gokul Bhavan</title>
  <meta name="description" content="Submit a spiritual question to Guruji at Gokul Bhavan Gauḍīya Maṭha." />
  <meta name="robots" content="noindex" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Manrope:wght@700;800&display=swap" rel="stylesheet" />

  <?php if ($siteKey): ?>
  <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($siteKey, ENT_QUOTES) ?>"></script>
  <?php endif; ?>

  <style>
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
      padding: 24px 16px 64px;
    }

    .card {
      width: 100%;
      max-width: 480px;
      background: #fff;
      border: 1px solid #EADDB7;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 4px 32px rgba(8, 42, 74, 0.10);
    }

    /* ── Guruji photo ──────────────────────────────────── */
    .guruji-photo-wrap {
      display: flex;
      justify-content: center;
      padding: 28px 0 4px;
    }
    .guruji-photo {
      width: 96px;
      height: 96px;
      border-radius: 50%;
      object-fit: cover;
      object-position: center top;
      border: 3px solid #EADDB7;
      box-shadow: 0 2px 12px rgba(8, 42, 74, 0.15);
    }

    /* ── Card body ─────────────────────────────────────── */
    .card-body { padding: 20px 24px 28px; }

    .card-title {
      font-family: 'Manrope', sans-serif;
      font-weight: 800;
      font-size: clamp(20px, 5.5vw, 26px);
      color: #082A4A;
      margin-bottom: 6px;
    }
    .card-subtitle {
      font-size: 13px;
      color: #2A506A;
      line-height: 1.55;
      margin-bottom: 20px;
    }

    /* ── Step labels ───────────────────────────────────── */
    .step-label {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #8a9eb0;
      margin-bottom: 10px;
    }

    /* ── Form fields ───────────────────────────────────── */
    .field { margin-bottom: 14px; }
    label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #2A506A;
      margin-bottom: 5px;
    }
    label .req { color: #C0392B; margin-left: 2px; }
    input[type="text"], input[type="search"], textarea {
      width: 100%;
      padding: 10px 12px;
      font-family: inherit;
      font-size: 15px;
      color: #082A4A;
      background: #FAFAF7;
      border: 1px solid #EADDB7;
      border-radius: 8px;
      outline: none;
      transition: border-color 0.15s, box-shadow 0.15s;
      -webkit-appearance: none;
    }
    input:focus, textarea:focus {
      border-color: #2A506A;
      box-shadow: 0 0 0 3px rgba(42, 80, 106, 0.12);
    }
    textarea {
      resize: vertical;
      min-height: 120px;
      line-height: 1.55;
    }

    /* ── Search results ────────────────────────────────── */
    #search-results { margin-top: 8px; }

    .result-card {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding: 10px 14px;
      border: 1px solid #EADDB7;
      border-radius: 10px;
      margin-bottom: 8px;
      cursor: pointer;
      transition: border-color 0.15s, background 0.15s;
    }
    .result-card:hover { border-color: #2A506A; background: #f4f8fb; }
    .result-card .rc-name {
      font-weight: 600;
      font-size: 14px;
      color: #082A4A;
    }
    .result-card .rc-loc {
      font-size: 12px;
      color: #2A506A;
      margin-top: 2px;
    }
    .result-card .btn-select {
      flex-shrink: 0;
      font-size: 12px;
      font-weight: 700;
      color: #2A506A;
      border: 1px solid #2A506A;
      border-radius: 999px;
      padding: 4px 12px;
      background: none;
      cursor: pointer;
      transition: background 0.15s, color 0.15s;
    }
    .result-card:hover .btn-select,
    .result-card .btn-select:hover {
      background: #2A506A;
      color: #fff;
    }

    .no-results {
      font-size: 13px;
      color: #2A506A;
      padding: 10px 0 4px;
    }

    /* ── Confirmed profile chip ────────────────────────── */
    .profile-chip {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      background: #ecfdf5;
      border: 1px solid #a7f3d0;
      border-radius: 10px;
      padding: 10px 14px;
      margin-bottom: 16px;
    }
    .profile-chip .chip-name {
      font-weight: 700;
      font-size: 14px;
      color: #065f46;
    }
    .profile-chip .chip-loc {
      font-size: 12px;
      color: #047857;
      margin-top: 1px;
    }
    .profile-chip .btn-change {
      flex-shrink: 0;
      font-size: 12px;
      font-weight: 600;
      color: #047857;
      background: none;
      border: none;
      cursor: pointer;
      text-decoration: underline;
      padding: 0;
    }

    /* ── Register prompt ───────────────────────────────── */
    .register-prompt {
      display: none;
      align-items: flex-start;
      gap: 12px;
      background: #fef9ec;
      border: 1px solid #EADDB7;
      border-radius: 10px;
      padding: 12px 14px;
      margin-top: 10px;
    }
    .register-prompt.visible { display: flex; }
    .register-prompt p {
      font-size: 13px;
      color: #2A506A;
      line-height: 1.5;
      flex: 1;
    }
    .btn-register {
      flex-shrink: 0;
      display: inline-block;
      background: #E8A207;
      color: #082A4A;
      font-family: 'Manrope', sans-serif;
      font-weight: 800;
      font-size: 13px;
      padding: 8px 16px;
      border-radius: 999px;
      text-decoration: none;
      transition: background 0.15s;
    }
    .btn-register:hover { background: #cf8f06; }

    /* ── Submit button ─────────────────────────────────── */
    .btn-submit {
      width: 100%;
      padding: 13px 20px;
      background: #2A506A;
      color: #fff;
      font-family: 'Manrope', sans-serif;
      font-weight: 800;
      font-size: 15px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      transition: background 0.15s, transform 0.1s;
      margin-top: 6px;
    }
    .btn-submit:hover:not(:disabled) { background: #082A4A; }
    .btn-submit:active:not(:disabled) { transform: scale(0.98); }
    .btn-submit:disabled { opacity: 0.45; cursor: not-allowed; }

    /* ── Status messages ───────────────────────────────── */
    .msg {
      margin-top: 12px;
      padding: 11px 13px;
      border-radius: 8px;
      font-size: 13px;
      line-height: 1.5;
      display: none;
    }
    .msg.error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .msg.visible { display: block; }

    /* ── Thank-you ─────────────────────────────────────── */
    #thank-you {
      text-align: center;
      padding: 32px 24px 36px;
      display: none;
    }
    #thank-you.visible { display: block; }
    #thank-you .ty-icon { font-size: 48px; margin-bottom: 12px; }
    #thank-you h2 {
      font-family: 'Manrope', sans-serif;
      font-weight: 800;
      font-size: 22px;
      color: #082A4A;
      margin-bottom: 10px;
    }
    #thank-you p { font-size: 14px; color: #2A506A; line-height: 1.6; margin-bottom: 20px; }
    #thank-you a { color: #2A506A; font-size: 13px; font-weight: 600; text-decoration: underline; }

    /* ── Footer ────────────────────────────────────────── */
    .footer-note {
      margin-top: 16px;
      text-align: center;
      font-size: 11px;
      color: #8a9eb0;
      max-width: 480px;
    }
    .footer-note a { color: inherit; text-decoration: underline; }
    .grecaptcha-badge { visibility: hidden; }
  </style>
</head>
<body>

  <div class="card" id="main-card">

    <div class="guruji-photo-wrap">
      <img src="/assets/guruji.png" alt="Guruji" class="guruji-photo" />
    </div>

    <div class="card-body" id="form-section">
      <h1 class="card-title">Ask Guruji</h1>
      <p class="card-subtitle">
        Please use this form to submit questions related to sādhana, previous Harikathā, scripture, or devotional practice.
        <strong>Please submit sincere and relevant questions only.</strong>
      </p>

      <!-- Honeypot -->
      <div style="position:absolute;left:-9999px;opacity:0;pointer-events:none" aria-hidden="true" tabindex="-1">
        <input type="text" name="website" id="website" autocomplete="off" tabindex="-1" />
      </div>

      <!-- ── Step 1: Find profile ─────────────────────── -->
      <div id="step-lookup">
        <p class="step-label">Step 1 — Find your profile</p>

        <div class="field">
          <label for="member-search">Your Name or Email <span class="req">*</span></label>
          <input type="search" id="member-search" autocomplete="off"
                 placeholder="e.g. Priya or priya@example.com" />
        </div>

        <div id="search-results"></div>

        <div id="register-prompt" class="register-prompt">
          <p>Not registered yet? Please register as a sanga member first.</p>
          <a href="/register" class="btn-register">Register</a>
        </div>
      </div>

      <!-- ── Step 2: Question ─────────────────────────── -->
      <div id="step-question" style="display:none">
        <div id="profile-chip" class="profile-chip">
          <div>
            <div class="chip-name" id="chip-name"></div>
            <div class="chip-loc"  id="chip-loc"></div>
          </div>
          <button type="button" class="btn-change" id="btn-change-profile">Change</button>
        </div>

        <p class="step-label">Step 2 — Your Question</p>

        <div class="field">
          <label for="question">Question <span class="req">*</span></label>
          <textarea id="question" name="question"
                    placeholder="Guruji, I have been wondering about…" maxlength="3000"></textarea>
        </div>

        <div id="form-error" class="msg error"></div>

        <button type="button" class="btn-submit" id="submit-btn" disabled>
          Submit Question
        </button>
      </div>

    </div>

    <!-- Thank-you -->
    <div id="thank-you">
      <div class="ty-icon">🙏</div>
      <h2>Hare Krsna!</h2>
      <p>Your question has been submitted to Guruji!</p>
      <a href="/">Return to Gokul Bhavan</a>
    </div>

  </div>

  <p class="footer-note">
    This site is protected by reCAPTCHA —
    <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy</a> &amp;
    <a href="https://policies.google.com/terms"   target="_blank" rel="noopener">Terms</a> apply.
  </p>

<script>
(function () {
  var SITE_KEY = <?= json_encode($siteKey) ?>;

  // State
  var selectedMember = null; // { id, display_name, city, country }
  var searchTimer    = null;

  // Elements
  var memberSearch    = document.getElementById('member-search');
  var searchResults   = document.getElementById('search-results');
  var registerPrompt  = document.getElementById('register-prompt');
  var stepLookup      = document.getElementById('step-lookup');
  var stepQuestion    = document.getElementById('step-question');
  var profileChip     = document.getElementById('profile-chip');
  var chipName        = document.getElementById('chip-name');
  var chipLoc         = document.getElementById('chip-loc');
  var btnChange       = document.getElementById('btn-change-profile');
  var questionEl      = document.getElementById('question');
  var submitBtn       = document.getElementById('submit-btn');
  var formError       = document.getElementById('form-error');
  var formSection     = document.getElementById('form-section');
  var thankYou        = document.getElementById('thank-you');

  // ── Search ────────────────────────────────────────────
  memberSearch.addEventListener('input', function () {
    clearTimeout(searchTimer);
    var q = memberSearch.value.trim();
    searchResults.innerHTML = '';
    registerPrompt.classList.remove('visible');

    if (q.length < 3) return;

    searchTimer = setTimeout(function () { doSearch(q); }, 350);
  });

  function doSearch(q) {
    searchResults.innerHTML = '<p style="font-size:13px;color:#8a9eb0;padding:6px 0">Searching…</p>';
    fetch('/api/lookup-member.php?q=' + encodeURIComponent(q))
      .then(function (r) { return r.json(); })
      .then(function (rows) {
        searchResults.innerHTML = '';
        if (!Array.isArray(rows) || rows.length === 0) {
          searchResults.innerHTML = '<p class="no-results">No registered profile found.</p>';
          registerPrompt.classList.add('visible');
          return;
        }
        rows.forEach(function (m) {
          var loc = [m.city, m.country].filter(Boolean).join(', ');
          var nameLine = m.spiritual_name
            ? esc(m.spiritual_name) + '<span style="font-weight:400;color:#2A506A;margin-left:6px;font-size:12px">(' + esc(m.display_name) + ')</span>'
            : esc(m.display_name);
          var card = document.createElement('div');
          card.className = 'result-card';
          card.innerHTML =
            '<div>' +
              '<div class="rc-name">' + nameLine + '</div>' +
              (loc ? '<div class="rc-loc">' + esc(loc) + '</div>' : '') +
            '</div>' +
            '<button class="btn-select" type="button">This is me</button>';
          card.addEventListener('click', function () { selectMember(m); });
          searchResults.appendChild(card);
        });
      })
      .catch(function () {
        searchResults.innerHTML = '<p class="no-results">Search unavailable. Please try again.</p>';
      });
  }

  function selectMember(m) {
    selectedMember = m;
    var loc = [m.city, m.country].filter(Boolean).join(', ');
    chipName.textContent = m.spiritual_name || m.display_name;
    chipLoc.textContent  = (m.spiritual_name ? m.display_name + (loc ? ' · ' + loc : '') : loc) || '';

    stepLookup.style.display   = 'none';
    stepQuestion.style.display = 'block';
    submitBtn.disabled         = false;
    questionEl.focus();
  }

  btnChange.addEventListener('click', function () {
    selectedMember             = null;
    stepQuestion.style.display = 'none';
    stepLookup.style.display   = 'block';
    submitBtn.disabled         = true;
    questionEl.value           = '';
    clearError();
    memberSearch.value         = '';
    searchResults.innerHTML    = '';
    registerPrompt.classList.remove('visible');
    memberSearch.focus();
  });

  // ── Enable submit only when question has content ──────
  questionEl.addEventListener('input', function () {
    submitBtn.disabled = (questionEl.value.trim().length < 10) || !selectedMember;
  });

  // ── Submit ────────────────────────────────────────────
  submitBtn.addEventListener('click', function () {
    clearError();

    var question = questionEl.value.trim();
    var honeypot = document.getElementById('website').value;

    if (!selectedMember) {
      showError('Please find your profile first.');
      return;
    }
    if (question.length < 10) {
      showError('Please write your question (at least 10 characters).');
      return;
    }

    submitBtn.disabled       = true;
    submitBtn.textContent    = 'Submitting…';

    function doSubmit(token) {
      fetch('/api/ask-guruji.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          registration_id: selectedMember.id,
          question:        question,
          recaptcha:       token,
          website:         honeypot
        })
      })
      .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
      .then(function (r) {
        if (r.ok) {
          formSection.style.display = 'none';
          thankYou.classList.add('visible');
        } else {
          showError(r.data.message || 'Something went wrong. Please try again.');
          submitBtn.disabled    = false;
          submitBtn.textContent = 'Submit Question';
        }
      })
      .catch(function () {
        showError('Network error. Please check your connection and try again.');
        submitBtn.disabled    = false;
        submitBtn.textContent = 'Submit Question';
      });
    }

    if (SITE_KEY && window.grecaptcha) {
      grecaptcha.ready(function () {
        grecaptcha.execute(SITE_KEY, { action: 'ask_guruji' }).then(doSubmit);
      });
    } else {
      doSubmit('');
    }
  });

  function showError(msg) {
    formError.textContent = msg;
    formError.classList.add('visible');
    formError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function clearError() {
    formError.textContent = '';
    formError.classList.remove('visible');
  }
  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

})();
</script>

</body>
</html>
