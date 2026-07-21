<?php
/**
 * Uthenga — Admin Gate Session Manager
 * QR ticket scanning, live validation dashboard
 * PHP 7.3+ compatible
 */
$pageTitle = 'Gate Session';
$activeNav = 'admin-gate';

require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/functions.php';

// Load all active events for event selector
$events = dbQuery(
    "SELECT id, title, location, meta FROM listings
     WHERE listing_type = 'event' AND is_active = 1
     ORDER BY title ASC"
);

// Current selected event
$selectedEventId = trim($_GET['event_id'] ?? '');
$selectedEvent   = null;
$activeSession   = null;

if ($selectedEventId) {
    $selectedEvent = dbQueryOne(
        "SELECT * FROM listings WHERE id = ? AND listing_type = 'event' AND is_active = 1",
        [$selectedEventId]
    );
    if ($selectedEvent) {
        $activeSession = dbQueryOne(
            "SELECT * FROM gate_sessions WHERE listing_id = ? AND status IN ('active','paused') ORDER BY started_at DESC LIMIT 1",
            [$selectedEventId]
        );
    }
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= admin_icon_svg('activity') ?> Gate Session Manager</h1>
    <p class="text-muted">Scan QR codes, validate tickets, and monitor entry in real time.</p>
  </div>
  <?php if ($selectedEvent && $activeSession): ?>
    <div style="display:flex;gap:0.75rem;align-items:center;">
      <span class="badge" style="background:<?= $activeSession['status'] === 'active' ? 'var(--clr-green)' : 'var(--clr-accent)' ?>;color:#000;padding:0.4rem 1rem;border-radius:20px;font-weight:700;font-size:0.82rem;">
        <?= $activeSession['status'] === 'active' ? uthenga_public_icon_svg('check') . ' SESSION ACTIVE' : admin_icon_svg('clock') . ' SESSION PAUSED' ?>
      </span>
    </div>
  <?php endif; ?>
</div>

<!-- Event Selector -->
<div class="glass-panel" style="padding:1.5rem;margin-bottom:1.5rem;">
  <form method="GET" action="gate_session.php" id="gate-event-form" style="display:flex;align-items:flex-end;gap:1rem;flex-wrap:wrap;">
    <div class="form-group" style="margin:0;flex:1;min-width:240px;">
      <label class="form-label" for="gate-event-select">Select Event</label>
      <select name="event_id" class="form-control" id="gate-event-select" onchange="this.form.submit()">
        <option value="">Choose an Event</option>
        <?php foreach ($events as $ev): ?>
          <?php $em = json_decode($ev['meta'], true); ?>
          <option value="<?= e($ev['id']) ?>" <?= $selectedEventId === $ev['id'] ? 'selected' : '' ?>>
            <?= e($ev['title']) ?> - <?= e($ev['location']) ?> (<?= e($em['date'] ?? 'No date') ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-secondary btn-sm" id="gate-select-btn" style="height:42px;">Load Event</button>
  </form>
</div>

<?php if (!$selectedEvent): ?>
<div class="glass-panel" style="padding:3rem;text-align:center;">
  <div style="font-size:3rem;margin-bottom:1rem;"><?= uthenga_public_icon_svg('ticket') ?></div>
  <h3>No Event Selected</h3>
  <p class="text-muted">Select an event from the dropdown above to start a gate session.</p>
</div>

<?php else:
  $em = json_decode($selectedEvent['meta'], true);
?>

<!-- Event Summary -->
<div class="glass-panel" style="padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">
  <img src="<?= e($selectedEvent['image']) ?>" alt="" style="width:64px;height:64px;border-radius:var(--radius-md);object-fit:cover;">
  <div style="flex:1;">
    <div style="font-weight:700;font-size:1rem;"><?= e($selectedEvent['title']) ?></div>
    <div class="text-sm text-muted" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
      <span><?= uthenga_public_icon_svg('pin') ?> <?= e($selectedEvent['location']) ?></span>
      <span><?= uthenga_public_icon_svg('calendar') ?> <?= e($em['date'] ?? 'TBC') ?></span>
      <span><?= admin_icon_svg('clock') ?> <?= e($em['time'] ?? 'TBC') ?></span>
    </div>
  </div>
  <a href="<?= BASE_URL ?>event-details.php?id=<?= e($selectedEvent['id']) ?>" target="_blank" class="btn btn-sm btn-secondary">View Event</a>
</div>

<!-- Session Controls -->
<div class="glass-panel" style="padding:1.5rem;margin-bottom:1.5rem;">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
      <h4 style="margin-bottom:0.25rem;">Session Controls</h4>
      <p class="text-muted text-sm" style="margin:0;">
        <?php if ($activeSession): ?>
          Session ID: <code style="font-size:0.82rem;"><?= e($activeSession['id']) ?></code> - started <?= date('d M Y H:i', strtotime($activeSession['started_at'])) ?>
        <?php else: ?>
          No active session for this event.
        <?php endif; ?>
      </p>
    </div>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
      <?php if (!$activeSession): ?>
        <button class="btn btn-primary" id="btn-start-session" onclick="startSession()"><?= admin_icon_svg('activity') ?> Start Gate Session</button>
      <?php elseif ($activeSession['status'] === 'active'): ?>
        <button class="btn btn-secondary" id="btn-pause-session" onclick="pauseSession()"><?= admin_icon_svg('clock') ?> Pause Session</button>
        <button class="btn btn-danger" id="btn-stop-session" onclick="stopSession()"><?= admin_icon_svg('close') ?> Stop Session</button>
      <?php else: ?>
        <button class="btn btn-primary" id="btn-resume-session" onclick="resumeSession()"><?= admin_icon_svg('activity') ?> Resume Session</button>
        <button class="btn btn-danger" id="btn-stop-session" onclick="stopSession()"><?= admin_icon_svg('close') ?> Stop Session</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($activeSession || true): ?>
<!-- Live Dashboard + QR Scanner -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;" id="gate-dashboard-grid">

  <!-- Live Stats -->
  <div class="glass-panel" style="padding:1.5rem;">
    <h4 style="margin-bottom:1.25rem;display:flex;align-items:center;gap:0.5rem;"><?= admin_icon_svg('chart') ?> Live Dashboard</h4>
    <div class="gate-stat-grid" id="gate-stat-grid">
      <div class="gate-stat-card">
        <div class="gate-stat-icon" style="background:rgba(59,130,246,0.1);color:var(--clr-blue);"><?= uthenga_public_icon_svg('ticket') ?></div>
        <div class="gate-stat-value" id="stat-sold">—</div>
        <div class="gate-stat-label">Tickets Sold</div>
      </div>
      <div class="gate-stat-card">
        <div class="gate-stat-icon" style="background:rgba(16,185,129,0.1);color:var(--clr-green);"><?= uthenga_public_icon_svg('check') ?></div>
        <div class="gate-stat-value" id="stat-scanned">—</div>
        <div class="gate-stat-label">Scanned In</div>
      </div>
      <div class="gate-stat-card">
        <div class="gate-stat-icon" style="background:rgba(245,158,11,0.1);color:var(--clr-accent);"><?= uthenga_public_icon_svg('user') ?></div>
        <div class="gate-stat-value" id="stat-remaining">—</div>
        <div class="gate-stat-label">Still to Arrive</div>
      </div>
      <div class="gate-stat-card">
        <div class="gate-stat-icon" style="background:rgba(139,92,246,0.1);color:var(--clr-purple);"><?= uthenga_public_icon_svg('wallet') ?></div>
        <div class="gate-stat-value" id="stat-revenue">—</div>
        <div class="gate-stat-label">Revenue</div>
      </div>
      <div class="gate-stat-card">
        <div class="gate-stat-icon" style="background:rgba(239,68,68,0.1);color:var(--clr-red);"><?= uthenga_public_icon_svg('x') ?></div>
        <div class="gate-stat-value" id="stat-invalid">—</div>
        <div class="gate-stat-label">Invalid Scans</div>
      </div>
      <div class="gate-stat-card">
        <div class="gate-stat-icon" style="background:rgba(245,158,11,0.1);color:var(--clr-accent);"><?= uthenga_public_icon_svg('warning') ?></div>
        <div class="gate-stat-value" id="stat-duplicate">—</div>
        <div class="gate-stat-label">Duplicates</div>
      </div>
    </div>
  </div>

  <!-- QR Scanner -->
  <div class="glass-panel" style="padding:1.5rem;">
    <h4 style="margin-bottom:1.25rem;display:flex;align-items:center;gap:0.5rem;"><?= uthenga_public_icon_svg('camera') ?> QR Code Scanner</h4>

    <!-- Text/hardware scanner input -->
    <div class="form-group">
      <label class="form-label" for="qr-input">Scan or Type Ticket Code</label>
      <div style="display:flex;gap:0.5rem;">
        <input type="text" id="qr-input" class="form-control" placeholder="Scan QR code here..." autocomplete="off"
          style="font-family:monospace;font-size:1rem;letter-spacing:0.05em;" <?= (!$activeSession || $activeSession['status'] !== 'active') ? 'disabled' : '' ?>>
        <button class="btn btn-primary" id="btn-scan" onclick="doScan()" <?= (!$activeSession || $activeSession['status'] !== 'active') ? 'disabled' : '' ?>>Scan</button>
      </div>
      <p class="text-xs text-muted" style="margin-top:0.4rem;">Connect a USB QR scanner or type the code manually. Press Enter to scan.</p>
    </div>

    <!-- Camera scanner toggle -->
    <div style="margin-bottom:1rem;">
      <button class="btn btn-sm btn-secondary" id="btn-camera-toggle" onclick="toggleCamera()" <?= (!$activeSession || $activeSession['status'] !== 'active') ? 'disabled' : '' ?>><?= uthenga_public_icon_svg('camera') ?> Use Camera</button>
    </div>
    <div id="camera-container" style="display:none;border-radius:var(--radius-md);overflow:hidden;background:#000;aspect-ratio:4/3;position:relative;">
      <video id="camera-video" style="width:100%;height:100%;object-fit:cover;" autoplay muted playsinline></video>
      <canvas id="camera-canvas" style="display:none;"></canvas>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);border:3px solid var(--clr-accent);width:200px;height:200px;border-radius:var(--radius-md);pointer-events:none;"></div>
    </div>

    <!-- Scan Result Display -->
    <div id="scan-result-display" style="display:none;margin-top:1rem;" class="scan-result-card">
      <div id="scan-result-icon" style="font-size:2.5rem;margin-bottom:0.5rem;text-align:center;"></div>
      <div id="scan-result-msg" style="font-size:1rem;font-weight:700;text-align:center;margin-bottom:0.5rem;"></div>
      <div id="scan-result-detail" style="font-size:0.85rem;text-align:center;color:var(--clr-text-muted);"></div>
    </div>
  </div>
</div>

<!-- Scan Activity Log -->
<div class="glass-panel" style="padding:1.5rem;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
    <h4 style="margin:0;display:flex;align-items:center;gap:0.5rem;"><?= admin_icon_svg('activity') ?> Scan Activity Log</h4>
    <button class="btn btn-sm btn-secondary" onclick="loadActivity()" id="btn-refresh-log">↺ Refresh</button>
  </div>
  <div id="scan-activity-log" style="max-height:320px;overflow-y:auto;">
    <p class="text-muted" style="text-align:center;padding:2rem 0;">No scans recorded yet.</p>
  </div>
</div>
<?php endif; ?>

<?php endif; // selectedEvent ?>

<style>
.gate-stat-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem; }
.gate-stat-card { background:var(--clr-surface2);border:1px solid var(--clr-border);border-radius:var(--radius-md);padding:1rem;text-align:center; }
.gate-stat-icon { width:40px;height:40px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:0.5rem; }
.gate-stat-value { font-size:1.6rem;font-weight:800;line-height:1.1;margin-bottom:0.2rem; }
.gate-stat-label { font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--clr-text-muted); }
.scan-result-card { padding:1.25rem;border-radius:var(--radius-md);border:2px solid transparent;transition:var(--transition); }
.scan-valid   { background:rgba(16,185,129,0.1);border-color:var(--clr-green);color:var(--clr-green); }
.scan-invalid { background:rgba(239,68,68,0.1);border-color:var(--clr-red);color:var(--clr-red); }
.scan-duplicate { background:rgba(245,158,11,0.1);border-color:var(--clr-accent);color:var(--clr-accent); }
.scan-log-row { display:flex;align-items:center;gap:0.75rem;padding:0.6rem 0;border-bottom:1px solid var(--clr-border);font-size:0.84rem; }
.scan-log-row:last-child { border-bottom:none; }
.scan-badge { padding:0.2rem 0.5rem;border-radius:4px;font-size:0.72rem;font-weight:700;text-transform:uppercase; }
.badge-valid { background:var(--clr-green);color:#000; }
.badge-invalid { background:var(--clr-red);color:#fff; }
.badge-duplicate { background:var(--clr-accent);color:#000; }
@media (max-width:768px) {
  #gate-dashboard-grid { grid-template-columns:1fr; }
  .gate-stat-grid { grid-template-columns:repeat(2,1fr); }
}
</style>

<script>
var GATE_API_URL = '<?= BASE_URL ?>api/gate_api.php';
var CSRF_TOKEN   = '<?= e($_SESSION['csrf_token'] ?? '') ?>';
var SESSION_ID   = '<?= $activeSession ? e($activeSession['id']) : '' ?>';
var EVENT_ID     = '<?= e($selectedEventId) ?>';
var statsTimer   = null;
var cameraStream = null;
var cameraActive = false;
var scanCooldown = false;

function postApi(params, callback) {
  params.csrf_token = CSRF_TOKEN;
  var fd = new FormData();
  Object.keys(params).forEach(function(k){ fd.append(k, params[k]); });
  fetch(GATE_API_URL, { method: 'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(callback)
    .catch(function(e){ console.error(e); });
}

function getApi(params, callback) {
  var qs = Object.keys(params).map(function(k){ return k + '=' + encodeURIComponent(params[k]); }).join('&');
  fetch(GATE_API_URL + '?' + qs)
    .then(function(r){ return r.json(); })
    .then(callback)
    .catch(function(e){ console.error(e); });
}

// ─── Session Controls ─────────────────────────────────────────────────────────
function startSession() {
  var btn = document.getElementById('btn-start-session');
  if (btn) btn.disabled = true;
  postApi({ action: 'start_session', listing_id: EVENT_ID }, function(res) {
    if (res.success) {
      SESSION_ID = res.session_id;
      showToast(res.resumed ? 'Session resumed!' : 'Gate session started!', 'success');
      setTimeout(function(){ location.reload(); }, 1200);
    } else {
      showToast(res.message, 'error');
      if (btn) btn.disabled = false;
    }
  });
}

function pauseSession() {
  if (!SESSION_ID) return;
  postApi({ action: 'pause_session', session_id: SESSION_ID }, function(res) {
    if (res.success) { showToast('Session paused.', 'success'); setTimeout(function(){ location.reload(); }, 1000); }
    else showToast(res.message, 'error');
  });
}

function resumeSession() {
  if (!SESSION_ID) return;
  postApi({ action: 'start_session', listing_id: EVENT_ID }, function(res) {
    if (res.success) { SESSION_ID = res.session_id; showToast('Session resumed!', 'success'); setTimeout(function(){ location.reload(); }, 1000); }
    else showToast(res.message, 'error');
  });
}

function stopSession() {
  if (!SESSION_ID) return;
  if (!confirm('Are you sure you want to STOP this gate session? This cannot be undone.')) return;
  postApi({ action: 'stop_session', session_id: SESSION_ID }, function(res) {
    if (res.success) { showToast('Session stopped.', 'success'); setTimeout(function(){ location.reload(); }, 1000); }
    else showToast(res.message, 'error');
  });
}

// ─── QR Scanning ──────────────────────────────────────────────────────────────
function doScan() {
  if (scanCooldown || !SESSION_ID) return;
  var input = document.getElementById('qr-input');
  var code  = input ? input.value.trim() : '';
  if (!code) return;

  scanCooldown = true;
  setTimeout(function(){ scanCooldown = false; }, 1500);

  postApi({ action: 'scan_ticket', session_id: SESSION_ID, qr_code: code }, function(res) {
    if (res.success) {
      showScanResult(res);
      if (input) input.value = '';
      input.focus();
      loadStats();
      loadActivity();
    } else {
      showToast(res.message, 'error');
    }
  });
}

function showScanResult(res) {
  var el   = document.getElementById('scan-result-display');
  var icon = document.getElementById('scan-result-icon');
  var msg  = document.getElementById('scan-result-msg');
  var det  = document.getElementById('scan-result-detail');
  if (!el) return;

  el.style.display = 'block';
  el.className = 'scan-result-card ' + (res.css_class || '');
  icon.textContent = res.icon || '';
  msg.textContent  = res.message || '';
  var parts = [];
  if (res.customer_name) parts.push('Holder: ' + res.customer_name);
  if (res.ticket_type) parts.push('Tier: ' + res.ticket_type);
  if (res.ticket_id) parts.push('Ticket ID: ' + res.ticket_id);
  if (typeof res.tickets_purchased !== 'undefined') parts.push('Purchased: ' + res.tickets_purchased);
  if (typeof res.tickets_used !== 'undefined') parts.push('Used: ' + res.tickets_used);
  if (typeof res.tickets_remaining !== 'undefined') parts.push('Remaining: ' + res.tickets_remaining);
  if (res.purchase_date) parts.push('Purchased On: ' + res.purchase_date);
  if (res.ticket_status) parts.push('Status: ' + res.ticket_status);
  det.textContent  = parts.join(' · ');

  // Play audio cue (beep)
  try {
    var ctx  = new (window.AudioContext || window.webkitAudioContext)();
    var osc  = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.connect(gain); gain.connect(ctx.destination);
    osc.frequency.value = res.scan_result === 'valid' ? 880 : (res.scan_result === 'duplicate' ? 440 : 220);
    osc.type = 'sine';
    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
    osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.4);
  } catch(e) {}

  setTimeout(function(){ if (el) el.style.display = 'none'; }, 5000);
}

// Enter key triggers scan
document.addEventListener('DOMContentLoaded', function() {
  var input = document.getElementById('qr-input');
  if (input) {
    input.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); doScan(); } });
    input.focus();
  }
});

// ─── Camera ───────────────────────────────────────────────────────────────────
function toggleCamera() {
  if (cameraActive) stopCamera();
  else startCamera();
}

function startCamera() {
  var container = document.getElementById('camera-container');
  var video     = document.getElementById('camera-video');
  if (!container || !video) return;

  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    .then(function(stream) {
      cameraStream = stream;
      cameraActive = true;
      video.srcObject = stream;
      container.style.display = 'block';
      document.getElementById('btn-camera-toggle').innerHTML = '<?= uthenga_public_icon_svg('camera') ?> Stop Camera';
      scanCameraLoop();
    })
    .catch(function(e) {
      showToast('Camera not available: ' + e.message, 'error');
    });
}

function stopCamera() {
  if (cameraStream) { cameraStream.getTracks().forEach(function(t){ t.stop(); }); cameraStream = null; }
  cameraActive = false;
  var container = document.getElementById('camera-container');
  if (container) container.style.display = 'none';
  var btn = document.getElementById('btn-camera-toggle');
  if (btn) btn.innerHTML = '<?= uthenga_public_icon_svg('camera') ?> Use Camera';
}

function scanCameraLoop() {
  if (!cameraActive) return;
  var video  = document.getElementById('camera-video');
  var canvas = document.getElementById('camera-canvas');
  if (!video || !canvas) return;

  // Load jsQR library dynamically
  if (typeof jsQR === 'undefined') {
    var script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
    script.onload = function() { scanCameraFrame(video, canvas); };
    document.head.appendChild(script);
  } else {
    scanCameraFrame(video, canvas);
  }
}

function scanCameraFrame(video, canvas) {
  if (!cameraActive || video.readyState !== video.HAVE_ENOUGH_DATA) {
    requestAnimationFrame(function(){ scanCameraFrame(video, canvas); });
    return;
  }
  canvas.width  = video.videoWidth;
  canvas.height = video.videoHeight;
  var ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
  var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

  if (typeof jsQR !== 'undefined') {
    var code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
    if (code && code.data && !scanCooldown) {
      var input = document.getElementById('qr-input');
      if (input) input.value = code.data;
      doScan();
    }
  }
  requestAnimationFrame(function(){ scanCameraFrame(video, canvas); });
}

// ─── Live Stats ───────────────────────────────────────────────────────────────
function loadStats() {
  if (!SESSION_ID) return;
  getApi({ action: 'session_stats', session_id: SESSION_ID }, function(res) {
    if (res.success) {
      setText('stat-sold',      res.tickets_sold);
      setText('stat-scanned',   res.valid);
      setText('stat-remaining', res.remaining);
      setText('stat-revenue',   res.revenue);
      setText('stat-invalid',   res.invalid);
      setText('stat-duplicate', res.duplicate);
    }
  });
}

function setText(id, val) {
  var el = document.getElementById(id);
  if (el) el.textContent = val;
}

function loadActivity() {
  if (!SESSION_ID) return;
  getApi({ action: 'scan_activity', session_id: SESSION_ID, limit: 30 }, function(res) {
    var container = document.getElementById('scan-activity-log');
    if (!container || !res.success) return;
    if (!res.scans || res.scans.length === 0) {
      container.innerHTML = '<p class="text-muted" style="text-align:center;padding:2rem 0;">No scans recorded yet.</p>';
      return;
    }
    var html = '';
    res.scans.forEach(function(scan) {
      var badgeClass = 'badge-' + scan.scan_result;
      var name = scan.customer_name || 'Unknown';
      var type = scan.ticket_type || '';
      var time = scan.scanned_at ? scan.scanned_at.substr(11, 5) : '';
      html += '<div class="scan-log-row">' +
        '<span class="scan-badge ' + badgeClass + '">' + scan.scan_result.toUpperCase() + '</span>' +
        '<span style="flex:1;">' + htmlEsc(name) + (type ? ' <span class="text-xs text-muted">· ' + htmlEsc(type) + '</span>' : '') + '</span>' +
        '<span class="text-xs text-muted">' + time + '</span>' +
      '</div>';
    });
    container.innerHTML = html;
  });
}

function htmlEsc(str) {
  var d = document.createElement('div');
  d.appendChild(document.createTextNode(str));
  return d.innerHTML;
}

function showToast(msg, type) {
  var toast = document.createElement('div');
  toast.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;padding:1rem 1.5rem;border-radius:var(--radius-md);z-index:9999;font-weight:600;font-size:0.9rem;box-shadow:var(--shadow-md);max-width:320px;';
  toast.style.background = type === 'success' ? 'var(--clr-green)' : 'var(--clr-red)';
  toast.style.color = '#000';
  toast.textContent = msg;
  document.body.appendChild(toast);
  setTimeout(function(){ if (toast.parentNode) toast.parentNode.removeChild(toast); }, 3500);
}

// Auto-refresh stats every 10 seconds if session is active
<?php if ($activeSession && $activeSession['status'] === 'active'): ?>
SESSION_ID = '<?= e($activeSession['id']) ?>';
loadStats();
loadActivity();
statsTimer = setInterval(function() { loadStats(); loadActivity(); }, 10000);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
