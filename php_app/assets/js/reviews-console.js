/* Uthenga — Reviews Console (Events V2).
 * The organizer's reputation command center: collect → understand → respond →
 * resolve → learn → improve. Every review is tied to a verified booking and
 * ticket record; the Verified Attendee badge is earned only through a paid,
 * checked-in ticket. Organizers respond to and flag reviews, but can never
 * erase legitimate negative feedback — platform moderation decides removal.
 */
window.ReviewsControlCenter = (function() {
  'use strict';

  var evDoc = document.getElementById('events-workspace');
  if (!evDoc) return {};
  var base = evDoc.dataset.baseUrl ? evDoc.dataset.baseUrl : '';
  var csrf = evDoc.dataset.csrf ? evDoc.dataset.csrf : '';
  var api = base + 'api/tie/vendor/events/reviews.php';

  var FLAG_REASONS = [
    { id: 'INAPPROPRIATE', label: 'Inappropriate language' },
    { id: 'HARASSMENT', label: 'Harassment' },
    { id: 'SPAM', label: 'Spam or advertising' },
    { id: 'FAKE', label: 'Fake — reviewer did not attend' },
    { id: 'OFF_TOPIC', label: 'Off-topic' },
    { id: 'PRIVACY', label: 'Personal information shared' },
    { id: 'CONFLICT', label: 'Conflict of interest' },
    { id: 'OTHER', label: 'Other' }
  ];

  var TABS = [
    { id: 'overview', label: 'Overview' },
    { id: 'all', label: 'All Reviews' },
    { id: 'needs', label: 'Needs Response' },
    { id: 'themes', label: 'Themes' },
    { id: 'flagged', label: 'Flagged' },
    { id: 'requests', label: 'Requests' }
  ];

  var state = {
    tab: 'overview',
    filters: { event_id: 'all', rating: 0, status: 'all', theme: '', q: '', sort: 'newest', page: 1 },
    events: [],
    config: null,
    ov: null,
    list: null,
    req: null,
    loading: false,
    booted: false
  };

  /* ── Helpers ────────────────────────────────────────────────────── */

  function esc(s) { return window.tkEsc ? tkEsc(s) : String(s == null ? '' : s); }
  function date(s) { return window.tkDate ? tkDate(s) : String(s || '—'); }
  function num(n) { return Number(n) || 0; }
  function toast(m, err) {
    if (window.eccNotify) { window.eccNotify(m); return; }
    var el = document.createElement('div');
    el.textContent = m;
    el.style.cssText = 'position:fixed;bottom:' + (err ? '70px' : '20px') + ';right:20px;z-index:99999;background:' + (err ? '#e63946' : '#10b981') + ';color:#fff;padding:10px 16px;border-radius:10px;font:700 13px Inter,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.25)';
    document.body.appendChild(el);
    setTimeout(function() { el.remove(); }, 3200);
  }
  function ic(name, size) {
    var p = {
      star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
      check: '<polyline points="20 6 9 17 4 12"/>',
      shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
      x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
      back: '<polyline points="15 18 9 12 15 6"/>',
      clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
      search: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
      sparkle: '<path d="M12 3l1.9 5.7L20 10l-5.7 1.9L12 18l-1.9-6.1L4 10l6.1-1.3z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
      flag: '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
      reply: '<polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
      tune: '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
      refresh: '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
      download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
      calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
      scale: '<path d="M12 3v18"/><path d="M5 7h14"/><path d="M5 7l-3 6a3 3 0 0 0 6 0z"/><path d="M19 7l-3 6a3 3 0 0 0 6 0z"/><path d="M8 21h8"/>',
      thumbs: '<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>',
      user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
      ticket: '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/><line x1="13" y1="5" x2="13" y2="7"/><line x1="13" y1="11" x2="13" y2="13"/><line x1="13" y1="17" x2="13" y2="19"/>',
      mail: '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/>',
      alert: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
      bar: '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
      inbox: '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
      send: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
      eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
      chevR: '<polyline points="9 18 15 12 9 6"/>',
      robot: '<rect x="4" y="8" width="16" height="12" rx="2"/><circle cx="9" cy="13" r="1.5"/><circle cx="15" cy="13" r="1.5"/><path d="M12 3v5"/><path d="M8 8h8"/>'
    }[name] || '';
    var s = size || 14;
    return '<svg viewBox="0 0 24 24" width="' + s + '" height="' + s + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-0.15em;flex:none;">' + p + '</svg>';
  }
  function stars(rating, size) {
    var out = '';
    for (var i = 1; i <= 5; i++) {
      out += '<span class="' + (i <= num(rating) ? 'rev-star on' : 'rev-star') + '">' + ic('star', size || 13) + '</span>';
    }
    return out;
  }
  function sentCls(s) { return String(s || '').toLowerCase(); }
  function modCls(s) {
    var m = String(s || '').toLowerCase();
    if (m === 'flagged') return 'warn';
    return m === 'normal' ? 'ok' : 'gray';
  }
  function shortDate(sqlDt) {
    if (!sqlDt) return '';
    var d = new Date(String(sqlDt).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(sqlDt);
    var now = new Date();
    var sameDay = d.toDateString() === now.toDateString();
    var hh = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    return sameDay ? 'Today ' + hh : (String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')) + ' ' + hh;
  }
  function prioFor(rating) {
    var c = state.config && state.config.priority ? state.config.priority : { critical: 2, high: 3, normal: 4, low: 5 };
    var r = num(rating);
    if (r <= num(c.critical)) return { label: 'Critical', cls: 'crit' };
    if (r <= num(c.high)) return { label: 'High', cls: 'high' };
    if (r <= num(c.normal)) return { label: 'Normal', cls: 'norm' };
    return { label: 'Low', cls: 'low' };
  }

  /* ── API Layer ─────────────────────────────────────────────────── */

  function qs(obj) {
    return Object.keys(obj).filter(function(k) { return obj[k] !== '' && obj[k] != null; })
      .map(function(k) { return encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]); }).join('&');
  }
  function get(action, params) {
    var p = Object.assign({ action: action }, params || {});
    return fetch(api + '?' + qs(p), { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function(r) { return r.json(); })
      .then(function(j) {
        if (!j || j.success !== true) {
          var msg = j && j.error && j.error.message ? j.error.message : 'Request failed.';
          throw new Error(msg);
        }
        return j.reviews_result !== undefined ? j.reviews_result : j;
      });
  }
  function post(payload) {
    return fetch(api, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-CSRF-Token': csrf },
      body: qs(payload)
    }).then(function(r) { return r.json(); }).then(function(j) {
      if (!j || j.success !== true) {
        var msg = j && j.error && j.error.message ? j.error.message : 'Operation failed.';
        throw new Error(msg);
      }
      return j.reviews_result !== undefined ? j.reviews_result : j;
    });
  }

  /* ── Shell ─────────────────────────────────────────────────────── */

  function shell() {
    var tabs = TABS.map(function(t) {
      var badge = '';
      if (t.id === 'needs' && state.ov) badge = state.ov.kpis.unanswered.value;
      if (t.id === 'flagged' && state.ov) badge = state.ov.flagged_count;
      return '<button type="button" class="rev-tab' + (state.tab === t.id ? ' active' : '') + '" data-tab="' + t.id + '" onclick="ReviewsControlCenter.setTab(\'' + t.id + '\')">' +
        esc(t.label) + (badge ? '<em>' + badge + '</em>' : '') + '</button>';
    }).join('');
    ROOT.innerHTML =
      '<div class="rev-wrap">' +
        '<div class="rev-topbar">' +
          '<div class="rev-topbar-l">' + ic('star', 18) +
            '<div><div class="rev-topbar-t">Reviews</div>' +
            '<div class="rev-topbar-s">Reputation &amp; customer feedback truth center</div></div>' +
          '</div>' +
          '<div class="rev-tabs" id="rev-tabs">' + tabs + '</div>' +
          '<div class="rev-topbar-r">' +
            '<button type="button" class="rev-btn rev-btn-line" onclick="ReviewsControlCenter.openSettings()" title="Review settings">' + ic('tune', 13) + ' <span>Settings</span></button>' +
            '<button type="button" class="rev-btn rev-btn-line" onclick="ReviewsControlCenter.exportCsv()" title="Export reviews">' + ic('download', 13) + ' <span>Export</span></button>' +
            '<button type="button" class="rev-btn rev-btn-line" onclick="ReviewsControlCenter.refresh()" title="Refresh">' + ic('refresh', 13) + '</button>' +
          '</div>' +
        '</div>' +
        '<div class="rev-pane" id="rev-pane"></div>' +
        '<div class="rev-modal-overlay" id="rev-modal-overlay" onclick="if(event.target===this)ReviewsControlCenter.modalClose()">' +
          '<div class="rev-modal"><div class="rev-modal-hd"><h3 id="rev-modal-title"></h3><button type="button" class="rev-modal-x" onclick="ReviewsControlCenter.modalClose()" title="Close">' + ic('x', 15) + '</button></div>' +
          '<div class="rev-modal-bd" id="rev-modal-bd"></div>' +
          '<div class="rev-modal-ft" id="rev-modal-ft"></div></div>' +
        '</div>' +
      '</div>';
  }
  function pane() {
    var p = document.getElementById('rev-pane');
    if (!p) return;
    if (state.loading) { p.innerHTML = '<div class="rev-loading">' + ic('refresh', 16) + ' Loading reviews…</div>'; return; }
    if (state.tab === 'overview') p.innerHTML = buildOverview();
    else if (state.tab === 'all') p.innerHTML = buildList();
    else if (state.tab === 'needs') p.innerHTML = buildList();
    else if (state.tab === 'themes') p.innerHTML = buildThemes();
    else if (state.tab === 'flagged') p.innerHTML = buildList();
    else if (state.tab === 'requests') p.innerHTML = buildRequests();
  }

  /* ── Overview ──────────────────────────────────────────────────── */

  function kpiCard(label, value, sub, onClick, tone) {
    return '<div class="rev-kpi" onclick="' + (onClick || '') + '"><div class="rev-kpi-top"><b>' + esc(value) + '</b></div>' +
      '<span>' + esc(label) + '</span>' + (sub ? '<small class="rev-kpi-sub ' + (tone || '') + '">' + sub + '</small>' : '') + '</div>';
  }
  function starDist(dist) {
    var total = dist.reduce(function(a, d) { return a + d.count; }, 0) || 1;
    return '<div class="rev-dist" role="list" aria-label="Rating distribution">' + dist.slice().reverse().map(function(d) {
      var w = Math.max(2, Math.round(100 * d.count / total));
      return '<div class="rev-dist-row" onclick="ReviewsControlCenter.filterRating(' + d.rating + ')">' +
        '<span class="rev-dist-star">' + d.rating + ' ' + ic('star', 11) + '</span>' +
        '<div class="rev-dist-track"><div class="rev-dist-fill" style="width:' + w + '%"></div></div>' +
        '<b>' + d.count + '</b><em>' + d.pct + '%</em></div>';
    }).join('') + '</div>';
  }
  function trendChart() {
    var t = state.ov.trend || {};
    var pts = t.points || [];
    if (!pts.length) return '<p class="rev-empty">No review activity in this window yet.</p>';
    var W = 860, H = 200, pad = 36, bot = 26, top = 16;
    var maxV = Math.max.apply(null, pts.map(function(p) { return num(p.volume); })) || 1;
    var iw = W - pad - 14, ih = H - top - bot;
    var step = iw / Math.max(1, pts.length - 1);
    var coords = pts.map(function(p, i) {
      var x = pad + i * step;
      var yb = top + ih - (num(p.volume) / maxV) * ih;
      var yl = top + ih - ((p.avg_rating == null ? 0 : num(p.avg_rating)) / 5) * ih;
      return { x: x, yb: yb, yl: yl, p: p };
    });
    var barH = Math.max(2, Math.round(ih * 0.55));
    var linePath = coords.map(function(c, i) { return (i === 0 ? 'M' : 'L') + c.x.toFixed(1) + ' ' + c.yl.toFixed(1); }).join(' ');
    var out = '<svg width="100%" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" role="img">';
    out += '<line x1="' + pad + '" y1="' + (H - bot) + '" x2="' + (W - 8) + '" y2="' + (H - bot) + '" stroke="#1e2a38" stroke-opacity=".12"/>';
    for (var g = 0; g <= 4; g++) {
      var gy = top + (ih / 4) * g;
      out += '<line x1="' + pad + '" y1="' + gy.toFixed(1) + '" x2="' + (W - 8) + '" y2="' + gy.toFixed(1) + '" stroke="#1e2a38" stroke-opacity=".05"/>';
      out += '<text x="' + (pad - 5) + '" y="' + (gy + 3).toFixed(1) + '" text-anchor="end" font-size="9" fill="#8a94a6">' + (5 - (5 / 4) * g).toFixed(1) + '</text>';
    }
    coords.forEach(function(c) {
      var bh = Math.max(2, (num(c.p.volume) / maxV) * (ih * 0.55));
      out += '<rect x="' + (c.x - 6).toFixed(1) + '" y="' + (top + ih - bh).toFixed(1) + '" width="12" height="' + bh.toFixed(1) + '" rx="3" fill="#e6e9f2"><title>' + esc(c.p.label) + ': ' + c.p.volume + ' reviews</title></rect>';
      var lbl = String(pts.length <= 16 ? c.p.label : '');
      out += '<text x="' + c.x.toFixed(1) + '" y="' + (H - 8) + '" text-anchor="middle" font-size="9" fill="#8a94a6">' + esc(lbl) + '</text>';
    });
    out += '<path d="' + linePath + '" fill="none" stroke="#e63946" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>';
    coords.forEach(function(c) {
      if (c.p.avg_rating == null) return;
      out += '<circle cx="' + c.x.toFixed(1) + '" cy="' + c.yl.toFixed(1) + '" r="3" fill="#fff" stroke="#e63946" stroke-width="2"><title>' + esc(c.p.label) + ': avg ' + c.p.avg_rating + '★</title></circle>';
    });
    out += '<text x="' + (pad - 5) + '" y="' + (top - 5) + '" text-anchor="end" font-size="9" fill="#8a94a6">avg ★</text>';
    out += '</svg>';
    return '<div class="rev-chart">' + out + '</div>';
  }
  function buildInsights(ins) {
    if (!ins.length) return '';
    return '<div class="rev-insights">' + ins.map(function(i) {
      var icon = i.icon === 'praise' ? 'thumbs' : i.icon === 'theme' ? 'bar' : i.icon === 'reply' ? 'reply' : 'alert';
      return '<div class="rev-insight ' + i.tone + '">' +
        '<div class="rev-insight-ic">' + ic(icon, 15) + '</div>' +
        '<div class="rev-insight-bd"><b>' + esc(i.title) + '</b><p>' + esc(i.text) + '</p></div>' +
        '<button type="button" class="rev-btn rev-btn-primary rev-btn-sm" onclick="ReviewsControlCenter.go(\'' + esc(i.link) + '\')">' + esc(i.action) + '</button>' +
        '</div>';
    }).join('') + '</div>';
  }
  function buildRecent() {
    var rows = (state.ov.recent || []).slice(0, 6);
    if (!rows.length) return '<p class="rev-empty">No reviews yet — share your event and invite attendees to leave feedback.</p>';
    return '<div class="rev-recent">' + rows.map(reviewCard).join('') + '</div>';
  }
  function buildThemeBars() {
    var th = state.ov.themes || [];
    if (!th.length) return '<p class="rev-empty">Themes will appear as reviews arrive.</p>';
    var max = Math.max.apply(null, th.map(function(t) { return t.count; })) || 1;
    return '<div class="rev-theme-bars">' + th.slice(0, 8).map(function(t) {
      return '<div class="rev-theme-row" onclick="ReviewsControlCenter.goToTheme(\'' + esc(t.theme) + '\')">' +
        '<span class="rev-theme-name">' + esc(t.label) + '</span>' +
        '<div class="rev-theme-track">' +
          '<div class="rev-theme-fill pos" style="width:' + Math.max(2, Math.round(100 * t.positive / max)) + '%"></div>' +
          '<div class="rev-theme-fill neg" style="width:' + Math.max(1, Math.round(100 * t.negative / max)) + '%"></div>' +
        '</div>' +
        '<span class="rev-theme-num"><b>' + t.count + '</b><i class="pos">+' + t.positive + '</i><i class="neg">-' + t.negative + '</i></span></div>';
    }).join('') + '</div>';
  }
  function buildEventRows() {
    var evs = state.ov.events || [];
    if (!evs.length) return '<p class="rev-empty">No events reviewed yet.</p>';
    return '<div class="rev-evrows">' + evs.slice(0, 8).map(function(e) {
      return '<div class="rev-evrow" onclick="ReviewsControlCenter.filterEvent(\'' + esc(e.event_id) + '\')">' +
        '<div class="rev-evrow-name"><b>' + esc(e.title) + '</b><span>' + stars(e.rating, 11) + '</span></div>' +
        '<div class="rev-evrow-meta"><b>' + e.rating + '</b><span>' + e.reviews + ' reviews</span>' +
        '<em class="' + e.trend + '">' + (e.trend === 'up' ? '&#9650; rising' : e.trend === 'down' ? '&#9660; slipping' : '— steady') + '</em></div></div>';
    }).join('') + '</div>';
  }
  function buildAsk() {
    return '<div class="rev-ask">' +
      '<div class="rev-ask-in"><input id="rev-ask-q" type="text" placeholder="Ask about feedback — e.g. \'What do attendees complain about most?\'" onkeydown="if(event.key===\'Enter\')ReviewsControlCenter.ask()"/>' +
      '<button type="button" class="rev-btn rev-btn-primary" onclick="ReviewsControlCenter.ask()">' + ic('robot', 13) + ' Ask</button></div>' +
      '<div class="rev-ask-chips">' +
      ['What do attendees complain about most?', 'How is venue feedback?', 'Which reviews await a response?', 'Any check-in problems?', 'Show food & drink feedback'].map(function(c) {
        return '<button type="button" class="rev-chip" onclick="ReviewsControlCenter.suggest(\'' + c.replace(/'/g, "\\'") + '\')">' + esc(c) + '</button>';
      }).join('') + '</div>' +
      '<div class="rev-ask-answer" id="rev-ask-answer" style="display:none"></div></div>';
  }
  function buildOverview() {
    var k = state.ov.kpis;
    var h = '<div class="rev-head">' +
      '<div class="rev-head-l"><h2>Reputation &amp; Feedback</h2>' +
      '<p>Every review is anchored to a real booking and ticket record. Respond fast, investigate negative feedback, and let patterns guide your next event.</p></div>' +
      '<div class="rev-head-r"><span class="rev-head-badge">' + ic('shield', 12) + ' Verified-attendee badges from checked-in tickets</span></div>' +
      '</div>';

    h += '<div class="rev-overview-kpis">' +
      kpiCard('Overall rating', k.overall_rating.formatted, (k.overall_rating.change ? '<span class="rev-up">&#9650; ' + k.overall_rating.change + '</span> vs prior' : 'across all reviewed events'), 'ReviewsControlCenter.setTab(\'all\')') +
      kpiCard('Total reviews', k.total_reviews.formatted, (k.total_reviews.change_pct != null ? '<span class="' + (num(k.total_reviews.change_pct) >= 0 ? 'rev-up' : 'rev-down') + '">' + (num(k.total_reviews.change_pct) >= 0 ? '&#9650; ' : '&#9660; ') + Math.abs(k.total_reviews.change_pct) + '%</span> vs prior' : 'lifetime'), 'ReviewsControlCenter.setTab(\'all\')') +
      kpiCard('Response rate', k.response_rate.formatted + '%', k.response_rate.responded + ' answered · ' + k.response_rate.unanswered + ' open', 'ReviewsControlCenter.setTab(\'needs\')') +
      kpiCard('Awaiting reply', k.unanswered.value, k.unanswered.needs_attention + ' high priority', 'ReviewsControlCenter.setTab(\'needs\')', 'warn') +
      '</div>';

    h += '<div class="rev-cols">' +
      '<div class="rev-block">' + blockH('Rating distribution', 'Filter the review list by star rating') + starDist(state.ov.distribution) + '</div>' +
      '<div class="rev-block">' + blockH('Rating &amp; volume trend', 'Daily average rating (line) and review volume (bars)') + trendChart() + '</div>' +
      '</div>';

    var sent = state.ov.sentiment_pct || {};
    h += '<div class="rev-cols">' +
      '<div class="rev-block">' + blockH('Sentiment', 'Deterministic keyword classification — explainable, no black box') +
        '<div class="rev-sent-row">' +
          ['positive', 'neutral', 'negative'].map(function(s) {
            var cls = s === 'positive' ? 'pos' : s === 'negative' ? 'neg' : 'neu';
            return '<div class="rev-sent rev-sent-' + s + '"><b>' + (sent[s] || 0) + '%</b>' +
              '<div class="rev-sent-track"><div class="rev-sent-fill" style="width:' + (sent[s] || 0) + '%"></div></div>' +
              '<span>' + (s === 'positive' ? 'Positive' : s === 'negative' ? 'Negative' : 'Neutral') + '</span></div>';
          }).join('') + '</div>' +
          (state.ov.verified_count ? '<p class="rev-hint">' + ic('shield', 11) + ' ' + state.ov.verified_count + ' reviews came from verified attendees (paid + checked-in tickets).</p>' : '') +
        '</div>' +
      '<div class="rev-block">' + blockH('Top themes', 'Click a theme to explore its reviews') + buildThemeBars() + '</div>' +
      '</div>';

    h += '<div class="rev-cols">' +
      '<div class="rev-block">' + blockH('Per-event reputation', 'Ratings and volume per event') + buildEventRows() + '</div>' +
      '<div class="rev-block">' + blockH('Review request funnel', 'How many attendees were asked vs. responded') +
        '<div class="rev-funnel-mini">' +
          [['Eligible', state.ov.funnel.eligible], ['Sent', state.ov.funnel.sent], ['Opened', state.ov.funnel.opened], ['Submitted', state.ov.funnel.submitted]].map(function(pair, i) {
            var max = Math.max(state.ov.funnel.eligible, 1);
            return '<div class="rev-funnel-mini-row"><span>' + pair[0] + '</span>' +
              '<div class="rev-funnel-mini-track"><div class="rev-funnel-mini-fill" style="width:' + Math.max(3, Math.round(100 * pair[1] / max)) + '%"></div></div><b>' + pair[1] + '</b></div>';
          }).join('') +
          '<button type="button" class="rev-link" onclick="ReviewsControlCenter.setTab(\'requests\')">Open request report ' + ic('chevR', 11) + '</button></div>' +
        '</div>' +
      '</div>';

    h += '<div class="rev-insights-wrap">' + blockH('Reputation insights', 'Auto-generated from current data') + buildInsights(state.ov.insights) + '</div>';
    h += '<div class="rev-block">' + blockH('Latest reviews', 'Click a review to read and respond') + buildRecent() + '</div>';
    h += '<div class="rev-block">' + blockH('Ask your reviews', 'Plain-language answers backed by the real data') + buildAsk() + '</div>';
    return h;
  }
  function blockH(label, sub) {
    return '<h3 class="rev-block-h">' + esc(label) + (sub ? '<span>' + esc(sub) + '</span>' : '') + '</h3>';
  }

  /* ── Review list ───────────────────────────────────────────────── */

  function filterStatusLabel() {
    if (state.tab === 'needs') return 'Needs Response';
    if (state.tab === 'flagged') return 'Flagged';
    return 'All Reviews';
  }
  function buildList() {
    var l = state.list || { rows: [], total: 0, page: 1, pages: 1 };
    var f = state.filters;

    var evOpts = '<option value="all">All events</option>' + state.events.map(function(e) {
      return '<option value="' + esc(e.event_id) + '"' + (f.event_id === e.event_id ? ' selected' : '') + '>' + esc(e.title) + '</option>';
    }).join('');

    var h = '<div class="rev-list-tool">' +
      '<label class="rev-tool-lbl">' + ic('calendar', 12) + '<select id="rev-ev" onchange="ReviewsControlCenter.setEvent(this.value)">' + evOpts + '</select></label>' +
      '<div class="rev-star-filter">' +
        ['0', '5', '4', '3', '2', '1'].map(function(r) {
          var label = r === '0' ? 'All' : r + ' ' + ic('star', 10);
          return '<button type="button" class="rev-star-fbtn' + (num(f.rating) === num(r) ? ' active' : '') + '" onclick="ReviewsControlCenter.filterRating(' + r + ')">' + label + '</button>';
        }).join('') +
      '</div>' +
      (f.theme ? '<span class="rev-pill active">' + ic('bar', 11) + ' Theme: ' + esc(f.themeLabel || f.theme) + ' <button type="button" class="rev-x-inline" onclick="ReviewsControlCenter.clearTheme()">' + ic('x', 10) + '</button></span>' : '') +
      '<div class="rev-tool-r">' +
        '<label class="rev-tool-search">' + ic('search', 12) + '<input id="rev-q" type="text" placeholder="Search reviews…" value="' + esc(f.q) + '" onkeydown="if(event.key===\'Enter\')ReviewsControlCenter.applySearch()"/></label>' +
        '<select id="rev-sort" onchange="ReviewsControlCenter.setSort(this.value)">' +
          '<option value="newest"' + (f.sort === 'newest' ? ' selected' : '') + '>Newest first</option>' +
          '<option value="oldest"' + (f.sort === 'oldest' ? ' selected' : '') + '>Oldest first</option>' +
          '<option value="rating"' + (f.sort === 'rating' ? ' selected' : '') + '>Highest rated</option>' +
          '<option value="helpful"' + (f.sort === 'helpful' ? ' selected' : '') + '>Most helpful</option>' +
        '</select>' +
      '</div></div>';

    h += '<div class="rev-list-meta"><b>' + l.total + '</b> ' + esc(filterStatusLabel()) + (f.theme ? ' matching <em>' + esc(f.themeLabel || f.theme) + '</em>' : '') +
      (f.event_id !== 'all' ? ' · ' + esc((state.events.find(function(e) { return e.event_id === f.event_id; }) || {}).title || '') : '') +
      '</div>';

    if (!l.rows.length) {
      h += '<p class="rev-empty">No reviews match these filters.</p>';
    } else {
      h += '<div class="rev-cards">' + l.rows.map(reviewCard).join('') + '</div>';
      if (l.pages > 1) {
        h += '<div class="rev-pager"><button type="button" class="rev-btn rev-btn-line rev-btn-sm" ' + (l.page <= 1 ? 'disabled' : '') + ' onclick="ReviewsControlCenter.goPage(' + (l.page - 1) + ')">' + ic('back', 11) + ' Prev</button>' +
          '<span>Page ' + l.page + ' of ' + l.pages + '</span>' +
          '<button type="button" class="rev-btn rev-btn-line rev-btn-sm" ' + (l.page >= l.pages ? 'disabled' : '') + ' onclick="ReviewsControlCenter.goPage(' + (l.page + 1) + ')">Next ' + ic('chevR', 11) + '</button></div>';
      }
    }
    return h;
  }
  function reviewCard(r) {
    var prio = prioFor(r.rating);
    var flagChip = r.moderation === 'FLAGGED'
      ? '<span class="rev-tag warn">' + ic('flag', 10) + ' Flagged' + (r.flag_status ? ' · ' + esc(r.flag_status) : '') + '</span>' : '';
    var sentChip = '<span class="rev-tag sent ' + sentCls(r.sentiment) + '">' + esc(r.sentiment || '—') + '</span>';
    var respChip = r.response
      ? '<span class="rev-tag resp' + (r.response.status === 'PUBLISHED' ? ' ok' : '') + '">' + ic('reply', 10) + (r.response.ai_drafted ? ' AI-assisted reply' : ' Replied') + (r.response.status === 'PUBLISHED' ? '' : ' · ' + esc(r.response.status)) + '</span>'
      : '<span class="rev-tag resp none">' + ic('alert', 10) + ' Awaiting reply</span>';
    var verify = r.verified_attendee
      ? '<span class="rev-verified" title="Verified attendee: paid booking ' + esc(r.verification && r.verification.booking_id || '') + ', ticket checked in" onclick="event.stopPropagation()">' + ic('shield', 11) + ' Verified attendee</span>'
      : '<span class="rev-unverified" title="No paid, checked-in ticket was found for this review." onclick="event.stopPropagation()">' + ic('user', 11) + ' Unverified</span>';

    var themes = (r.themes || []).map(function(t) {
      return '<span class="rev-theme-chip ' + (t.polarity === 'negative' ? 'neg' : 'pos') + '">' + esc(t.label) + '</span>';
    }).join('');

    var respBody = '';
    if (r.response && r.response.status === 'PUBLISHED') {
      respBody = '<div class="rev-card-resp">' + ic('reply', 11) + '<span><b>Organizer response</b> — ' + esc((r.response.body || '').slice(0, 110)) + (r.response.body && r.response.body.length > 110 ? '…' : '') + '</span></div>';
    } else {
      respBody = '<div class="rev-card-resp none">' + ic('alert', 11) + '<span><b>No response yet</b> — ' + (num(r.rating) <= (state.config ? state.config.priority.high : 3) ? 'high priority, review this soon.' : 'this review is still open.') + '</span></div>';
    }

    return '<div class="rev-card' + (r.moderation === 'FLAGGED' ? ' flagged' : '') + '" onclick="ReviewsControlCenter.openDetail(\'' + esc(r.id) + '\')">' +
      '<div class="rev-card-top">' +
        '<div class="rev-card-stars">' + stars(r.rating) + ' <b>' + r.rating + '.0</b></div>' +
        '<div class="rev-card-top-r">' + prioChip(prio) + sentChip + flagChip + '</div>' +
      '</div>' +
      '<h4 class="rev-card-title">' + esc(r.title || 'Untitled review') + '</h4>' +
      '<p class="rev-card-body">' + esc((r.body || '').slice(0, 220)) + ((r.body || '').length > 220 ? '…' : '') + '</p>' +
      (themes ? '<div class="rev-card-themes">' + themes + '</div>' : '') +
      '<div class="rev-card-meta">' +
        '<span class="rev-card-cust">' + ic('user', 11) + ' ' + esc(r.customer && r.customer.name || 'Anonymous') + '</span>' +
        '<span>' + ic('calendar', 11) + ' ' + shortDate(r.created_at) + '</span>' +
        '<span class="rev-card-ev">' + ic('ticket', 11) + ' ' + esc(r.event && r.event.title || '—') + '</span>' +
        '<span>' + ic('thumbs', 11) + ' ' + num(r.helpful_count) + '</span>' +
      '</div>' +
      '<div class="rev-card-foot">' +
        '<div class="rev-card-verify">' + verify + '</div>' +
        '<div class="rev-card-actions">' +
          '<button type="button" class="rev-btn rev-btn-line rev-btn-sm" onclick="event.stopPropagation();ReviewsControlCenter.openReply(\'' + esc(r.id) + '\')">' + ic('reply', 11) + ' Reply</button>' +
          (r.moderation === 'FLAGGED'
            ? '<button type="button" class="rev-btn rev-btn-line rev-btn-sm" onclick="event.stopPropagation();ReviewsControlCenter.openResolve(\'' + esc(r.id) + '\')">' + ic('check', 11) + ' Resolve</button>'
            : '<button type="button" class="rev-btn rev-btn-line rev-btn-sm" onclick="event.stopPropagation();ReviewsControlCenter.openFlag(\'' + esc(r.id) + '\')">' + ic('flag', 11) + ' Flag</button>') +
        '</div>' +
      '</div>' +
      respBody +
    '</div>';
  }
  function prioChip(p) {
    return '<span class="rev-prio ' + p.cls + '">' + p.label + '</span>';
  }

  /* ── Themes tab ────────────────────────────────────────────────── */

  function buildThemes() {
    var th = state.ov && state.ov.themes || [];
    if (!th.length) return '<div class="rev-list-tool"></div><p class="rev-empty">No themes detected yet — themes are classified from review text as reviews arrive.</p>';
    var max = Math.max.apply(null, th.map(function(t) { return t.count; })) || 1;
    var worst = th.filter(function(t) { return t.negative > 0; }).slice().sort(function(a, b) { return b.negative - a.negative; })[0];

    var h = '<div class="rev-head-l" style="margin-bottom:.9rem"><h2>Feedback themes</h2>' +
      '<p>Deterministic classification — a 5★ review can still carry a negative nuance (e.g. "great event, but the queues…").</p></div>';

    if (worst) {
      h += '<div class="rev-callout warn">' + ic('alert', 15) +
        '<div><b>' + esc(worst.label) + ' is your most-cited concern</b>' +
        '<p>' + worst.negative + ' negative mention' + (worst.negative === 1 ? '' : 's') + ' out of ' + worst.count + ' — address it before your next event.</p></div>' +
        '<button type="button" class="rev-btn rev-btn-primary rev-btn-sm" onclick="ReviewsControlCenter.goToTheme(\'' + esc(worst.theme) + '\')">Explore ' + ic('chevR', 11) + '</button></div>';
    }

    h += '<div class="rev-block">' + blockH('Theme intensity', 'Positive and negative mentions per theme — '+'click to filter reviews') +
      th.map(function(t) {
        return '<div class="rev-theme-row big" onclick="ReviewsControlCenter.goToTheme(\'' + esc(t.theme) + '\')">' +
          '<span class="rev-theme-name"><b>' + esc(t.label) + '</b></span>' +
          '<div class="rev-theme-track">' +
            '<div class="rev-theme-fill pos" style="width:' + Math.max(2, Math.round(100 * t.positive / max)) + '%"></div>' +
            '<div class="rev-theme-fill neg" style="width:' + Math.max(1, Math.round(100 * t.negative / max)) + '%"></div>' +
          '</div>' +
          '<span class="rev-theme-num"><b>' + t.count + '</b><i class="pos">+' + t.positive + '</i><i class="neg">-' + t.negative + '</i></span></div>';
      }).join('') + '</div>';

    h += '<div class="rev-list-tool"></div>';
    h += '<div class="rev-block">' + blockH('Reviews by theme', 'Theme currently shown in the list below') +
      '<p class="rev-hint">' + ic('bar', 11) + ' Use the All Reviews tab or click a theme above to browse its reviews.</p>' +
      (state.list ? '<div class="rev-cards">' + state.list.rows.map(reviewCard).join('') + '</div>' : '') +
      '</div>';
    return h;
  }

  /* ── Requests tab ──────────────────────────────────────────────── */

  function buildRequests() {
    if (!state.req) return '<p class="rev-empty">Request tracking will appear here once review invites are sent.</p>';
    var fr = state.req.funnel;
    var stages = [
      ['Eligible attendees', fr.eligible],
      ['Invitations sent', fr.sent],
      ['Opened', fr.opened],
      ['Started writing', fr.started],
      ['Submitted a review', fr.submitted]
    ];
    var max = fr.eligible || 1;
    var cone = '';
    stages.forEach(function(s) {
      var pct = Math.max(2, Math.round(100 * s[1] / max));
      cone += '<div class="rev-funnel-row"><span class="rev-funnel-step"></span>' +
        '<div class="rev-funnel-meta"><b>' + s[0] + '</b><em>' + s[1] + '</em></div>' +
        '<div class="rev-funnel-track"><div class="rev-funnel-fill" style="width:' + pct + '%"></div></div></div>';
    });
    var sentRate = fr.sent ? Math.round(100 * fr.opened / fr.sent) : 0;
    var submitRate = fr.sent ? Math.round(100 * fr.submitted / fr.sent) : 0;

    var h = '<div class="rev-head-l" style="margin-bottom:.9rem"><h2>Review request funnel</h2>' +
      '<p>Who was invited to leave feedback after their event, and how the invitation journey converts.</p></div>';

    h += '<div class="rev-cols">' +
      '<div class="rev-block">' + blockH('Invitation funnel') + cone +
        '<div class="rev-funnel-conv"><span>Open rate <b>' + sentRate + '%</b></span><span>Completion rate <b>' + submitRate + '%</b></span></div>' +
        '</div>' +
      '<div class="rev-block">' + blockH('How to earn more reviews', 'Practical levers from your data') +
        '<div class="rev-lever"><span class="rev-lever-ic">' + ic('send', 13) + '</span><div><b>Ask every attendee</b><p>Only ' + fr.sent + ' of ' + fr.eligible + ' eligible attendees were invited to review.</p></div></div>' +
        '<div class="rev-lever"><span class="rev-lever-ic">' + ic('mail', 13) + '</span><div><b>Remind open invites</b><p>' + (fr.opened - fr.started) + ' people opened the invite but never started writing.</p></div></div>' +
        '<div class="rev-lever"><span class="rev-lever-ic">' + ic('reply', 13) + '</span><div><b>Reply to every review</b><p>Attendees who get a response are far more likely to leave one next time.</p></div></div>' +
        '</div>' +
      '</div>';

    h += '<div class="rev-block">' + blockH('Requests per event') +
      '<div class="rev-table-wrap"><table class="rev-table"><thead><tr><th>Event</th><th>Sent</th><th>Opened</th><th>Started</th><th>Submitted</th><th>Open rate</th></tr></thead><tbody>' +
      (state.req.by_event || []).map(function(r) {
        var or = num(r.sent) ? Math.round(100 * num(r.opened) / num(r.sent)) : 0;
        return '<tr><td><b>' + esc(r.event_title) + '</b><br><small>' + date(r.start_date) + '</small></td>' +
          '<td>' + r.sent + '</td><td>' + num(r.opened) + '</td><td>' + num(r.started) + '</td><td><b>' + num(r.submitted) + '</b></td>' +
          '<td><span class="rev-heat"><span class="rev-heat-fill" style="width:' + Math.min(100, or) + '%"></span></span> ' + or + '%</td></tr>';
      }).join('') +
      '</tbody></table></div></div>';
    return h;
  }

  /* ── Modals ────────────────────────────────────────────────────── */

  function openDetail(id) {
    get('detail', { id: id }).then(function(d) {
      var r = d.review;
      if (!r) { toast('Review not found.', true); return; }
      var prio = prioFor(r.rating);
      var verifyBlock = '';
      if (r.verification) {
        var v = r.verification;
        verifyBlock = '<div class="rev-detail-verify' + (r.verified_attendee ? ' ok' : ' no') + '">' +
          ic('shield', 14) +
          '<div><b>' + (r.verified_attendee ? 'Verified attendee' : 'Reviewer not verified') + '</b>' +
          '<p>' + (r.verified_attendee
            ? 'Paid booking ' + esc(v.booking_id || '') + ' · ticket ' + esc(v.ticket_id || '') + ' · checked in on ' + date(v.checked_in_at)
            : 'No paid, checked-in ticket record was found for this review — it may still be genuine feedback, treat it with care.') + '</p></div></div>';
      }
      var thread = '';
      if (r.response) {
        var resp = r.response;
        thread = '<div class="rev-thread">' +
          '<div class="rev-thread-msg cust"><div class="rev-thread-h"><b>' + esc((r.customer || {}).name || 'Reviewer') + '</b><span>' + shortDate(r.created_at) + '</span></div>' +
          '<p class="rev-thread-body">' + esc(r.body || '') + '</p></div>' +
          '<div class="rev-thread-msg org' + (resp.status === 'PUBLISHED' ? '' : ' draft') + '"><div class="rev-thread-h"><b>Organizer response</b> ' + (resp.ai_drafted ? '<span class="rev-tag ai">' + ic('sparkle', 10) + ' AI-assisted</span>' : '') + '<span>' + shortDate(resp.created_at) + '</span></div>' +
          '<p class="rev-thread-body">' + esc(resp.body || '') + '</p>' +
          (resp.status !== 'PUBLISHED' ? '<p class="rev-hint">This response is not yet published.</p>' : '') +
          '</div></div>';
      } else {
        thread = '<div class="rev-thread-msg cust"><div class="rev-thread-h"><b>' + esc((r.customer || {}).name || 'Reviewer') + '</b><span>' + shortDate(r.created_at) + '</span></div>' +
          '<p class="rev-thread-body">' + esc(r.body || '') + '</p></div>';
      }
      var themes = (r.themes || []).map(function(t) {
        return '<span class="rev-theme-chip ' + (t.polarity === 'negative' ? 'neg' : 'pos') + '">' + esc(t.label) + '</span>';
      }).join('');
      var flagInfo = r.moderation === 'FLAGGED'
        ? '<div class="rev-detail-flag warn">' + ic('flag', 13) + '<div><b>Under review by the platform</b><p>Flagged as "' + esc(r.flag_reason || 'other') + '". The review stays visible — the platform decides any removal.</p></div></div>' : '';

      var ft = '<button type="button" class="rev-btn rev-btn-line" onclick="ReviewsControlCenter.openReply(\'' + esc(r.id) + '\')">' + ic('reply', 12) + (r.response ? ' Reply again' : ' Reply') + '</button>' +
        (r.moderation === 'FLAGGED'
          ? '<button type="button" class="rev-btn rev-btn-primary" onclick="ReviewsControlCenter.openResolve(\'' + esc(r.id) + '\')">' + ic('check', 12) + ' Resolve flag</button>'
          : '<button type="button" class="rev-btn rev-btn-line" onclick="ReviewsControlCenter.openFlag(\'' + esc(r.id) + '\')">' + ic('flag', 12) + ' Flag review</button>');

      openModal(r.title || 'Review ' + r.id,
        '<div class="rev-detail-top">' +
          '<div class="rev-detail-stars">' + stars(r.rating, 15) + ' <b>' + r.rating + '.0</b></div>' +
          prioChip(prio) + ' ' +
          '<span class="rev-tag sent ' + sentCls(r.sentiment) + '">' + esc(r.sentiment || '—') + '</span>' +
          (r.status ? '<span class="rev-tag">' + esc(r.status) + '</span>' : '') +
        '</div>' +
        '<div class="rev-detail-meta">' + esc((r.customer || {}).name || 'Anonymous') + ' · ' + esc((r.customer || {}).email || '') + ' · ' + date(r.created_at) + ' · ' + esc((r.event || {}).title || '') + '</div>' +
        (themes ? '<div class="rev-card-themes">' + themes + '</div>' : '') +
        verifyBlock + flagInfo +
        '<div class="rev-thread">' + thread + '</div>',
        ft);
    }).catch(function(e) { toast(e.message, true); });
  }

  function openReply(id) {
    var ta = '<textarea id="rev-reply-body" maxlength="500" placeholder="Write a public response…" style="height:120px">' + '</textarea>';
    var hint = '<p class="rev-hint">Responses are public. Keep it constructive — you are writing to every future attendee.</p>';
    openModal('Reply to review ' + esc(id),
      ta + '<div class="rev-reply-count" id="rev-reply-count">0 / 500</div>' + hint,
      '<button type="button" class="rev-btn rev-btn-line" id="rev-ai-btn" onclick="ReviewsControlCenter.aiDraft(\'' + esc(id) + '\')">' + ic('sparkle', 12) + ' Draft with AI</button>' +
      '<button type="button" class="rev-btn rev-btn-primary" onclick="ReviewsControlCenter.publishReply(\'' + esc(id) + '\')">' + ic('send', 12) + ' Publish reply</button>');
    var el = document.getElementById('rev-reply-body');
    if (el) el.addEventListener('input', function() {
      var c = document.getElementById('rev-reply-count');
      if (c) c.textContent = el.value.length + ' / 500';
      var btn = document.getElementById('rev-ai-btn');
      if (btn && el.value.length > 2) btn.style.display = 'none';
    });
    if (el) el.focus();
  }
  function aiDraft(id) {
    var el = document.getElementById('rev-reply-body');
    if (!el) return;
    var hint = document.createElement('p');
    hint.className = 'rev-ai-note';
    hint.innerHTML = ic('sparkle', 11) + ' Drafting…';
    if (el.parentNode) el.parentNode.insertBefore(hint, el.nextSibling);
    get('ai_draft', { id: id }).then(function(res) {
      el.value = res.draft || '';
      var c = document.getElementById('rev-reply-count');
      if (c) c.textContent = el.value.length + ' / 500';
      if (hint && hint.parentNode) {
        hint.innerHTML = ic('sparkle', 11) + ' ' + esc(res.disclaimer || 'AI draft — review it before publishing. AI text is never published automatically.');
      }
    }).catch(function(e) {
      if (hint && hint.parentNode) hint.remove();
      toast(e.message, true);
    });
  }
  function publishReply(id) {
    var el = document.getElementById('rev-reply-body');
    var body = el ? el.value.trim() : '';
    if (!body) { toast('Write a response before publishing.', true); return; }
    var btn = getModalFt().querySelector('.rev-btn-primary');
    if (btn) { btn.disabled = true; btn.textContent = 'Publishing…'; }
    post({ action: 'respond', review_id: id, body: body }).then(function() {
      toast('Response published.');
      closeModalNow();
      refreshListAndCounts();
    }).catch(function(e) { toast(e.message, true); if (btn) { btn.disabled = false; btn.textContent = 'Publish reply'; } });
  }

  function openFlag(id) {
    var opts = FLAG_REASONS.map(function(r) {
      return '<label class="rev-flag-reason"><input type="radio" name="rev-flag-reason" value="' + r.id + '"' + (r.id === 'INAPPROPRIATE' ? ' checked' : '') + '/><span>' + esc(r.label) + '</span></label>';
    }).join('');
    openModal('Flag review ' + esc(id),
      '<p class="rev-hint">' + ic('alert', 12) + ' Flagging sends the review for platform moderation. The review stays published while under review — your flag cannot erase negative feedback, and the platform decides final removal.</p>' +
      '<div class="rev-flag-reasons">' + opts + '</div>' +
      '<textarea id="rev-flag-notes" placeholder="Details for the moderation team (optional)…" style="height:72px"></textarea>',
      '<button type="button" class="rev-btn rev-btn-line" onclick="ReviewsControlCenter.modalClose()">Cancel</button>' +
      '<button type="button" class="rev-btn rev-btn-primary" onclick="ReviewsControlCenter.submitFlag(\'' + esc(id) + '\')">' + ic('flag', 12) + ' Submit flag</button>');
  }
  function submitFlag(id) {
    var reason = document.querySelector('input[name="rev-flag-reason"]:checked');
    var notesEl = document.getElementById('rev-flag-notes');
    post({ action: 'flag', review_id: id, reason: reason ? reason.value : 'OTHER', notes: notesEl ? notesEl.value.trim() : '' })
      .then(function() {
        toast('Review flagged for moderation.');
        closeModalNow();
        refreshListAndCounts();
      }).catch(function(e) { toast(e.message, true); });
  }

  function openResolve(id) {
    openModal('Resolve flag on ' + esc(id),
      '<div class="rev-resolve">' + ic('shield', 16) +
      '<p><b>One rule: negative feedback is not invalid.</b><br/>A critical review is real feedback. Resolving the flag returns the review to ' +
      '<b>normal visibility</b> — it stays published, and you keep it as feedback to act on. The platform alone decides removal.</p></div>' +
      '<div class="rev-resolve-actions">' +
      '<button type="button" class="rev-btn rev-btn-line" onclick="ReviewsControlCenter.resolveFlag(\'' + esc(id) + '\',\'DISMISSED\')">' + ic('check', 12) + ' Keep review &amp; close flag</button>' +
      '<button type="button" class="rev-btn rev-btn-line" onclick="ReviewsControlCenter.resolveFlag(\'' + esc(id) + '\',\'REMOVED\')">' + ic('x', 12) + ' Close — platform to remove</button></div>',
      '<button type="button" class="rev-btn rev-btn-line" onclick="ReviewsControlCenter.modalClose()">Cancel</button>');
  }
  function resolveFlag(id, outcome) {
    post({ action: 'resolve_flag', review_id: id, outcome: outcome }).then(function() {
      toast(outcome === 'REMOVED' ? 'Flag closed — removal is with the platform.' : 'Flag closed — review kept and visible.');
      closeModalNow();
      refreshListAndCounts();
    }).catch(function(e) { toast(e.message, true); });
  }

  function openSettings() {
    var c = state.config || {};
    var p = c.priority || { critical: 2, high: 3, normal: 4, low: 5 };
    function tf(key, label) {
      return '<label class="rev-set-row"><span>' + label + '</span><span class="rev-switch"><input type="checkbox" id="rev-set-' + key + '" ' + (c[key] ? 'checked' : '') + '/><i></i></span></label>';
    }
    openModal('Review settings',
      '<p class="rev-hint">These settings shape when review requests are sent, how reviews publish, and which ratings deserve your priority.</p>' +
      '<div class="rev-set-group"><h5>Collection</h5>' +
      tf('collect_enabled', 'Collect reviews after events') +
      '<label class="rev-set-row"><span>Delay before sending the invite</span><input type="number" id="rev-set-delay" min="0" max="720" value="' + num(c.request_delay_hours) + '"/> <em>hours</em></label>' +
      '<div class="rev-set-row"><span>Invite channels</span><div class="rev-set-chips">' +
        [['channel_uthenga', 'Uthenga'], ['channel_email', 'Email'], ['channel_sms', 'SMS']].map(function(ch) {
          return '<label class="rev-chip-check"><input type="checkbox" id="rev-set-' + ch[0] + '" ' + (c[ch[0]] ? 'checked' : '') + '/><span>' + ch[1] + '</span></label>';
        }).join('') + '</div></div>' +
      '</div>' +
      '<div class="rev-set-group"><h5>Publishing</h5>' +
      '<label class="rev-set-row"><span>New reviews</span><select id="rev-set-mode">' +
        '<option value="AUTO"' + (c.publish_mode === 'AUTO' ? ' selected' : '') + '>Publish immediately (auto)</option>' +
        '<option value="MODERATED"' + (c.publish_mode === 'MODERATED' ? ' selected' : '') + '>Hold for organizer review</option>' +
      '</select></label>' +
      '</div>' +
      '<div class="rev-set-group"><h5>Notifications</h5>' +
      tf('notify_new', 'On new review') + tf('notify_negative', 'On negative review') + tf('notify_reply', 'When a reply is published') +
      '</div>' +
      '<div class="rev-set-group"><h5>Priority thresholds (stars)</h5>' +
      '<p class="rev-hint">Ratings at or below these values are tagged for priority attention.</p>' +
      '<div class="rev-set-prio">' +
        [['critical', 'Critical'], ['high', 'High'], ['normal', 'Normal'], ['low', 'Low']].map(function(pr) {
          return '<label class="rev-prio-in"><span>' + pr[1] + '</span><input type="number" id="rev-prio-' + pr[0] + '" min="1" max="5" value="' + num(p[pr[0]] || 1) + '"/></label>';
        }).join('') +
      '</div></div>' +
      '<label class="rev-set-row">' + tf('incentive_enabled', 'Allow thank-you incentives for verified reviewers') +
      '<p class="rev-hint">Incentives are only offered to attendees with paid, checked-in tickets — never in exchange for a five-star rating.</p></label>',
      '<button type="button" class="rev-btn rev-btn-line" onclick="ReviewsControlCenter.modalClose()">Cancel</button>' +
      '<button type="button" class="rev-btn rev-btn-primary" onclick="ReviewsControlCenter.saveSettings()">' + ic('check', 12) + ' Save settings</button>');
  }
  function saveSettings() {
    var v = function(id) { var el = document.getElementById(id); return el ? el.checked : false; };
    var nv = function(id) { var el = document.getElementById(id); return el ? Number(el.value || 0) : 0; };
    var payload = {
      action: 'save_config',
      collect_enabled: v('rev-set-collect_enabled') ? 1 : 0,
      request_delay_hours: nv('rev-set-delay'),
      channel_uthenga: v('rev-set-channel_uthenga') ? 1 : 0,
      channel_email: v('rev-set-channel_email') ? 1 : 0,
      channel_sms: v('rev-set-channel_sms') ? 1 : 0,
      publish_mode: (document.getElementById('rev-set-mode') || {}).value || 'AUTO',
      notify_new: v('rev-set-notify_new') ? 1 : 0,
      notify_negative: v('rev-set-notify_negative') ? 1 : 0,
      notify_reply: v('rev-set-notify_reply') ? 1 : 0,
      incentive_enabled: v('rev-set-incentive_enabled') ? 1 : 0,
      priority: {
        critical: nv('rev-prio-critical'),
        high: nv('rev-prio-high'),
        normal: nv('rev-prio-normal'),
        low: nv('rev-prio-low')
      }
    };
    post(payload).then(function(res) {
      state.config = res;
      toast('Review settings saved.');
      closeModalNow();
      pane();
    }).catch(function(e) { toast(e.message, true); });
  }

  /* ── Ask ───────────────────────────────────────────────────────── */

  function ask() {
    var el = document.getElementById('rev-ask-q');
    var q = el ? el.value.trim() : '';
    if (!q) { toast('Type a question about your feedback.', true); return; }
    var ans = document.getElementById('rev-ask-answer');
    if (ans) { ans.style.display = 'block'; ans.innerHTML = '<div class="rev-ask-loading">' + ic('refresh', 12) + ' Looking through ' + (state.ov ? state.ov.kpis.total_reviews.value : 'your') + ' reviews…</div>'; }
    get('ask', { q: q }).then(function(res) {
      if (!ans) return;
      var data = res.data;
      var extra = '';
      if (data && data.rows && data.rows.length) {
        extra = '<div class="rev-ask-reviews">' + data.rows.slice(0, 4).map(function(r) {
          return '<button type="button" class="rev-ask-review" onclick="ReviewsControlCenter.openDetail(\'' + esc(r.id) + '\')">' +
            '<span class="rev-ask-stars">' + stars(r.rating, 10) + '</span>' +
            '<span class="rev-ask-title">' + esc(r.title || '') + '</span>' +
            '<span class="rev-ask-body">' + esc((r.body || '').slice(0, 90)) + (r.body && r.body.length > 90 ? '…' : '') + '</span></button>';
        }).join('') + '</div>';
      } else if (data && data.length) {
        extra = '<div class="rev-ask-themes">' + data.map(function(t) {
          return '<span class="rev-theme-chip ' + (t.negative > 0 ? 'neg' : 'pos') + '">' + esc(t.label) + ' (+' + t.positive + '/−' + t.negative + ')</span>';
        }).join('') + '</div>';
      }
      ans.innerHTML = '<div class="rev-ask-q">' + esc(q) + '</div>' +
        '<div class="rev-ask-a">' + ic('robot', 14) + ' <span>' + esc(res.answer) + '</span></div>' + extra;
    }).catch(function(e) {
      if (ans) { ans.style.display = 'block'; ans.innerHTML = '<div class="rev-ask-q">' + esc(q) + '</div><div class="rev-ask-a err">' + ic('alert', 13) + ' ' + esc(e.message) + '</div>'; }
    });
  }
  function suggest(q) {
    var el = document.getElementById('rev-ask-q');
    if (el) el.value = q;
    ask();
  }

  /* ── Modal plumbing ────────────────────────────────────────────── */

  function openModal(title, body, ft) {
    var m = document.getElementById('rev-modal-overlay');
    if (!m) return;
    m.style.display = 'flex';
    void m.offsetWidth;
    m.classList.add('active');
    document.getElementById('rev-modal-title').textContent = title;
    document.getElementById('rev-modal-bd').innerHTML = body;
    document.getElementById('rev-modal-ft').innerHTML = ft || '';
  }
  function getModalFt() { return document.getElementById('rev-modal-ft') || document.createElement('div'); }
  function modalClose() { closeModalNow(); }
  function closeModalNow() {
    var m = document.getElementById('rev-modal-overlay');
    if (m) { m.classList.remove('active'); setTimeout(function() { m.style.display = 'none'; }, 200); }
  }
  function getModalBd() { return document.getElementById('rev-modal-bd'); }

  function updateTabBadges() {
    var tabs = document.getElementById('rev-tabs');
    if (!tabs || !state.ov) return;
    var needs = state.ov.kpis.unanswered.value;
    var flagged = state.ov.flagged_count || 0;
    tabs.querySelectorAll('.rev-tab').forEach(function(b) {
      var n = b.getAttribute('data-tab');
      var em = b.querySelector('em');
      if (em) em.remove();
      var count = n === 'needs' ? needs : (n === 'flagged' ? flagged : 0);
      if (count) b.insertAdjacentHTML('beforeend', '<em>' + count + '</em>');
    });
  }

  /* ── Data loading ──────────────────────────────────────────────── */

  function loadEvents() {
    return get('events').then(function(evs) {
      state.events = evs || [];
      return get('config');
    }).then(function(cfg) { state.config = cfg; });
  }
  function loadOverview() {
    return get('overview', { event_id: state.filters.event_id }).then(function(o) {
      state.ov = o;
    });
  }
  function loadList() {
    var f = state.filters;
    var params = {
      event_id: f.event_id === 'all' ? '' : f.event_id,
      rating: f.rating || 0,
      status: state.tab === 'needs' ? 'unanswered' : (state.tab === 'flagged' ? 'flagged' : (f.status === 'all' ? '' : f.status)),
      theme: f.theme,
      q: f.q,
      sort: f.sort,
      page: f.page,
      limit: 12
    };
    return get('list', params).then(function(l) { state.list = l; });
  }
  function loadRequests() {
    return get('requests', state.filters.event_id === 'all' ? {} : { event_id: state.filters.event_id }).then(function(r) { state.req = r; });
  }
  function loadTab(tab) {
    state.loading = true;
    pane();
    var jobs = [];
    switch (tab) {
      case 'overview':
        jobs.push(loadOverview().catch(function(e) { toast(e.message, true); }));
        break;
      case 'themes':
        if (!state.ov) jobs.push(loadOverview());
        jobs.push(loadList().catch(function(e) { toast(e.message, true); }));
        break;
      case 'requests':
        jobs.push(loadRequests().catch(function(e) { toast(e.message, true); }));
        break;
      default:
        jobs.push(loadList().catch(function(e) { toast(e.message, true); }));
    }
    Promise.all(jobs).then(function() {
      state.loading = false;
      pane();
      updateTabBadges();
    });
  }
  function refreshListAndCounts() {
    var jobs = [];
    jobs.push(loadOverview().catch(function() {}));
    if (state.tab !== 'overview' && state.tab !== 'requests') jobs.push(loadList().catch(function() {}));
    Promise.all(jobs).then(function() {
      pane();
      updateTabBadges();
    });
  }

  /* ── User actions ──────────────────────────────────────────────── */

  function setTab(id) {
    if (id === state.tab) return;
    state.tab = id;
    state.filters.page = 1;
    if (id === 'themes') state.filters.theme = '';
    if (id === 'needs' || id === 'flagged' || id === 'overview') state.filters.theme = '';
    shell();
    loadTab(id);
  }
  function setEvent(id) {
    state.filters.event_id = id;
    state.filters.page = 1;
    state.ov = null;
    state.list = null;
    state.req = null;
    loadTab(state.tab);
  }
  function filterRating(r) {
    r = num(r);
    state.filters.rating = r === state.filters.rating ? 0 : r;
    state.filters.page = 1;
    state.list = null;
    loadTab(state.tab);
  }
  function applySearch() {
    var el = document.getElementById('rev-q');
    state.filters.q = el ? el.value.trim() : '';
    state.filters.page = 1;
    state.list = null;
    loadTab(state.tab);
  }
  function setSort(s) {
    state.filters.sort = s;
    state.filters.page = 1;
    loadTab(state.tab);
  }
  function goPage(p) {
    var l = state.list;
    if (!l || p < 1 || p > num(l.pages)) return;
    state.filters.page = p;
    loadTab(state.tab);
  }
  function go(link) {
    if (link === 'needs') return setTab('needs');
    if (link === 'themes') return setTab('themes');
    if (link === 'flagged') return setTab('flagged');
    setTab('all');
  }
  function goToTheme(theme) {
    state.tab = 'all';
    var label = '';
    (state.ov.themes || []).forEach(function(t) { if (t.theme === theme) label = t.label; });
    state.filters.theme = theme;
    state.filters.themeLabel = label;
    state.filters.page = 1;
    state.list = null;
    shell();
    loadTab('all');
  }
  function clearTheme() {
    state.filters.theme = '';
    state.filters.themeLabel = '';
    state.filters.page = 1;
    state.list = null;
    loadTab(state.tab);
  }
  function filterEvent(eventId) {
    state.filters.event_id = eventId;
    state.filters.page = 1;
    state.ov = null;
    state.list = null;
    state.req = null;
    setTab('all');
  }
  function exportCsv() {
    var params = {
      action: 'export',
      event_id: state.filters.event_id === 'all' ? '' : state.filters.event_id,
      limit: 2000
    };
    fetch(api + '?' + qs(params), { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function(r) { return r.json(); })
      .then(function(j) {
        if (!j || j.success !== true) { toast(j && j.error && j.error.message || 'Export failed.', true); return; }
        var rows = (j.reviews_result && j.reviews_result.rows) || [];
        if (!rows.length) { toast('Nothing to export yet.', true); return; }
        var head = ['id', 'rating', 'title', 'body', 'sentiment', 'status', 'verified_attendee', 'booking_id', 'ticket_id', 'checked_in_at', 'customer_name', 'customer_email', 'event_title', 'created_at', 'response_body'];
        var lines = [head.join(',')];
        rows.forEach(function(r) {
          var v = r.verification || {};
          var c = r.customer || {};
          var e = r.event || {};
          lines.push([r.id, r.rating, CSV(r.title), CSV(r.body), r.sentiment, r.status, r.verified_attendee ? 'yes' : 'no',
            v.booking_id, v.ticket_id, v.checked_in_at, c.name, c.email, e.title, r.created_at,
            CSV(r.response ? r.response.body : '')].join(','));
        });
        var res = (j.reviews_result.summary || {});
        lines.unshift('"Uthenga reviews export — ' + (res.overall_rating || '') + ' avg · ' + (res.total_reviews || '') + ' reviews · response rate ' + (res.response_rate || '') + '"');
        var blob = new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'uthenga-reviews-' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        setTimeout(function() { URL.revokeObjectURL(a.href); }, 8000);
        toast('Exported ' + rows.length + ' reviews.');
      })
      .catch(function(e) { toast(e.message, true); });
  }
  function CSV(s) {
    s = String(s == null ? '' : s).replace(/"/g, '""');
    return /[",\n]/.test(s) ? '"' + s + '"' : s;
  }
  function refresh() {
    state.ov = null; state.list = null; state.req = null;
    loadTab(state.tab);
  }

  /* ── Boot ──────────────────────────────────────────────────────── */

  var ROOT = null;
  function init() {
    var root = document.getElementById('rev-root');
    if (!root) return;
    ROOT = root;
    if (state.booted) { pane(); return; }
    state.booted = true;
    root.innerHTML = '<div class="rev-loading">' + ic('refresh', 16) + ' Loading your reviews…</div>';
    loadEvents().then(function() {
      shell();
      loadTab('overview');
    }).catch(function(e) { toast(e.message, true); });
  }

  return {
    init: init,
    refresh: refresh,
    setTab: setTab,
    setEvent: setEvent,
    filterRating: filterRating,
    applySearch: applySearch,
    setSort: setSort,
    goPage: goPage,
    go: go,
    goToTheme: goToTheme,
    clearTheme: clearTheme,
    filterEvent: filterEvent,
    openDetail: openDetail,
    openReply: openReply,
    aiDraft: aiDraft,
    publishReply: publishReply,
    openFlag: openFlag,
    submitFlag: submitFlag,
    openResolve: openResolve,
    resolveFlag: resolveFlag,
    openSettings: openSettings,
    saveSettings: saveSettings,
    ask: ask,
    suggest: suggest,
    exportCsv: exportCsv,
    modalClose: modalClose
  };
})();

/* Auto-engage when the user opens the Reviews tab. */
(function() {
  var prev = window.onEccModuleShow;
  window.onEccModuleShow = function(modId) {
    if (typeof prev === 'function') { try { prev(modId); } catch (e) {} }
    if (modId === 'reviews' && window.ReviewsControlCenter) {
      window.ReviewsControlCenter.init();
    }
  };
})();