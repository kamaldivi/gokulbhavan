<?php
/**
 * ekadasi.php — Ekādaśī date share page with WhatsApp link preview
 * URL: gokulbhavan.org/share/ekadasi/2025-06-15
 *
 * Server-side: validates date, calls vcalendar API for the Ekādaśī name
 *   so OG meta tags are meaningful (WhatsApp does not execute JS).
 * Client-side: 3-tier location detection → fetches location-specific
 *   fasting times (sunrise, sunset, break-fast) via JS.
 */

require_once __DIR__ . '/_layout.php';

define('VCAL_API',           'https://purebhaktibase.com:8443/api/v1/vcalendar');
// Adjust to any valid location_id from the vcalendar API.
// Used only for the server-side OG-tag fetch (to get the Ekādaśī name).
define('VCAL_DEFAULT_LOC_ID', 1);

// ── Input validation ──────────────────────────────────────────────────────────
$eventIdRaw = $_GET['id'] ?? '';
$eventId    = (preg_match('/^\d+$/', $eventIdRaw) && (int)$eventIdRaw > 0) ? (int)$eventIdRaw : 0;
$siteBase   = 'https://gokulbhavan.org';

// ── Server-side API call to get Ekādaśī name and date for OG tags ────────────
// This runs once when the page is first loaded by the WhatsApp crawler.
// Falls back gracefully if the API is unreachable.
$ekadaśīName   = '';
$dateFormatted = '';
if ($eventId && function_exists('curl_init')) {
    $apiUrl = VCAL_API . '/events/by-event-id?' . http_build_query([
        'event_id'    => $eventId,
        'location_id' => VCAL_DEFAULT_LOC_ID,
    ]);
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $json = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($json && $code === 200) {
        $response = json_decode($json, true);
        // Response wraps data: { event_id, count, entries: [ {...} ] }
        $entry = (isset($response['entries'][0]) && is_array($response['entries'][0]))
            ? $response['entries'][0]
            : (is_array($response) ? $response : []);
        if (!empty($entry['ekadasi_name'])) {
            $ekadaśīName = $entry['ekadasi_name'];
        }
        if (!empty($entry['entry_date'])) {
            try {
                $dt            = new DateTime($entry['entry_date']);
                $dateFormatted = $dt->format('l, F j, Y');
            } catch (Exception $e) {
                $dateFormatted = $entry['entry_date'];
            }
        }
    }
}

// ── OG values ─────────────────────────────────────────────────────────────────
// Static title keeps WhatsApp preview clean; no description so only the
// cover image is shown. og:image is essential to suppress the YouTube
// video thumbnail that would otherwise appear from the harikatha URL.
$ogTitle = 'Gokul Bhavan Ekādaśī Notification';
$ogDesc  = '';
//$ogImage = $siteBase . '/assets/share/og-ekadasi.jpg';
$ogImage = '';
$ogUrl   = $eventId ? $siteBase . '/share/ekadasi.php?id=' . $eventId : $siteBase . '/share/ekadasi.php';

// ── Page-specific CSS ─────────────────────────────────────────────────────────
$extraCss = <<<'CSS'
    /* Location selector row */
    .loc-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      margin-top: 10px;
    }
    .loc-select {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid #EADDB7;
      border-radius: 10px;
      font-size: 13px;
      color: #082A4A;
      background: #fff;
      appearance: none;
      -webkit-appearance: none;
    }
    .loc-select:disabled { opacity: 0.5; cursor: not-allowed; }
    .loc-select:focus    { outline: none; border-color: #C94277; box-shadow: 0 0 0 2px #fce7f0; }

    .detect-status {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: #2A506A;
      min-height: 20px;
      margin-bottom: 4px;
    }

    /* Ekādaśī info card */
    .ek-card {
      background: #fdf2f8;
      border: 1px solid rgba(201, 66, 119, 0.2);
      border-radius: 12px;
      padding: 14px 16px;
      margin-top: 8px;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    .ek-date      { font-weight: 700; color: #082A4A; font-size: 15px; line-height: 1.3; }
    .ek-times     { font-size: 12px; color: #2A506A; }
    .ek-tithi     { font-size: 12px; color: #2A506A; }
    .ek-breakfast { font-weight: 700; color: #082A4A; font-size: 13px; margin-top: 6px; }

    /* Event items */
    .event-list {
      list-style: none;
      padding: 0;
      margin: 0;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .event-item {
      font-size: 13px;
      color: #082A4A;
      padding: 6px 10px;
      background: #FFF7DF;
      border-radius: 8px;
      line-height: 1.4;
    }

    /* Calendar link */
    .cal-link {
      display: block;
      text-align: center;
      font-size: 13px;
      font-weight: 600;
      color: #C94277;
      text-decoration: none;
      padding: 16px 24px;
    }
    .cal-link:hover { text-decoration: underline; }
CSS;

// ── Render page ───────────────────────────────────────────────────────────────
share_page_open(
    $dateFormatted ?: 'Ekādaśī',
    $ogTitle, $ogDesc, $ogImage, $ogUrl,
    $extraCss
);
share_logo();

if (!$eventId): ?>

  <?php share_error_state(false, '', 'date', '/vcalendar', 'View Vaiṣṇava Calendar'); ?>

<?php else: ?>

  <!-- Header (date badge populated by JS after location resolves) -->
  <div class="section">
    <div class="badge-row">
      <span class="badge badge-lotus">Ekādaśī</span>
      <!-- Date shown here as JS-disabled fallback only; JS hides this and shows location-specific date in timing section -->
      <span class="badge badge-tan" id="header-date-fallback"><?= e($dateFormatted) ?></span>
    </div>
    <h1 class="page-title" id="ekadasi-name">
      <?= $ekadaśīName ? e($ekadaśīName) : 'Ekādaśī Observance' ?>
    </h1>
  </div>

  <!-- Location detection -->
  <div class="section">
    <div class="section-label">Your Location</div>
    <div class="detect-status">
      <span class="spinner" id="detect-spinner"></span>
      <span id="detect-msg">Detecting your location…</span>
    </div>
    <div class="loc-row">
      <select id="country-sel" class="loc-select" disabled>
        <option value="">Country…</option>
      </select>
      <select id="city-sel" class="loc-select" disabled>
        <option value="">City…</option>
      </select>
    </div>
  </div>

  <!-- Fasting times (populated by JS) -->
  <div class="section" id="timing-section">
    <div class="section-label">Ekādaśī Date for Your Location</div>
    <div id="timing-loading" class="detect-status">
      <span class="spinner"></span>
      <span>Select your location to see fasting times…</span>
    </div>
    <div id="timing-rows" hidden></div>
  </div>

  <!-- Event items (populated by JS, hidden until data loads) -->
  <div class="section" id="events-section" hidden>
    <div class="section-label">Observances</div>
    <ul class="event-list" id="events-list"></ul>
  </div>

  <!-- Ekādaśī guidance -->
  <?php
    $guidanceFile = __DIR__ . '/ekadasi-guidance.json';
    $guidance     = null;
    if (file_exists($guidanceFile)) {
        $decoded = json_decode(file_get_contents($guidanceFile), true);
        if (is_array($decoded)) $guidance = $decoded;
    }
    if ($guidance):
  ?>
  <div class="section" id="guidance-section">
    <div class="section-label">Ekādaśī Guidance</div>
    <?php foreach ($guidance['paragraphs'] as $para): ?>
      <p class="body-text" style="margin-bottom:10px"><?= e($para) ?></p>
    <?php endforeach; ?>
    <?php if (!empty($guidance['harikathaUrl'])): ?>
      <a href="<?= e($guidance['harikathaUrl']) ?>"
         target="_blank" rel="noopener noreferrer"
         class="body-text" style="color:#C94277;font-weight:600;word-break:break-all"><?= e($guidance['harikathaUrl']) ?></a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Link to full calendar -->
  <a class="cal-link" href="/vcalendar">View Full Vaiṣṇava Calendar →</a>

<?php endif;

share_page_close();
?>

<?php if ($eventId): ?>
<script>
(function () {
  'use strict';

  var VCAL     = <?= json_encode(VCAL_API) ?>;
  var EVENT_ID = <?= json_encode($eventId) ?>;

  // ISO 3166-1 alpha-2 → vcalendar country name (matches vcalendar.astro)
  var ISO_COUNTRY = {
    US: 'USA', GB: 'UK', TT: 'Trinidad-Tobago', MK: 'Macedonia',
    CI: 'Ivory Coast', DO: 'Dominican Rep.', SR: 'Surinam', KR: 'South Korea',
  };

  // IANA timezone → vcalendar country (unambiguous timezones only)
  var TZ_COUNTRY = {
    'Asia/Kolkata': 'India', 'Africa/Lagos': 'Nigeria',
    'America/New_York': 'USA', 'America/Chicago': 'USA',
    'America/Denver': 'USA', 'America/Los_Angeles': 'USA',
    'America/Phoenix': 'USA', 'America/Anchorage': 'USA',
    'America/Honolulu': 'USA', 'America/Indiana/Indianapolis': 'USA',
    'America/Indiana/Knox': 'USA', 'America/Indiana/Marengo': 'USA',
    'America/Indiana/Petersburg': 'USA', 'America/Indiana/Tell_City': 'USA',
    'America/Indiana/Vevay': 'USA', 'America/Indiana/Vincennes': 'USA',
    'America/Indiana/Winamac': 'USA', 'America/Kentucky/Louisville': 'USA',
    'America/Kentucky/Monticello': 'USA', 'America/North_Dakota/Beulah': 'USA',
    'America/North_Dakota/Center': 'USA', 'America/North_Dakota/New_Salem': 'USA',
    'America/Adak': 'USA', 'America/Metlakatla': 'USA', 'America/Nome': 'USA',
    'America/Sitka': 'USA', 'America/Yakutat': 'USA', 'Pacific/Honolulu': 'USA',
    'Europe/London': 'UK', 'Europe/Berlin': 'Germany', 'Europe/Madrid': 'Spain',
    'Europe/Stockholm': 'Sweden', 'Europe/Kiev': 'Ukraine', 'Europe/Kyiv': 'Ukraine',
    'Europe/Vilnius': 'Lithuania', 'Europe/Warsaw': 'Poland',
    'Asia/Tokyo': 'Japan', 'Asia/Singapore': 'Singapore', 'Asia/Colombo': 'Sri Lanka',
    'Africa/Accra': 'Ghana', 'Africa/Nairobi': 'Kenya', 'Africa/Lusaka': 'Zambia',
    'Africa/Harare': 'Zimbabwe', 'Africa/Freetown': 'Sierra Lanka',
    'Africa/Kampala': 'Uganda', 'Africa/Douala': 'Cameroon',
  };

  var spinnerEl      = document.getElementById('detect-spinner');
  var detectMsg      = document.getElementById('detect-msg');
  var countryEl      = document.getElementById('country-sel');
  var cityEl         = document.getElementById('city-sel');
  var timingLoad     = document.getElementById('timing-loading');
  var timingRows     = document.getElementById('timing-rows');
  var headerFallback = document.getElementById('header-date-fallback');
  var evSection      = document.getElementById('events-section');
  var evList         = document.getElementById('events-list');

  var countries = [];
  var DEFAULT_LOC = { id: 490, country: 'USA', label: 'Washington, DC' };

  // ── Helpers ──────────────────────────────────────────────────
  function setMsg(msg, spinning) {
    detectMsg.textContent = msg;
    spinnerEl.style.display = spinning ? 'inline-block' : 'none';
  }

  function locLabel(loc) {
    return [loc.city, loc.state_region, loc.country].filter(Boolean).join(', ');
  }

  function to12h(t) {
    if (!t) return '';
    var clean = t.replace(/^\*/, '');
    var parts = clean.split(':');
    var h = parseInt(parts[0], 10), m = parts[1] || '00';
    if (isNaN(h)) return t;
    var ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return h + ':' + m + ' ' + ampm;
  }

  function fetchWithTimeout(url, ms) {
    var ctrl = new AbortController();
    var tid = setTimeout(function() { ctrl.abort(); }, ms || 5000);
    return fetch(url, { signal: ctrl.signal }).finally(function() { clearTimeout(tid); });
  }

  // ── Data loading ──────────────────────────────────────────────
  function loadCountries() {
    return fetchWithTimeout(VCAL + '/countries').then(function(r) { return r.json(); })
      .then(function(data) {
        countries = data.countries || [];
        countryEl.innerHTML = '<option value="">Country…</option>';
        countries.forEach(function(c) {
          var o = document.createElement('option');
          o.value = c.country; o.textContent = c.country;
          countryEl.appendChild(o);
        });
        countryEl.disabled = false;
      });
  }

  function loadCities(country) {
    cityEl.innerHTML = '<option value="">Loading…</option>';
    cityEl.disabled = true;
    return fetchWithTimeout(VCAL + '/locations-by-country?country=' + encodeURIComponent(country))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var locs = data.locations || [];
        cityEl.innerHTML = '<option value="">Select city…</option>';
        locs.forEach(function(l) {
          var o = document.createElement('option');
          o.value = l.id; o.textContent = locLabel(l);
          cityEl.appendChild(o);
        });
        cityEl.disabled = locs.length === 0;
        return locs;
      });
  }

  function preselectCountry(name) {
    if (!countries.some(function(c) { return c.country === name; })) return Promise.resolve([]);
    countryEl.value = name;
    return loadCities(name);
  }

  function loadEntry(locId) {
    timingLoad.style.display = 'flex';
    timingRows.hidden = true;
    evSection.hidden  = true;
    return fetchWithTimeout(VCAL + '/events/by-event-id?event_id=' + EVENT_ID + '&location_id=' + locId)
      .then(function(r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function(data) {
        // Response shape: { event_id, count, entries: [ {...} ] }
        var entry = (data.entries && data.entries.length) ? data.entries[0] : data;
        renderEntry(entry);
      })
      .catch(function() {
        timingLoad.style.display = 'flex';
        timingLoad.querySelector('span:last-child').textContent = 'Unable to load fasting times. Please try again.';
        timingLoad.querySelector('.spinner').style.display = 'none';
      });
  }

  function formatDate(isoDate) {
    // Parse as local midnight to avoid UTC offset shifting the day
    var d = new Date(isoDate + 'T00:00:00');
    return d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  function renderEntry(entry) {
    // Update Ekādaśī name in page header if server-side fetch missed it
    if (entry.ekadasi_name) {
      document.getElementById('ekadasi-name').textContent = entry.ekadasi_name;
    }

    // Hide server-rendered date fallback once JS has location-specific data
    if (entry.entry_date && headerFallback) headerFallback.hidden = true;

    // Build info card
    var html = '<div class="ek-card">';

    // Ekādaśī date
    if (entry.entry_date) {
      html += '<p class="ek-date">' + formatDate(entry.entry_date) + '</p>';
    }

    // Sunrise · Sunset (above Tithi Ends)
    if (entry.sunrise || entry.sunset) {
      var timeParts = [];
      if (entry.sunrise) timeParts.push('Sunrise: ' + to12h(entry.sunrise));
      if (entry.sunset)  timeParts.push('Sunset: '  + to12h(entry.sunset));
      html += '<p class="ek-times">' + timeParts.join(' &nbsp;·&nbsp; ') + '</p>';
    }

    // Tithi ends
    if (entry.tithi_end) {
      html += '<p class="ek-tithi"><strong>Tithi Ends:</strong> ' + to12h(entry.tithi_end) + '</p>';
    }

    // Break fast note + break fast date on its own line (same style as ekadasi date)
    if (entry.break_fast_note) {
      html += '<p class="ek-breakfast">' + entry.break_fast_note.replace(/</g, '&lt;') + '</p>';
      if (entry.break_fast_date && entry.break_fast_date !== entry.entry_date) {
        html += '<p class="ek-date">' + formatDate(entry.break_fast_date) + '</p>';
      }
    }

    html += '</div>';

    timingLoad.style.display = 'none';
    timingRows.hidden = false;
    timingRows.innerHTML = html;

    // Events list
    if (entry.event_items && entry.event_items.length) {
      evList.innerHTML = '';
      entry.event_items.forEach(function(ev) {
        var li = document.createElement('li');
        li.className = 'event-item';
        li.textContent = ev.text;
        evList.appendChild(li);
      });
      evSection.hidden = false;
    }
  }

  // ── 3-tier location detection (mirrors vcalendar.astro) ───────
  function detectLocation() {
    setMsg('Detecting your location…', true);

    // Tier 1 — Browser GPS
    if ('geolocation' in navigator) {
      navigator.geolocation.getCurrentPosition(
        function(pos) {
          fetchWithTimeout(VCAL + '/nearest-location?lat=' + pos.coords.latitude + '&lon=' + pos.coords.longitude)
            .then(function(r) { return r.json(); })
            .then(function(loc) {
              setMsg('Nearest location: ' + locLabel(loc));
              preselectCountry(loc.country).then(function(locs) {
                if (!locs.length) return;
                cityEl.value = String(loc.id);
                loadEntry(loc.id);
              });
            })
            .catch(tryIpGeo);
        },
        tryIpGeo,
        { timeout: 5000 }
      );
      return;
    }
    tryIpGeo();
  }

  // Tier 2 — IP geolocation
  function tryIpGeo() {
    fetchWithTimeout('https://ipapi.co/json/', 5000)
      .then(function(r) { return r.json(); })
      .then(function(geo) {
        var countryName = ISO_COUNTRY[geo.country_code] || geo.country_name;
        if (!countryName || !countries.some(function(c) { return c.country === countryName; })) {
          throw new Error('no match');
        }
        return preselectCountry(countryName).then(function(locs) {
          // Exact city match
          if (geo.city && locs.length) {
            var cityLow = geo.city.toLowerCase();
            var match = null;
            for (var i = 0; i < locs.length; i++) {
              if (locs[i].city.toLowerCase() === cityLow) { match = locs[i]; break; }
            }
            if (match) {
              setMsg('Location detected: ' + locLabel(match));
              cityEl.value = String(match.id);
              loadEntry(match.id);
              return;
            }
          }
          // Nearest by IP coordinates
          if (geo.latitude && geo.longitude) {
            return fetchWithTimeout(VCAL + '/nearest-location?lat=' + geo.latitude + '&lon=' + geo.longitude)
              .then(function(r) { return r.json(); })
              .then(function(nearest) {
                setMsg('Nearest location: ' + locLabel(nearest));
                return preselectCountry(nearest.country).then(function(rlocs) {
                  if (!rlocs.length) return;
                  cityEl.value = String(nearest.id);
                  loadEntry(nearest.id);
                });
              })
              .catch(function() {
                setMsg('Country detected: ' + countryName + '. Please select your city.');
              });
          }
          setMsg('Country detected: ' + countryName + '. Please select your city.');
        });
      })
      .catch(tryTimezone);
  }

  // Tier 3 — Browser timezone
  function tryTimezone() {
    try {
      var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      var cn = TZ_COUNTRY[tz];
      if (cn && countries.some(function(c) { return c.country === cn; })) {
        setMsg('Country estimated from timezone: ' + cn + '. Please select your city.');
        preselectCountry(cn);
        return;
      }
    } catch (e) { /* ignore */ }
    useDefault();
  }

  // Tier 4 — Default location (Washington, DC)
  function useDefault() {
    setMsg('Showing times for ' + DEFAULT_LOC.label + ' (default). Select your city to change.');
    preselectCountry(DEFAULT_LOC.country).then(function() {
      cityEl.value = String(DEFAULT_LOC.id);
      loadEntry(DEFAULT_LOC.id);
    });
  }

  // ── Select event listeners ────────────────────────────────────
  countryEl.addEventListener('change', function() {
    if (countryEl.value) loadCities(countryEl.value);
  });

  cityEl.addEventListener('change', function() {
    var id = parseInt(cityEl.value);
    if (id) loadEntry(id);
  });

  // ── Boot ──────────────────────────────────────────────────────
  loadCountries().then(detectLocation).catch(function() {
    setMsg('Please select your country and city below.');
    countryEl.disabled = false;
  });

})();
</script>
<?php endif; ?>
