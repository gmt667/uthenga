/* Uthenga — Venues Console controller (Events V2).
 * Renders the operational venue directory, the 8-step Add Venue wizard and
 * the per-venue manage workspace (Overview / Details / Spaces / Availability /
 * Pricing / Facilities / Media / Policies / Documents / Activity) backed by
 * api/tie/vendor/events/venues.php. Relies on the Events Control Center
 * runtime helpers (tkEsc, tkMoney, tkDate, tkDateTime, eccNotify, ecc modals).
 */
window.VenuesControlCenter = (function() {
  'use strict';

  var vcDoc = document.getElementById('events-workspace');
  var base = (vcDoc && vcDoc.dataset.baseUrl) ? vcDoc.dataset.baseUrl : '';
  var csrf = (vcDoc && vcDoc.dataset.csrf) ? vcDoc.dataset.csrf : '';
  var vcApi = base + 'api/tie/vendor/events/venues.php';
  var tkApi = base + 'api/tie/vendor/events/tickets.php';

  var VENUE_TYPES = ['Conference Centre','Stadium','Convention Centre','Hall','Auditorium','Outdoor','Hotel','Restaurant','Theatre','Community','Private','Other'];
  var SPACE_TYPES = ['Theatre','Classroom','Banquet','Boardroom','U-Shape','Cabaret','Standing','Custom'];
  var PRICE_PRESETS = [
    { name: 'Standard Day',   price: '750000',  desc: 'Full day hire (08:00 - 17:00).' },
    { name: 'Half Day',       price: '450000',  desc: 'Half day hire (max 4 hours).' },
    { name: 'Evening',        price: '600000',  desc: 'Evening hire (17:00 - 23:00).' },
    { name: 'Weekend',        price: '900000',  desc: 'Full weekend hire (Sat - Sun).' }
  ];
  var FACILITY_PRESETS = {
    GENERAL: [
      ['Backup generator', 'Full power backup for the venue.'],
      ['Water supply', 'Reliable on-site water.'],
      ['Toilets & washrooms', 'Sanitary facilities for guests.'],
      ['Air conditioning', 'Climate control in indoor spaces.'],
      ['Lighting', 'Hall lighting rigs.'],
      ['Parking', 'Guest parking on site.']
    ],
    TECHNOLOGY: [
      ['Wi-Fi', 'High-speed internet access.'],
      ['Projector', 'Projection equipment.'],
      ['Screens', 'Presentation screens.'],
      ['Sound system', 'PA system and microphones.'],
      ['Stage & lectern', 'Dedicated stage area.'],
      ['Live recording', 'Audio / video recording support.'],
      ['Video conferencing', 'Hybrid meeting hardware.']
    ],
    ACCESSIBILITY: [
      ['Wheelchair access', 'Ramped entrances and routes.'],
      ['Accessible toilets', 'Disabled-friendly washrooms.'],
      ['Elevators', 'Accessible lifts.'],
      ['Reserved parking', 'Priority accessible parking.'],
      ['Assistance staff', 'On-call assistance for guests.']
    ],
    HOSPITALITY: [
      ['Catering', 'In-house catering team.'],
      ['Kitchen', 'Professional kitchen access.'],
      ['Dining area', 'On-site dining space.'],
      ['VIP room', 'Private hosting lounge.'],
      ['Refreshments', 'Water stations and service.']
    ],
    SECURITY: [
      ['Security personnel', 'Trained guards on site.'],
      ['CCTV', '24/7 monitored cameras.'],
      ['Controlled entrances', 'Checkpoint entry points.'],
      ['Emergency exits', 'Clearly marked exits.'],
      ['First aid', 'On-site medical kit and staff.']
    ]
  };
  var RESTRICTION_PRESETS = ['Alcohol','Smoking','Amplified sound','External catering','Decorations','Animals','Political gatherings','Age restricted entry'];
  var SUB_TABS = [
    ['overview', 'Overview'], ['details', 'Details'], ['spaces', 'Spaces'], ['availability', 'Availability'],
    ['pricing', 'Pricing'], ['facilities', 'Facilities'], ['media', 'Media'], ['policies', 'Policies'],
    ['documents', 'Documents'], ['activity', 'Activity']
  ];

  var state = {
    venues: [], stats: null, view: 'grid', search: '',
    detail: null, venueId: null, sub: 'overview',
    calVenueId: null, calMonth: null, calData: null,
    vwStep: 1, vwData: null,
    assignVenueId: null, assignVenueName: '',
    eventsLoaded: false, events: [],
    mediaDraft: null, pricingDraft: null
  };

  function esc(s) { return window.tkEsc ? tkEsc(s) : String(s == null ? '' : s); }
  function icon(name) {
    var p = {
      pin: '<path d="M8 1.5a4.5 4.5 0 0 0-4.5 4.5c0 3.4 4.5 8.5 4.5 8.5s4.5-5.1 4.5-8.5A4.5 4.5 0 0 0 8 1.5Z" /><circle cx="8" cy="6" r="1.6" />',
      calendar: '<rect x="2.5" y="4" width="11" height="10.5" rx="2" /><path d="M2.5 7.5h11M6 2.5v3M10 2.5v3" />',
      clock: '<circle cx="8" cy="8.5" r="5.5" /><path d="M8 5.5v3l2 1.5" />',
      bulb: '<path d="M8 2a4.5 4.5 0 0 0-2.4 8.3c.7.5 1 1.2 1 2.2h2.8c0-1 .3-1.7 1-2.2A4.5 4.5 0 0 0 8 2Z" /><path d="M6.8 14h2.4M7.5 14.8v.1" />',
      doc: '<path d="M4 1.5h5.5L13 5v9.5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-12a1 1 0 0 1 1-1Z" /><path d="M9.5 1.5V5H13" />',
      check: '<path d="M3 8.5l3.2 3.2L13 5" />',
      warn: '<path d="M8 2.5l6.5 11h-13L8 2.5Z" /><path d="M8 6.5v3.2M8 11.9v.1" />',
      pencil: '<path d="M3.5 12.5l.6-2.6L11.5 2.5a1.1 1.1 0 0 1 1.6 1.6L5.7 11.5l-2.6.6Z" /><path d="M10.6 3.4l1.9 1.9" />'
    };
    return '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-0.14em;flex-shrink:0;">' + (p[name] || '') + '</svg>';
  }
  function money(n, c) { return window.tkMoney ? tkMoney(n, c) : ('MWK ' + (Number(n) || 0)); }
  function fmtDate(d) { return window.tkDate ? tkDate(d) : String(d || '—'); }
  function fmtDt(d) { return window.tkDateTime ? tkDateTime(d) : String(d || '—'); }
  function toast(m) { if (window.eccNotify) window.eccNotify(m); }

  function venPill(status) {
    var s = String(status || 'ACTIVE').toUpperCase();
    var cls = (s === 'ACTIVE') ? 'green' : ((s === 'DRAFT' || s === 'PENDING_REVIEW' || s === 'TEMPORARILY_UNAVAILABLE') ? 'amber' : 'rose');
    return '<span class="ecc-pill ' + cls + '" style="font-size:0.62rem;">' + esc(s.replace(/_/g, ' ')) + '</span>';
  }
  function chip(status) {
    var cls = 'var(--ecc-surface-3)';
    if (status === 'EVENT') cls = '#4f46e5';
    else if (status === 'SETUP') cls = '#7c3aed';
    else if (status === 'RESERVED') cls = '#b45309';
    else if (status === 'MAINTENANCE') cls = '#be123c';
    else if (status === 'BLOCKED') cls = '#475569';
    return cls;
  }
  function timePart(d) {
    var t = String(d || '');
    return t.length >= 16 ? t.slice(11, 16) : '';
  }
  function ymd(d) {
    var t = String(d || '');
    return t.replace('T', ' ').slice(0, 10);
  }

  /* ── API ─────────────────────────────────────────────────── */
  function get(action, params) {
    var qs = '?action=' + encodeURIComponent(action);
    Object.keys(params || {}).forEach(function(k) { qs += '&' + k + '=' + encodeURIComponent(params[k]); });
    return fetch(vcApi + qs, { credentials: 'same-origin' }).then(function(r) { return r.json().catch(function() { return {}; }); });
  }
  function post(data) {
    return fetch(vcApi, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(data)
    }).then(function(r) { return r.json().catch(function() { return {}; }); });
  }
  function errMsg(b) {
    return (b && b.error && b.error.message) ? b.error.message : 'The request could not be completed.';
  }

  /* ── Console (directory) ─────────────────────────────────── */
  function loadWorkspace() {
    return get('workspace', { search: state.search }).then(function(b) {
      if (!b.success) { toast('Venues: ' + errMsg(b)); return; }
      var res = b.venue_result || {};
      state.venues = res.venues || [];
      state.stats = res.stats || { total: 0, available: 0, occupied: 0, upcoming_events: 0, maintenance: 0 };
      renderKPIs();
      renderGrid();
      renderList();
      if (state.view === 'cal' && state.calVenueId === null && state.venues.length) state.calVenueId = state.venues[0].id;
      if (state.view === 'cal') renderConsoleCalendar();
    });
  }

  function renderKPIs() {
    var el = document.getElementById('vc-kpis');
    if (!el) return;
    var s = state.stats;
    var cards = [
      ['Venues', s.total || 0, 'var(--ecc-primary)', '▦'],
      ['Available', s.available || 0, '#22c55e', '✓'],
      ['In Use Right Now', s.occupied || 0, '#f59e0b', '◈'],
      ['Upcoming Events', s.upcoming_events || 0, '#60a5fa', '★'],
      ['In Maintenance', s.maintenance || 0, '#fb7185', '△']
    ];
    var h = '';
    cards.forEach(function(c) {
      h += '<div class="vc-kpi"><div style="font-size:0.62rem;color:var(--ecc-text-dim);font-weight:700;letter-spacing:0.05em;">' + c[3] + ' ' + esc(c[0]) + '</div>' +
           '<div style="font-size:1.35rem;font-weight:900;color:' + c[2] + ';margin-top:0.15rem;">' + c[1] + '</div></div>';
    });
    el.innerHTML = h;
  }

  function coverStyle(v) {
    var url = v.cover_image || '';
    if (url) return 'background-image:url(' + esc(url) + ');background-size:cover;background-position:center;';
    return 'background:linear-gradient(135deg,#312e81,#7c3aed);';
  }
  function coverLetter(v) {
    return v.cover_image ? '' : '<span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:2rem;color:rgba(255,255,255,0.85);font-weight:900;">' + esc(String(v.name || 'V').charAt(0).toUpperCase()) + '</span>';
  }

  function renderGrid() {
    var el = document.getElementById('vc-grid');
    if (!el) return;
    var list = state.venues;
    if (!list.length) {
      el.innerHTML = '<div class="ecc-tk-empty" style="padding:3rem;">No venues yet. <button type="button" class="ecc-btn ecc-btn-primary" style="margin-left:0.5rem;font-size:0.74rem;" onclick="VenuesControlCenter.wizardOpen()">+ Add your first venue</button></div>';
      return;
    }
    var h = '<div class="vc-grid">';
    list.forEach(function(v) {
      h += '<div class="vc-card">';
      h += '<div class="vc-card-img" style="' + coverStyle(v) + '">' + coverLetter(v);
      h += '<div style="position:absolute;top:0.5rem;right:0.5rem;">' + venPill(v.status) + '</div>';
      if (v.verification_status === 'VERIFIED') h += '<div style="position:absolute;top:0.5rem;left:0.5rem;"><span class="ecc-pill green" style="font-size:0.58rem;">✓ VERIFIED</span></div>';
      h += '</div>';
      h += '<div class="vc-card-body">';
      h += '<div style="display:flex;justify-content:space-between;gap:0.5rem;align-items:flex-start;">';
      h += '<div style="font-weight:900;font-size:0.88rem;line-height:1.25;">' + esc(v.name) + '</div>';
      if (v.type) h += '<span class="ecc-pill purple" style="font-size:0.58rem;flex-shrink:0;">' + esc(v.type) + '</span>';
      h += '</div>';
      h += '<div style="font-size:0.68rem;color:var(--ecc-text-dim);margin:0.3rem 0 0.55rem;">' + esc([v.city, v.district, v.region].filter(Boolean).join(' · ')) + '</div>';
      h += '<div style="display:flex;gap:0.4rem;flex-wrap:wrap;font-size:0.66rem;margin-bottom:0.55rem;">';
      h += '<span class="ecc-chip">' + esc(String(v.capacity || 0).replace(/\B(?=(\d{3})+(?!\d))/g, ',')) + ' cap</span>';
      h += '<span class="ecc-chip">' + (v.spaces_count || 0) + ' space' + ((v.spaces_count || 0) === 1 ? '' : 's') + '</span>';
      if (v.min_price) h += '<span class="ecc-chip">from ' + money(v.min_price, true) + '</span>';
      h += '</div>';
      if (v.next_event) {
        h += '<div style="background:rgba(79,70,229,0.12);border:1px solid rgba(79,70,229,0.25);color:#a5b4fc;font-size:0.66rem;padding:0.3rem 0.5rem;border-radius:6px;margin-bottom:0.6rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">◈ ' + esc(v.next_event.title) + ' · ' + fmtDt(v.next_event.event_start) + '</div>';
      } else {
        h += '<div style="font-size:0.65rem;color:var(--ecc-text-dim);margin-bottom:0.6rem;">No upcoming bookings</div>';
      }
      h += '<div style="display:flex;gap:0.4rem;align-items:center;">';
      h += '<button type="button" class="ecc-btn ecc-btn-primary" style="flex:1;font-size:0.68rem;padding:0.34rem 0.5rem;" onclick="VenuesControlCenter.openWorkspace(\'' + esc(v.id) + '\')">Manage</button>';
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.34rem 0.5rem;" title="Calendar" onclick="VenuesControlCenter.jumpToCal(\'' + esc(v.id) + '\')">▤</button>';
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.34rem 0.55rem;" title="Options" onclick="VenuesControlCenter.toggleCardMenu(\'' + esc(v.id) + '\')">⋯</button>';
      h += '</div>';
      h += '<div class="vc-card-menu" id="vc-menu-' + esc(v.id) + '" style="display:none;margin-top:0.55rem;">';
      h += '<div style="display:flex;gap:0.4rem;">';
      h += '<select class="ecc-input" style="flex:1;font-size:0.68rem;padding:0.3rem;" onchange="VenuesControlCenter.quickStatus(\'' + esc(v.id) + '\', this.value)">';
      ['ACTIVE', 'TEMPORARILY_UNAVAILABLE', 'MAINTENANCE', 'SUSPENDED'].forEach(function(s) {
        h += '<option value="' + s + '"' + (v.status === s ? ' selected' : '') + '>' + s.replace(/_/g, ' ') + '</option>';
      });
      h += '</select>';
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.3rem;" onclick="VenuesControlCenter.removeVenue(\'' + esc(v.id) + '\')">Delete</button>';
      h += '</div></div>';
      h += '</div></div>';
    });
    h += '</div>';
    el.innerHTML = h;
  }

  function renderList() {
    var el = document.getElementById('vc-list');
    if (!el) return;
    if (!state.venues.length) {
      el.innerHTML = '<div class="ecc-tk-empty">No venues match your search.</div>';
      return;
    }
    var h = '<div class="ecc-card" style="overflow-x:auto;"><table class="vc-table">';
    h += '<thead><tr><th>Venue</th><th>Location</th><th>Type</th><th>Capacity</th><th>Spaces</th><th>Status</th><th>Next Event</th><th></th></tr></thead><tbody>';
    state.venues.forEach(function(v) {
      h += '<tr>';
      h += '<td><div style="display:flex;align-items:center;gap:0.55rem;"><div style="width:34px;height:34px;border-radius:8px;overflow:hidden;flex-shrink:0;font-size:13px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;' + coverStyle(v) + '">' + (v.cover_image ? '' : esc(String(v.name || 'V').charAt(0))) + '</div><strong style="font-size:0.78rem;">' + esc(v.name) + '</strong></div></td>';
      h += '<td style="font-size:0.72rem;">' + esc([v.city, v.district, v.region].filter(Boolean).join(', ') || '—') + '</td>';
      h += '<td style="font-size:0.72rem;">' + esc(v.type || '—') + '</td>';
      h += '<td style="font-size:0.72rem;">' + esc(String(v.capacity || 0)) + '</td>';
      h += '<td style="font-size:0.72rem;">' + (v.spaces_count || 0) + '</td>';
      h += '<td>' + venPill(v.status) + '</td>';
      h += '<td style="font-size:0.7rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + (v.next_event ? esc(v.next_event.title) + ' · ' + fmtDate(v.next_event.event_start) : '—') + '</td>';
      h += '<td><button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.66rem;padding:0.3rem 0.6rem;" onclick="VenuesControlCenter.openWorkspace(\'' + esc(v.id) + '\')">Manage</button></td>';
      h += '</tr>';
    });
    h += '</tbody></table></div>';
    el.innerHTML = h;
  }

  /* ── Calendar (console) ──────────────────────────────────── */
  function jumpToCal(id) {
    state.view = 'cal';
    if (!state.calMonth) {
      var now = new Date();
      state.calMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    }
    state.calVenueId = id || (state.venues.length ? state.venues[0].id : null);
    syncViewBtns();
    document.getElementById('vc-grid').style.display = 'none';
    document.getElementById('vc-list').style.display = 'none';
    document.getElementById('vc-cal').style.display = 'block';
    renderConsoleCalendar();
  }

  function switchView(v) {
    state.view = v;
    syncViewBtns();
    document.getElementById('vc-grid').style.display = v === 'grid' ? 'block' : 'none';
    document.getElementById('vc-list').style.display = v === 'list' ? 'block' : 'none';
    document.getElementById('vc-cal').style.display = v === 'cal' ? 'block' : 'none';
    if (v === 'cal') renderConsoleCalendar();
  }
  function syncViewBtns() {
    var btns = document.querySelectorAll('.vc-view-btn');
    btns.forEach(function(b) { b.classList.toggle('active', b.getAttribute('data-vc-view') === state.view); });
  }

  function renderConsoleCalendar() {
    var el = document.getElementById('vc-cal');
    if (!el) return;
    if (!state.venues.length) { el.innerHTML = '<div class="ecc-tk-empty">Add a venue to see its calendar.</div>'; return; }
    if (!state.calVenueId) state.calVenueId = state.venues[0].id;
    var now = new Date();
    var month = state.calMonth || (now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0'));
    var h = '<div style="display:flex;justify-content:space-between;align-items:center;gap:0.6rem;flex-wrap:wrap;margin-bottom:0.8rem;">';
    h += '<select class="ecc-input" style="width:240px;font-size:0.75rem;" onchange="VenuesControlCenter.calVenueChanged(this.value)">';
    state.venues.forEach(function(v) {
      h += '<option value="' + esc(v.id) + '"' + (v.id === state.calVenueId ? ' selected' : '') + '>' + esc(v.name) + '</option>';
    });
    h += '</select>';
    h += '<div style="display:flex;gap:0.4rem;align-items:center;">';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.7rem;padding:0.3rem 0.6rem;" onclick="VenuesControlCenter.calNav(-1)">←</button>';
    h += '<strong style="font-size:0.8rem;min-width:95px;text-align:center;">' + esc(month) + '</strong>';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.7rem;padding:0.3rem 0.6rem;" onclick="VenuesControlCenter.calNav(1)">→</button>';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.7rem;padding:0.3rem 0.6rem;" onclick="VenuesControlCenter.calToday()">Today</button>';
    h += '</div></div>';
    h += '<div id="vc-cal-grid" style="font-size:0.72rem;">Loading…</div>';
    h += '<div style="display:flex;gap:0.9rem;flex-wrap:wrap;margin-top:0.6rem;font-size:0.64rem;color:var(--ecc-text-dim);">';
    h += '<span><span class="vc-legend" style="background:#4f46e5;"></span>Event</span>';
    h += '<span><span class="vc-legend" style="background:#7c3aed;"></span>Setup / Teardown</span>';
    h += '<span><span class="vc-legend" style="background:#b45309;"></span>Reserved</span>';
    h += '<span><span class="vc-legend" style="background:#475569;"></span>Blocked</span>';
    h += '<span><span class="vc-legend" style="background:#be123c;"></span>Maintenance</span>';
    h += '<span style="margin-left:auto;">Click a day to assign an event</span></div>';
    el.innerHTML = h;
    if (!state.calVenueId || !month) return;
    get('calendar', { venue_id: state.calVenueId, month: month }).then(function(b) {
      if (!b.success) { document.getElementById('vc-cal-grid').innerHTML = '<div class="ecc-tk-empty">' + esc(errMsg(b)) + '</div>'; return; }
      var res = b.venue_result || {};
      state.calData = { venue_id: res.venue_id, month: res.month, assignments: res.assignments || [], blocks: res.blocks || [] };
      renderCalGrid('vc-cal-grid', month, true);
    });
  }

  function calNav(dir) {
    var parts = (state.calMonth || currentMonthStr()).split('-');
    var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1 + dir, 1);
    state.calMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    renderConsoleCalendar();
  }
  function calToday() {
    var d = new Date();
    state.calMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    renderConsoleCalendar();
  }
  function calVenueChanged(id) {
    state.calVenueId = id;
    renderConsoleCalendar();
  }
  function currentMonthStr() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
  }

  /* Shared month grid: items = {start_at, end_at, label, kind} */
  function renderCalGrid(containerId, month, clickable) {
    var el = document.getElementById(containerId);
    if (!el) return;
    var data = state.calData || { assignments: [], blocks: [] };
    var assignments = data.assignments || [];
    var blocks = data.blocks || [];
    var y = parseInt(month.slice(0, 4), 10);
    var m = parseInt(month.slice(5, 7), 10) - 1;
    var first = new Date(y, m, 1);
    var startDow = (first.getDay() + 6) % 7;
    var dim = new Date(y, m + 1, 0).getDate();
    var today = new Date();
    var todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

    function dayItems(d) {
      var day = y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
      var items = [];
      assignments.forEach(function(a) {
        if (day >= ymd(a.setup_start) && day <= ymd(a.teardown_end)) {
          items.push({ start: a.setup_start, end: a.teardown_end, label: a.title, kind: (day <= ymd(a.event_start)) && a.setup_start !== a.event_start ? 'SETUP' : 'EVENT' });
        }
      });
      blocks.forEach(function(b) {
        if (day >= ymd(b.start_at) && day <= ymd(b.end_at)) {
          items.push({ start: b.start_at, end: b.end_at, label: b.status === 'RESERVED' ? 'Reserved' : (b.reason || b.status.replace(/_/g, ' ')), kind: b.status });
        }
      });
      return items;
    }

    var h = '<table class="vc-cal"><thead><tr>';
    ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].forEach(function(d) { h += '<th>' + d + '</th>'; });
    h += '</tr></thead><tbody>';
    var cells = [];
    for (var i = 0; i < startDow; i++) cells.push('<td class="vc-cal-empty"></td>');
    for (var d = 1; d <= dim; d++) {
      var dayStr = y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
      var items = dayItems(d);
      var isToday = dayStr === todayStr;
      var cell = '<td class="vc-cal-day' + (isToday ? ' today' : '') + (clickable ? ' clickable' : '') + '"' + (clickable ? ' onclick="VenuesControlCenter.calDayClick(\'' + dayStr + '\')"' : '') + '>';
      cell += '<div class="vc-cal-num">' + d + '</div>';
      items.slice(0, 3).forEach(function(it) {
        cell += '<div class="vc-cal-item" style="background:' + chip(it.kind) + ';" title="' + esc(it.label) + ' ' + timePart(it.start) + '–' + timePart(it.end) + '">' +
          '<span class="vc-cal-t1">' + esc(String(it.label).slice(0, 16)) + (String(it.label).length > 16 ? '…' : '') + '</span>' +
          '<span class="vc-cal-t2">' + timePart(it.start) + '–' + timePart(it.end) + '</span></div>';
      });
      if (items.length > 3) cell += '<div class="vc-cal-more">+' + (items.length - 3) + ' more</div>';
      cell += '</td>';
      cells.push(cell);
    }
    while (cells.length % 7 !== 0) cells.push('<td class="vc-cal-empty"></td>');
    for (var r = 0; r < cells.length; r += 7) h += '<tr>' + cells.slice(r, r + 7).join('') + '</tr>';
    h += '</tbody></table>';
    el.innerHTML = h;
  }

  function calDayClick(dayStr) {
    openAssign(state.calVenueId || '', dayStr);
  }

  /* ── Workspace (manage venue) ─────────────────────────────── */
  function openWorkspace(venueId) {
    state.venueId = venueId;
    state.sub = 'overview';
    document.getElementById('vc-console').style.display = 'none';
    document.getElementById('vc-workspace').style.display = 'block';
    document.getElementById('vc-ws-body').innerHTML = '<div class="ecc-tk-empty">Loading…</div>';
    refreshDetail();
  }
  function closeWorkspace() {
    state.venueId = null;
    document.getElementById('vc-console').style.display = 'block';
    document.getElementById('vc-workspace').style.display = 'none';
    loadWorkspace();
  }
  function refreshDetail() {
    get('detail', { venue_id: state.venueId }).then(function(b) {
      if (!b.success) { toast('Venue: ' + errMsg(b)); return; }
      state.detail = b.venue_result || {};
      renderWsHead();
      renderWsNav();
      renderSub();
    });
  }
  function goSub(sub) {
    state.sub = sub;
    renderWsNav();
    renderSub();
  }
  function renderWsNav() {
    var el = document.getElementById('vc-ws-nav');
    if (!el) return;
    var h = '<div class="vc-ws-nav">';
    SUB_TABS.forEach(function(t) {
      h += '<button type="button" class="vc-ws-tab' + (t[0] === state.sub ? ' active' : '') + '" data-sub="' + t[0] + '" onclick="VenuesControlCenter.goSub(\'' + t[0] + '\')">' + t[1] + '</button>';
    });
    h += '</div>';
    el.innerHTML = h;
  }
  function renderWsHead() {
    var el = document.getElementById('vc-ws-head');
    var v = (state.detail && state.detail.venue) || {};
    if (!el) return;
    var h = '<div class="vc-ws-head">';
    h += '<div class="vc-ws-cover" style="' + coverStyle(v) + '">' + coverLetter(v) + '</div>';
    h += '<div style="flex:1;min-width:0;">';
    h += '<div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">';
    h += '<h2 style="margin:0;font-size:1.15rem;font-weight:900;">' + esc(v.name || 'Venue') + '</h2>';
    h += venPill(v.status);
    if (v.type) h += '<span class="ecc-pill purple" style="font-size:0.6rem;">' + esc(v.type) + '</span>';
    if (v.verification_status === 'VERIFIED') h += '<span class="ecc-pill green" style="font-size:0.6rem;">✓ VERIFIED</span>';
    h += '</div>';
    h += '<div style="font-size:0.72rem;color:var(--ecc-text-dim);margin:0.25rem 0 0.5rem;">' + esc([v.address, v.city, v.district, v.region, v.country].filter(Boolean).join(', ') || 'Location not set') + '</div>';
    h += '<div style="display:flex;gap:0.4rem;flex-wrap:wrap;">';
    h += '<span class="ecc-chip">' + esc(String(v.capacity || 0).replace(/\B(?=(\d{3})+(?!\d))/g, ',')) + ' capacity</span>';
    var spaces = (state.detail && state.detail.spaces) || [];
    h += '<span class="ecc-chip">' + spaces.length + ' space' + (spaces.length === 1 ? '' : 's') + '</span>';
    if (v.gps_lat != null) h += '<span class="ecc-chip">' + icon('pin') + ' ' + esc(String(v.gps_lat)) + ', ' + esc(String(v.gps_lng)) + '</span>';
    h += '</div></div>';
    h += '<div style="display:flex;flex-direction:column;gap:0.4rem;">';
    h += '<button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.7rem;padding:0.34rem 0.7rem;display:inline-flex;align-items:center;gap:0.35rem;" onclick="VenuesControlCenter.openAssign(\'' + esc(v.id) + '\')">' + icon('calendar') + ' Assign Event</button>';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.7rem;padding:0.34rem 0.7rem;display:inline-flex;align-items:center;gap:0.35rem;" onclick="VenuesControlCenter.openBlock(\'' + esc(v.id) + '\', \'\')">' + icon('clock') + ' Set Availability Block</button>';
    h += '<select class="ecc-input" style="font-size:0.68rem;padding:0.3rem;" onchange="VenuesControlCenter.quickStatus(\'' + esc(v.id) + '\', this.value)">';
    ['ACTIVE', 'TEMPORARILY_UNAVAILABLE', 'MAINTENANCE', 'SUSPENDED'].forEach(function(s) {
      h += '<option value="' + s + '"' + (v.status === s ? ' selected' : '') + '>' + s.replace(/_/g, ' ') + '</option>';
    });
    h += '</select>';
    h += '</div></div>';
    el.innerHTML = h;
  }

  function box(label, value) {
    return '<div class="vc-box"><div style="font-size:0.6rem;color:var(--ecc-text-dim);font-weight:700;letter-spacing:0.05em;">' + esc(label) + '</div><div style="font-size:0.95rem;font-weight:900;margin-top:0.2rem;overflow:hidden;text-overflow:ellipsis;">' + value + '</div></div>';
  }

  function renderSub() {
    var el = document.getElementById('vc-ws-body');
    if (!el) return;
    switch (state.sub) {
      case 'overview': renderOverview(el); break;
      case 'details': renderDetails(el); break;
      case 'spaces': renderSpaces(el); break;
      case 'availability': renderAvailability(el); break;
      case 'pricing': renderPricing(el); break;
      case 'facilities': renderFacilities(el); break;
      case 'media': renderMedia(el); break;
      case 'policies': renderPolicies(el); break;
      case 'documents': renderDocuments(el); break;
      case 'activity': renderActivity(el); break;
      default: el.innerHTML = '';
    }
  }

  function renderOverview(el) {
    var d = state.detail || {};
    var v = d.venue || {};
    var st = d.stats || { bookings: 0, available_days: 0, utilization: 0, revenue: 0, insights: [] };
    var assignments = d.assignments || [];
    var now = new Date();
    var upcoming = assignments.filter(function(a) { return a.status === 'CONFIRMED' && a.event_start >= (now.toISOString().slice(0, 10) + ' 00:00:00'); });

    var h = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.6rem;margin-bottom:1rem;">';
    h += box('UPCOMING EVENTS', '<span style="color:var(--ecc-primary);">' + upcoming.length + '</span>');
    h += box('UTILIZATION', '<span style="color:' + (st.utilization > 60 ? '#22c55e' : '#f59e0b') + ';">' + st.utilization + '%</span>');
    h += box('REVENUE', money(st.revenue, true));
    h += box('BOOKINGS', st.bookings);
    h += box('AVAILABLE DAYS', st.available_days);
    h += '</div>';

    h += '<div class="ecc-card" style="margin-bottom:1rem;padding:1rem;">';
    h += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;"><strong style="font-size:0.78rem;">Utilization (next 30 days)</strong><span style="font-size:0.7rem;color:var(--ecc-text-dim);">' + st.utilization + '%</span></div>';
    h += '<div style="height:8px;border-radius:100px;background:var(--ecc-surface-3);overflow:hidden;"><div style="width:' + Math.min(100, st.utilization) + '%;height:100%;border-radius:100px;background:linear-gradient(90deg,#6366f1,#a855f7);"></div></div>';
    h += '<div style="margin-top:0.6rem;font-size:0.72rem;color:var(--ecc-text-dim);">Based on confirmed assignments in the next 6 months.</div>';
    h += '</div>';

    h += '<div class="ecc-card" style="margin-bottom:1rem;padding:1rem;">';
    h += '<strong style="font-size:0.78rem;">Intelligence</strong>';
    h += '<div style="margin-top:0.5rem;display:grid;gap:0.45rem;">';
    (st.insights || []).forEach(function(s) {
      h += '<div style="font-size:0.72rem;color:var(--ecc-text-dim);padding:0.5rem 0.65rem;background:var(--ecc-surface-2);border-radius:8px;border-left:3px solid #a855f7;">' + icon('bulb') + ' ' + esc(s) + '</div>';
    });
    h += '</div></div>';

    if (d.reservation) {
      h += '<div class="ecc-card" style="margin-bottom:1rem;padding:1rem;border:1px solid rgba(99,102,241,0.4);">';
      h += '<strong style="font-size:0.78rem;">Happening right now</strong>';
      h += '<div style="margin-top:0.4rem;font-size:0.75rem;">◈ ' + esc(d.reservation.title) + ' · ' + fmtDt(d.reservation.event_start) + ' → ' + fmtDt(d.reservation.teardown_end) + '</div>';
      h += '</div>';
    }

    h += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:0.8rem;">';
    h += '<div class="ecc-card" style="padding:1rem;"><strong style="font-size:0.78rem;">Quick actions</strong><div style="display:grid;gap:0.45rem;margin-top:0.6rem;">';
    h += '<button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.7rem;display:inline-flex;align-items:center;gap:0.35rem;" onclick="VenuesControlCenter.openAssign(\'' + esc(v.id) + '\')">' + icon('calendar') + ' Assign an event</button>';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.7rem;display:inline-flex;align-items:center;gap:0.35rem;" onclick="VenuesControlCenter.openBlock(\'' + esc(v.id) + '\', \'\')">' + icon('clock') + ' Block a date</button>';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.7rem;" onclick="VenuesControlCenter.goSub(\'availability\')">▤ Open availability calendar</button>';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.7rem;display:inline-flex;align-items:center;gap:0.35rem;" onclick="VenuesControlCenter.goSub(\'details\')">' + icon('pencil') + ' Edit venue details</button>';
    h += '</div></div>';
    h += '<div class="ecc-card" style="padding:1rem;"><strong style="font-size:0.78rem;">Guest rating</strong>';
    h += '<div style="margin-top:0.5rem;font-size:1.05rem;font-weight:900;color:#fbbf24;">★ 4.8</div>';
    h += '<div style="font-size:0.7rem;color:var(--ecc-text-dim);margin-top:0.15rem;">126 verified venue reviews</div>';
    h += '<div style="font-size:0.66rem;color:var(--ecc-text-dim);margin-top:0.5rem;">Full review management lives in the <strong>Reviews</strong> module.</div>';
    h += '</div></div>';

    el.innerHTML = h;
  }
  function renderDetails(el) {
    var v = (state.detail && state.detail.venue) || {};
    var f = function(n) { return esc(v[n] == null ? '' : v[n]); };
    var h = '<div class="ecc-card" style="padding:1rem;max-width:760px;">';
    h += '<strong style="font-size:0.8rem;">Venue details</strong>';
    h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;margin-top:0.8rem;font-size:0.75rem;">';
    h += '<label style="grid-column:1 / -1;font-weight:700;">Name<input id="vd-name" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('name') + '"></label>';
    h += '<label style="font-weight:700;">Type<select id="vd-type" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;">' + VENUE_TYPES.map(function(t) { return '<option' + (v.type === t ? ' selected' : '') + '>' + t + '</option>'; }).join('') + '</select></label>';
    h += '<label style="font-weight:700;">Capacity<input type="number" id="vd-capacity" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('capacity') + '"></label>';
    h += '<label style="grid-column:1 / -1;font-weight:700;">Description<textarea id="vd-desc" class="ecc-input" rows="3" style="display:block;width:100%;margin-top:0.2rem;">' + f('description') + '</textarea></label>';
    h += '<label style="font-weight:700;">Address<input id="vd-address" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('address') + '"></label>';
    h += '<label style="font-weight:700;">City<input id="vd-city" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('city') + '"></label>';
    h += '<label style="font-weight:700;">District<input id="vd-district" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('district') + '"></label>';
    h += '<label style="font-weight:700;">Region<input id="vd-region" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('region') + '"></label>';
    h += '<label style="font-weight:700;">Country<input id="vd-country" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('country') + '"></label>';
    h += '<label style="font-weight:700;">Latitude<input id="vd-lat" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('gps_lat') + '"></label>';
    h += '<label style="font-weight:700;">Longitude<input id="vd-lng" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('gps_lng') + '"></label>';
    h += '<label style="font-weight:700;">Contact phone<input id="vd-phone" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('contact_phone') + '"></label>';
    h += '<label style="font-weight:700;">Contact email<input id="vd-email" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('contact_email') + '"></label>';
    h += '<label style="grid-column:1 / -1;font-weight:700;">Cover image URL<input id="vd-cover" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + f('cover_image') + '"></label>';
    h += '</div>';
    h += '<div style="margin-top:0.9rem;"><button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.74rem;" onclick="VenuesControlCenter.saveDetails()">Save Changes</button></div>';
    h += '</div>';
    h += '<div class="ecc-card" style="padding:1rem;margin-top:0.8rem;max-width:760px;border-color:rgba(244,63,94,0.3);">';
    h += '<strong style="font-size:0.78rem;color:#fb7185;">Danger zone</strong>';
    h += '<div style="font-size:0.68rem;color:var(--ecc-text-dim);margin:0.3rem 0 0.6rem;">Removing a venue hides it from assignment. Venues with confirmed assignments cannot be removed.</div>';
    h += '<button type="button" class="ecc-btn" style="font-size:0.72rem;background:#7f1d1d;border-color:#b91c1c;color:#fecaca;" onclick="VenuesControlCenter.removeVenue(\'' + esc(v.id) + '\')">Delete this venue</button>';
    h += '</div>';
    el.innerHTML = h;
  }

  function saveDetails() {
    var payload = {
      action: 'update_venue', venue_id: state.venueId,
      name: val('vd-name'), type: val('vd-type'), capacity: val('vd-capacity'),
      description: val('vd-desc'), address: val('vd-address'), city: val('vd-city'),
      district: val('vd-district'), region: val('vd-region'), country: val('vd-country'),
      gps_lat: val('vd-lat'), gps_lng: val('vd-lng'),
      contact_phone: val('vd-phone'), contact_email: val('vd-email'), cover_image: val('vd-cover')
    };
    post(payload).then(function(b) {
      if (!b.success) { toast('Details: ' + errMsg(b)); return; }
      toast('Venue details saved');
      refreshDetail();
      loadWorkspace();
    });
  }

  function renderSpaces(el) {
    var d = state.detail || {};
    var spaces = d.spaces || [];
    var h = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;"><strong style="font-size:0.8rem;">Spaces (' + spaces.length + ')</strong></div>';
    h += '<div class="ecc-card" style="overflow-x:auto;margin-bottom:1rem;"><table class="vc-table">';
    h += '<thead><tr><th>Name</th><th>Seating</th><th>Capacity</th><th>Dimensions</th><th>Status</th><th></th></tr></thead><tbody>';
    if (!spaces.length) h += '<tr><td colspan="6"><div class="ecc-tk-empty">No spaces yet — add the first one below.</div></td></tr>';
    spaces.forEach(function(s) {
      h += '<tr>';
      h += '<td style="font-size:0.74rem;"><strong>' + esc(s.name) + '</strong>' + (s.description ? '<div style="color:var(--ecc-text-dim);font-size:0.66rem;">' + esc(s.description) + '</div>' : '') + '</td>';
      h += '<td style="font-size:0.72rem;">' + esc(s.type || '—') + '</td>';
      h += '<td style="font-size:0.72rem;">' + esc(String(s.capacity || 0)) + '</td>';
      h += '<td style="font-size:0.72rem;">' + esc(s.dimensions || '—') + '</td>';
      var spCls = s.status === 'MAINTENANCE' ? 'rose' : (s.status === 'BLOCKED' ? 'amber' : 'green');
      h += '<td><span class="ecc-pill ' + spCls + '" style="font-size:0.6rem;">' + esc(s.status || 'ACTIVE') + '</span></td>';
      h += '<td style="white-space:nowrap;"><button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.64rem;padding:0.25rem 0.5rem;" onclick="VenuesControlCenter.editSpace(\'' + esc(s.id) + '\')">Edit</button> <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.64rem;padding:0.25rem 0.5rem;" onclick="VenuesControlCenter.removeSpace(\'' + esc(s.id) + '\')">✕</button></td>';
      h += '</tr>';
    });
    h += '</tbody></table></div>';
    h += '<div class="ecc-card" style="padding:1rem;max-width:760px;">';
    h += '<strong style="font-size:0.78rem;">Add a space</strong>';
    h += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.5rem;margin-top:0.6rem;font-size:0.72rem;">';
    h += '<label style="font-weight:700;">Name<input id="vs-name" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" placeholder="Hall A"></label>';
    h += '<label style="font-weight:700;">Seating layout<select id="vs-type" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;"><option value="">—</option>' + SPACE_TYPES.map(function(t) { return '<option>' + t + '</option>'; }).join('') + '</select></label>';
    h += '<label style="font-weight:700;">Capacity<input type="number" id="vs-capacity" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;"></label>';
    h += '<label style="grid-column:1 / -1;font-weight:700;">Dimensions<input id="vs-dims" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" placeholder="e.g. 40x25m"></label>';
    h += '<label style="grid-column:1 / -1;font-weight:700;">Description<input id="vs-desc" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;"></label>';
    h += '</div>';
    h += '<button type="button" class="ecc-btn ecc-btn-primary" style="margin-top:0.7rem;font-size:0.72rem;" onclick="VenuesControlCenter.addSpaceFn()">+ Add Space</button>';
    h += '</div>';
    el.innerHTML = h;
  }

  function addSpaceFn() {
    post({
      action: 'add_space', venue_id: state.venueId,
      space: { name: val('vs-name'), type: val('vs-type'), capacity: val('vs-capacity'), dimensions: val('vs-dims'), description: val('vs-desc') }
    }).then(function(b) {
      if (!b.success) { toast('Space: ' + errMsg(b)); return; }
      toast('Space added');
      applyDetailResult(b);
    });
  }
  function editSpace(id) {
    var s = null;
    ((state.detail && state.detail.spaces) || []).forEach(function(x) { if (x.id === id) s = x; });
    if (!s) return;
    var name = window.prompt('Space name', s.name);
    var cap = window.prompt('Capacity', s.capacity || '0');
    var type = window.prompt('Seating layout (' + SPACE_TYPES.join(', ') + ')', s.type || '');
    if (name === null) return;
    post({
      action: 'update_space', space_id: id,
      space: { name: name, type: type, capacity: cap, dimensions: s.dimensions, description: s.description },
      status: s.status
    }).then(function(b) {
      if (!b.success) { toast('Space: ' + errMsg(b)); return; }
      toast('Space updated');
      applyDetailResult(b);
    });
  }
  function removeSpace(id) {
    if (!window.confirm('Remove this space?')) return;
    post({ action: 'delete_space', space_id: id }).then(function(b) {
      if (!b.success) { toast('Space: ' + errMsg(b)); return; }
      toast('Space removed');
      applyDetailResult(b);
    });
  }

  function renderAvailability(el) {
    var d = state.detail || {};
    var v = d.venue || {};
    var assignments = (d.assignments || []).filter(function(a) { return a.status === 'CONFIRMED'; });
    var h = '<div style="display:flex;justify-content:space-between;align-items:center;gap:0.6rem;flex-wrap:wrap;margin-bottom:0.7rem;">';
    h += '<strong style="font-size:0.8rem;">Availability calendar</strong>';
    h += '<div style="display:flex;gap:0.4rem;align-items:center;">';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.3rem 0.6rem;" onclick="VenuesControlCenter.avNav(-1)">←</button>';
    h += '<strong style="font-size:0.78rem;min-width:90px;text-align:center;">' + esc(state.calMonth || currentMonthStr()) + '</strong>';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.3rem 0.6rem;" onclick="VenuesControlCenter.avNav(1)">→</button>';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.3rem 0.6rem;" onclick="VenuesControlCenter.openBlock(\'' + esc(v.id) + '\', \'\')">+ Block Date</button>';
    h += '</div></div>';
    h += '<div id="vc-av-grid">Loading…</div>';
    h += '<div style="display:flex;gap:0.9rem;flex-wrap:wrap;margin:0.7rem 0 1rem;font-size:0.64rem;color:var(--ecc-text-dim);">';
    h += '<span><span class="vc-legend" style="background:#4f46e5;"></span>Event</span>';
    h += '<span><span class="vc-legend" style="background:#7c3aed;"></span>Setup</span>';
    h += '<span><span class="vc-legend" style="background:#b45309;"></span>Reserved</span>';
    h += '<span><span class="vc-legend" style="background:#475569;"></span>Blocked</span>';
    h += '<span><span class="vc-legend" style="background:#be123c;"></span>Maintenance</span>';
    h += '<span style="margin-left:auto;">Event blocks include setup & teardown windows</span></div>';
    h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">';
    h += '<div class="ecc-card" style="padding:1rem;"><strong style="font-size:0.78rem;">Upcoming bookings</strong><div style="margin-top:0.5rem;display:grid;gap:0.45rem;">';
    if (!assignments.length) h += '<div class="ecc-tk-empty">No confirmed bookings yet.</div>';
    assignments.forEach(function(a) {
      h += '<div style="font-size:0.7rem;background:var(--ecc-surface-2);border-radius:8px;padding:0.5rem 0.65rem;display:flex;gap:0.5rem;align-items:center;justify-content:space-between;">';
      h += '<div style="min-width:0;"><strong>' + esc(a.title) + '</strong><div style="color:var(--ecc-text-dim);font-size:0.64rem;">' + fmtDt(a.event_start) + ' → ' + fmtDt(a.teardown_end) + (a.space_id ? ' · space' : ' · whole venue') + '</div></div>';
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.62rem;padding:0.22rem 0.5rem;flex-shrink:0;" onclick="VenuesControlCenter.unassign(\'' + esc(a.assignment_id) + '\')">Unassign</button>';
      h += '</div>';
    });
    h += '</div></div>';
    h += '<div class="ecc-card" style="padding:1rem;"><strong style="font-size:0.78rem;">Manual blocks</strong><div style="margin-top:0.5rem;display:grid;gap:0.45rem;">';
    var blocks = (d.blocks || []).filter(function(x) { return ['RESERVED', 'BLOCKED', 'MAINTENANCE'].indexOf(x.status) !== -1; });
    if (!blocks.length) h += '<div class="ecc-tk-empty">No manual blocks.</div>';
    blocks.forEach(function(x) {
      h += '<div style="font-size:0.7rem;background:var(--ecc-surface-2);border-radius:8px;padding:0.5rem 0.65rem;display:flex;gap:0.5rem;align-items:center;justify-content:space-between;">';
      h += '<div style="min-width:0;"><strong style="color:' + chip(x.status) + ';">' + esc(x.status) + '</strong><div style="color:var(--ecc-text-dim);font-size:0.64rem;">' + fmtDt(x.start_at) + ' → ' + fmtDt(x.end_at) + (x.reason ? ' · ' + esc(x.reason) : '') + '</div></div>';
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.62rem;padding:0.22rem 0.5rem;flex-shrink:0;" onclick="VenuesControlCenter.removeBlock(\'' + esc(x.id) + '\')">✕</button>';
      h += '</div>';
    });
    h += '</div></div></div>';
    el.innerHTML = h;
    loadAvailabilityMonth();
  }

  function avNav(dir) {
    var parts = (state.calMonth || currentMonthStr()).split('-');
    var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1 + dir, 1);
    state.calMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    var el = document.getElementById('vc-ws-body');
    if (el && state.sub === 'availability') renderAvailability(el);
  }
  function loadAvailabilityMonth() {
    get('calendar', { venue_id: state.venueId, month: state.calMonth || currentMonthStr() }).then(function(b) {
      if (!b.success) { document.getElementById('vc-av-grid').innerHTML = '<div class="ecc-tk-empty">' + esc(errMsg(b)) + '</div>'; return; }
      var res = b.venue_result || {};
      state.calData = { venue_id: res.venue_id, month: res.month, assignments: res.assignments || [], blocks: res.blocks || [] };
      state.calMonth = res.month;
      renderCalGrid('vc-av-grid', res.month, false);
    });
  }
  function unassign(assignmentId) {
    if (!window.confirm('Cancel this booking and free the venue for that period?')) return;
    post({ action: 'delete_assignment', assignment_id: assignmentId }).then(function(b) {
      if (!b.success) { toast('Booking: ' + errMsg(b)); return; }
      toast('Booking cancelled — venue is free again');
      applyDetailResult(b);
    });
  }
  function removeBlock(blockId) {
    if (!window.confirm('Remove this manual block?')) return;
    post({ action: 'remove_availability', block_id: blockId }).then(function(b) {
      if (!b.success) { toast('Block: ' + errMsg(b)); return; }
      toast('Block removed');
      applyDetailResult(b);
    });
  }

  function applyDetailResult(b) {
    if (b.venue_result && b.venue_result.detail) {
      state.detail = b.venue_result.detail;
      renderWsHead();
      renderWsNav();
      renderSub();
    } else {
      refreshDetail();
    }
    loadWorkspace();
  }

  function renderPricing(el) {
    var d = state.detail || {};
    if (state.pricingDraft === null) state.pricingDraft = (d.pricing || []).map(function(p) { return { name: p.name, price: p.price, currency: p.currency || 'MWK', description: p.description || '' }; });
    var rows = state.pricingDraft;
    var h = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;"><strong style="font-size:0.8rem;">Pricing packages (' + rows.length + ')</strong><button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.66rem;padding:0.3rem 0.6rem;" onclick="VenuesControlCenter.pricePresets()">Standard packages</button></div>';
    h += '<div class="ecc-card" style="padding:1rem;max-width:760px;margin-bottom:1rem;">';
    h += '<div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:0.5rem;font-size:0.7rem;color:var(--ecc-text-dim);font-weight:700;padding-bottom:0.3rem;"><div>Name</div><div>Price (MWK)</div><div>Currency</div><div></div></div>';
    if (!rows.length) h += '<div class="ecc-tk-empty">No pricing packages yet.</div>';
    for (var i = 0; i < rows.length; i++) {
      h += '<div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:0.5rem;margin-bottom:0.4rem;">' +
        '<input class="ecc-input vc-pr-name" style="font-size:0.72rem;" value="' + esc(rows[i].name) + '">' +
        '<input class="ecc-input vc-pr-price" style="font-size:0.72rem;" type="number" value="' + esc(rows[i].price) + '">' +
        '<input class="ecc-input vc-pr-cur" style="font-size:0.72rem;" value="' + esc(rows[i].currency) + '">' +
        '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.62rem;padding:0.25rem 0.5rem;" onclick="VenuesControlCenter.priceRemove(' + i + ')">✕</button></div>';
    }
    h += '<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:0.5rem;margin-top:0.7rem;">';
    h += '<input id="vp-name" class="ecc-input" style="font-size:0.72rem;" placeholder="Package name (e.g. Custom Night)">';
    h += '<input id="vp-price" class="ecc-input" style="font-size:0.72rem;" placeholder="Price" type="number">';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;" onclick="VenuesControlCenter.priceAdd()">+ Add</button>';
    h += '</div></div>';
    h += '<button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.74rem;" onclick="VenuesControlCenter.priceSaveAll()">Save Pricing ✓</button>';
    el.innerHTML = h;
  }
  function pricePresets() {
    state.pricingDraft = PRICE_PRESETS.map(function(p) { return { name: p.name, price: p.price, currency: 'MWK', description: p.desc }; });
    renderPricing(document.getElementById('vc-ws-body'));
  }
  function priceAdd() {
    state.pricingDraft.push({ name: val('vp-name'), price: val('vp-price'), currency: 'MWK', description: '' });
    renderPricing(document.getElementById('vc-ws-body'));
  }
  function priceRemove(i) {
    state.pricingDraft.splice(i, 1);
    renderPricing(document.getElementById('vc-ws-body'));
  }
  function collectPricingDom() {
    var names = document.querySelectorAll('.vc-pr-name');
    var prices = document.querySelectorAll('.vc-pr-price');
    var curs = document.querySelectorAll('.vc-pr-cur');
    var out = [];
    for (var i = 0; i < names.length; i++) {
      var name = String(names[i].value || '').trim();
      if (name) out.push({ name: name, price: prices[i].value, currency: (curs[i].value || 'MWK').trim(), description: '' });
    }
    return out;
  }
  function priceSaveAll() {
    post({ action: 'save_pricing', venue_id: state.venueId, pricing: collectPricingDom() }).then(function(b) {
      if (!b.success) { toast('Pricing: ' + errMsg(b)); return; }
      toast('Pricing saved');
      state.pricingDraft = null;
      applyDetailResult(b);
    });
  }

  function renderFacilities(el) {
    var d = state.detail || {};
    var fac = d.facilities || [];
    var sel = {};
    fac.forEach(function(f) { sel[f.name] = f.available ? 1 : 0; });
    var h = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;"><strong style="font-size:0.8rem;">Facilities & services</strong></div>';
    h += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:0.8rem;" id="vc-fac-groups">';
    Object.keys(FACILITY_PRESETS).forEach(function(g) {
      h += '<div class="ecc-card" style="padding:1rem;">';
      h += '<strong style="font-size:0.72rem;color:var(--ecc-primary);letter-spacing:0.05em;">' + g + '</strong>';
      FACILITY_PRESETS[g].forEach(function(p) {
        var checked = sel[p[0]] === 1;
        h += '<label style="display:flex;gap:0.5rem;align-items:flex-start;margin-top:0.5rem;font-size:0.7rem;cursor:pointer;"><input type="checkbox" class="vc-fac-cb" data-group="' + g + '" data-name="' + esc(p[0]) + '" data-desc="' + esc(p[1]) + '"' + (checked ? ' checked' : '') + ' style="margin-top:0.15rem;accent-color:#6366f1;">' +
          '<span><span style="font-weight:700;">' + esc(p[0]) + '</span><span style="display:block;color:var(--ecc-text-dim);font-size:0.64rem;">' + esc(p[1]) + '</span></span></label>';
      });
      h += '</div>';
    });
    h += '</div>';
    h += '<div class="ecc-card" style="padding:1rem;margin-top:0.8rem;max-width:640px;">';
    h += '<strong style="font-size:0.72rem;">Custom facility</strong>';
    h += '<div style="display:grid;grid-template-columns:1fr 2fr 1fr;gap:0.5rem;margin-top:0.5rem;">';
    h += '<select id="vf-group" class="ecc-input" style="font-size:0.7rem;">' + Object.keys(FACILITY_PRESETS).map(function(g) { return '<option>' + g + '</option>'; }).join('') + '</select>';
    h += '<input id="vf-name" class="ecc-input" style="font-size:0.7rem;" placeholder="Facility name">';
    h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;" onclick="VenuesControlCenter.facAdd()">+ Add</button>';
    h += '</div></div>';
    h += '<button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.74rem;margin-top:0.8rem;" onclick="VenuesControlCenter.facSave()">Save Facilities ✓</button>';
    el.innerHTML = h;
  }
  function facAdd() {
    var name = String(val('vf-name') || '').trim();
    if (!name) { toast('Name the facility first.'); return; }
    var group = val('vf-group');
    var box = document.createElement('label');
    box.style.cssText = 'display:flex;gap:0.5rem;align-items:flex-start;margin-top:0.5rem;font-size:0.7rem;cursor:pointer;';
    box.innerHTML = '<input type="checkbox" class="vc-fac-cb" data-group="' + esc(group) + '" data-name="' + esc(name) + '" data-desc="" checked style="margin-top:0.15rem;accent-color:#6366f1;"><span><span style="font-weight:700;">' + esc(name) + '</span></span>';
    var cards = document.querySelectorAll('#vc-fac-groups .ecc-card');
    var idx = Object.keys(FACILITY_PRESETS).indexOf(group);
    var target = cards[idx >= 0 ? idx : 0];
    if (target) target.appendChild(box);
    document.getElementById('vf-name').value = '';
  }
  function facSave() {
    var out = [];
    document.querySelectorAll('.vc-fac-cb').forEach(function(cb) {
      out.push({ group: cb.getAttribute('data-group'), name: cb.getAttribute('data-name'), description: cb.getAttribute('data-desc') || '', available: cb.checked ? 1 : 0 });
    });
    post({ action: 'save_facilities', venue_id: state.venueId, facilities: out }).then(function(b) {
      if (!b.success) { toast('Facilities: ' + errMsg(b)); return; }
      toast('Facilities saved');
      applyDetailResult(b);
    });
  }

  function renderMedia(el) {
    var d = state.detail || {};
    if (state.mediaDraft === null) state.mediaDraft = (d.media || []).map(function(m) { return { url: m.url, media_type: m.media_type || 'GALLERY', is_cover: m.is_cover ? 1 : 0 }; });
    var rows = state.mediaDraft;
    var h = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;"><strong style="font-size:0.8rem;">Media gallery (' + rows.length + ')</strong></div>';
    h += '<div class="vc-grid" style="margin-bottom:1rem;">';
    if (!rows.length) h += '<div class="ecc-card" style="grid-column:1 / -1;padding:2rem;"><div class="ecc-tk-empty">No media yet — add a cover or gallery images below.</div></div>';
    for (var i = 0; i < rows.length; i++) {
      var m = rows[i];
      h += '<div class="vc-card">';
      h += '<div class="vc-card-img" style="background-image:url(' + esc(m.url) + ');background-size:cover;background-position:center;">';
      h += '<div style="position:absolute;top:0.5rem;left:0.5rem;display:flex;gap:0.3rem;">';
      if (m.is_cover) h += '<span class="ecc-pill green" style="font-size:0.55rem;">★ COVER</span>';
      h += '<span class="ecc-pill purple" style="font-size:0.55rem;">' + esc(m.media_type || 'GALLERY') + '</span>';
      h += '</div></div>';
      h += '<div class="vc-card-body">';
      h += '<div style="display:flex;gap:0.3rem;flex-wrap:wrap;">';
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.6rem;padding:0.22rem 0.45rem;" onclick="VenuesControlCenter.mediaMove(' + i + ', -1)">↑</button>';
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.6rem;padding:0.22rem 0.45rem;" onclick="VenuesControlCenter.mediaMove(' + i + ', 1)">↓</button>';
      h += (!m.is_cover ? '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.6rem;padding:0.22rem 0.45rem;" onclick="VenuesControlCenter.mediaCover(' + i + ')">Set cover</button>' : '');
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.6rem;padding:0.22rem 0.45rem;" onclick="VenuesControlCenter.mediaRemove(' + i + ')">✕</button>';
      h += '</div></div></div>';
    }
    h += '</div>';
    h += '<div class="ecc-card" style="padding:1rem;max-width:700px;">';
    h += '<strong style="font-size:0.76rem;">Add media</strong>';
    h += '<div style="display:flex;gap:0.5rem;margin-top:0.6rem;">';
    h += '<select id="vm-type" class="ecc-input" style="font-size:0.7rem;width:140px;"><option value="GALLERY">Gallery</option><option value="COVER">Cover</option><option value="FLOOR_PLAN">Floor plan / Doc</option></select>';
    h += '<input id="vm-url" class="ecc-input" style="font-size:0.7rem;flex:1;" placeholder="https://… image URL">';
    h += '<button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.68rem;" onclick="VenuesControlCenter.mediaAdd()">+ Add</button>';
    h += '</div></div>';
    h += '<button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.74rem;margin-top:0.8rem;" onclick="VenuesControlCenter.mediaSave()">Save Gallery ✓</button>';
    el.innerHTML = h;
  }
  function mediaMove(i, dir) {
    var j = i + dir;
    if (j < 0 || j >= state.mediaDraft.length) return;
    var tmp = state.mediaDraft[i]; state.mediaDraft[i] = state.mediaDraft[j]; state.mediaDraft[j] = tmp;
    renderMedia(document.getElementById('vc-ws-body'));
  }
  function mediaCover(i) {
    state.mediaDraft.forEach(function(m, k) { m.is_cover = (k === i) ? 1 : 0; });
    renderMedia(document.getElementById('vc-ws-body'));
  }
  function mediaRemove(i) {
    state.mediaDraft.splice(i, 1);
    renderMedia(document.getElementById('vc-ws-body'));
  }
  function mediaAdd() {
    var url = String(val('vm-url') || '').trim();
    if (!url) { toast('Paste a media URL first.'); return; }
    state.mediaDraft.push({ url: url, media_type: val('vm-type'), is_cover: 0 });
    renderMedia(document.getElementById('vc-ws-body'));
  }
  function mediaSave() {
    var items = [];
    state.mediaDraft.forEach(function(m, i) { if (String(m.url || '').trim()) items.push({ url: m.url, media_type: m.media_type || 'GALLERY', is_cover: m.is_cover ? 1 : 0, sort_order: i }); });
    post({ action: 'save_media', venue_id: state.venueId, media: items }).then(function(b) {
      if (!b.success) { toast('Media: ' + errMsg(b)); return; }
      toast('Media gallery saved');
      state.mediaDraft = null;
      applyDetailResult(b);
    });
  }

  function renderPolicies(el) {
    var p = (state.detail && state.detail.policies) || {};
    var restr = p.restrictions || '[]';
    if (typeof restr === 'string') { try { restr = JSON.parse(restr); } catch (e) { restr = []; } }
    var sel = {};
    restr.forEach(function(r) { sel[r] = true; });
    var h = '<div class="ecc-card" style="padding:1rem;max-width:760px;">';
    h += '<strong style="font-size:0.8rem;">Venue policies</strong>';
    h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;margin-top:0.8rem;font-size:0.75rem;">';
    h += '<label style="grid-column:1 / -1;font-weight:700;">Cancellation policy<textarea id="vp2-cancel" class="ecc-input" rows="3" style="display:block;width:100%;margin-top:0.2rem;" placeholder="e.g. Free cancellation 14 days before…">' + esc(p.cancellation_policy || '') + '</textarea></label>';
    h += '<label style="font-weight:700;">Advance booking (days)<input type="number" id="vp2-advance" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + esc(p.advance_booking_days || '') + '"></label>';
    h += '<label style="font-weight:700;">Min duration (hours)<input type="number" id="vp2-min" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + esc(p.min_duration_hours || '') + '"></label>';
    h += '<label style="font-weight:700;">Max duration (hours)<input type="number" id="vp2-max" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + esc(p.max_duration_hours || '') + '"></label>';
    h += '<label style="font-weight:700;">Check-in time<input type="time" id="vp2-checkin" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + esc(p.check_in_time || '') + '"></label>';
    h += '<label style="font-weight:700;">Opening time<input type="time" id="vp2-open" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + esc(p.opening_time || '') + '"></label>';
    h += '<label style="font-weight:700;">Closing time<input type="time" id="vp2-close" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + esc(p.closing_time || '') + '"></label>';
    h += '<label style="font-weight:700;">Setup period (minutes)<input type="number" id="vp2-setup" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + esc(p.setup_period_minutes != null ? p.setup_period_minutes : 120) + '"></label>';
    h += '<label style="font-weight:700;">Teardown period (minutes)<input type="number" id="vp2-teardown" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" value="' + esc(p.teardown_period_minutes != null ? p.teardown_period_minutes : 60) + '"></label>';
    h += '</div>';
    h += '<div style="margin-top:0.9rem;"><strong style="font-size:0.72rem;display:block;margin-bottom:0.4rem;">Restrictions</strong><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:0.3rem;">';
    RESTRICTION_PRESETS.forEach(function(r) {
      h += '<label style="font-size:0.7rem;display:flex;gap:0.4rem;align-items:center;cursor:pointer;"><input type="checkbox" class="vp2-restr" value="' + esc(r) + '"' + (sel[r] ? ' checked' : '') + ' style="accent-color:#6366f1;">' + esc(r) + '</label>';
    });
    h += '</div></div>';
    h += '<button type="button" class="ecc-btn ecc-btn-primary" style="margin-top:0.9rem;font-size:0.74rem;" onclick="VenuesControlCenter.savePoliciesFn()">Save Policies ✓</button>';
    h += '</div>';
    el.innerHTML = h;
  }
  function savePoliciesFn() {
    var restr = [];
    document.querySelectorAll('.vp2-restr').forEach(function(cb) { if (cb.checked) restr.push(cb.value); });
    post({
      action: 'save_policies', venue_id: state.venueId,
      policies: {
        cancellation_policy: val('vp2-cancel'), advance_booking_days: val('vp2-advance'),
        min_duration_hours: val('vp2-min'), max_duration_hours: val('vp2-max'),
        check_in_time: val('vp2-checkin'), opening_time: val('vp2-open'), closing_time: val('vp2-close'),
        setup_period_minutes: val('vp2-setup'), teardown_period_minutes: val('vp2-teardown'),
        restrictions: restr
      }
    }).then(function(b) {
      if (!b.success) { toast('Policies: ' + errMsg(b)); return; }
      toast('Policies saved');
      applyDetailResult(b);
    });
  }

  function renderDocuments(el) {
    var d = state.detail || {};
    var docs = (d.media || []).filter(function(m) { return m.media_type === 'FLOOR_PLAN'; });
    var h = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;"><strong style="font-size:0.8rem;">Venue documents</strong></div>';
    h += '<div class="ecc-card" style="padding:1rem;max-width:720px;">';
    h += '<div style="font-size:0.7rem;color:var(--ecc-text-dim);margin-bottom:0.7rem;">Floor plans, seating diagrams and venue packs. Add a file as a <strong>Floor plan / Doc</strong> item in the Media tab — it appears here for the venue team.</div>';
    if (!docs.length) h += '<div class="ecc-tk-empty">No documents yet. Add floor plans in the Media tab.</div>';
    docs.forEach(function(m) {
      h += '<div style="display:flex;gap:0.6rem;align-items:center;padding:0.5rem 0;border-bottom:1px dashed var(--ecc-border);">';
      h += '<span style="width:34px;height:34px;border-radius:8px;background:var(--ecc-surface-3);display:flex;align-items:center;justify-content:center;color:var(--ecc-text-dim);font-size:0.9rem;flex-shrink:0;">' + icon('doc') + '</span>';
      h += '<div style="flex:1;min-width:0;font-size:0.72rem;"><strong>Floor plan</strong><div style="color:var(--ecc-text-dim);font-size:0.64rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(m.url) + '</div></div>';
      h += '<a href="' + esc(m.url) + '" target="_blank" rel="noopener" class="ecc-btn ecc-btn-secondary" style="font-size:0.64rem;padding:0.25rem 0.55rem;text-decoration:none;">Open ↗</a>';
      h += '</div>';
    });
    h += '</div>';
    el.innerHTML = h;
  }

  function renderActivity(el) {
    var d = state.detail || {};
    var act = d.activity || [];
    var h = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;"><strong style="font-size:0.8rem;">Activity timeline</strong></div>';
    h += '<div class="ecc-card" style="padding:1rem;max-width:720px;">';
    if (!act.length) h += '<div class="ecc-tk-empty">No activity recorded yet.</div>';
    act.forEach(function(a) {
      var det = '';
      try { var j = JSON.parse(a.details || '{}'); if (j && j.event) det = j.event; else if (j && j.space) det = j.space; } catch (e) {}
      h += '<div style="display:flex;gap:0.6rem;padding:0.45rem 0;border-bottom:1px dashed var(--ecc-border);">';
      h += '<div style="width:9px;height:9px;border-radius:50%;background:var(--ecc-primary);margin-top:0.3rem;flex-shrink:0;"></div>';
      h += '<div style="flex:1;font-size:0.72rem;"><strong>' + esc(a.action) + '</strong>' + (det ? ' <span style="color:var(--ecc-text-dim);">· ' + esc(det) + '</span>' : '') +
        '<div style="font-size:0.62rem;color:var(--ecc-text-dim);">by ' + esc(a.actor_name || a.actor_id || 'system') + '</div></div>';
      h += '<div style="font-size:0.62rem;color:var(--ecc-text-dim);white-space:nowrap;">' + fmtDt(a.created_at) + '</div>';
      h += '</div>';
    });
    h += '</div>';
    el.innerHTML = h;
  }
/* ── Assign event modal ──────────────────────────────────── */

  function loadEvents() {
    if (state.eventsLoaded) return Promise.resolve();
    return fetch(base + 'api/tie/vendor/events/events.php', { credentials: 'same-origin' })
      .then(function(r) { return r.json().catch(function() { return {}; }); })
      .then(function(b) {
        state.events = (b && b.portfolio && b.portfolio.events) || [];
        state.eventsLoaded = true;
      });
  }

  function openAssign(venueId, dayStr) {
    state.assignVenueId = venueId;
    var v = (state.venueId === venueId && state.detail) ? (state.detail.venue || {}) : {};
    var vn = document.getElementById('ve-venue-name');
    if (vn) vn.textContent = v.name || 'Venue';
    var sp = document.getElementById('ve-space');
    sp.innerHTML = '<option value="">Whole venue (all spaces)</option>';
    var spaces = (state.venueId === venueId && state.detail) ? (state.detail.spaces || []) : [];
    spaces.forEach(function(s) {
      var opt = document.createElement('option');
      opt.value = s.id;
      opt.textContent = s.name + ' (' + (String(s.capacity || '—')) + ')';
      sp.appendChild(opt);
    });
    sp.value = '';
    if (dayStr && /^\d{4}-\d{2}-\d{2}$/.test(dayStr)) {
      document.getElementById('ve-setup').value = dayStr + 'T08:00';
      document.getElementById('ve-start').value = dayStr + 'T09:00';
      document.getElementById('ve-end').value = dayStr + 'T17:00';
      document.getElementById('ve-teardown').value = dayStr + 'T18:00';
    } else {
      var now = new Date();
      var t = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 9, 0);
      var t2 = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 17, 0);
      function iso(x) { return x.getFullYear() + '-' + String(x.getMonth() + 1).padStart(2, '0') + '-' + String(x.getDate()).padStart(2, '0') + 'T' + String(x.getHours()).padStart(2, '0') + ':' + String(x.getMinutes()).padStart(2, '0'); }
      document.getElementById('ve-setup').value = iso(new Date(t.getTime() - 60 * 60 * 1000));
      document.getElementById('ve-start').value = iso(t);
      document.getElementById('ve-end').value = iso(t2);
      document.getElementById('ve-teardown').value = iso(new Date(t2.getTime() + 60 * 60 * 1000));
    }
    var checkBox = document.getElementById('ve-check');
    if (checkBox) checkBox.innerHTML = '';
    loadEvents().then(function() {
      var sel = document.getElementById('ve-event');
      sel.innerHTML = '<option value="">Select event…</option>';
      state.events.forEach(function(e) {
        var opt = document.createElement('option');
        opt.value = e.id;
        opt.textContent = e.title;
        sel.appendChild(opt);
      });
      sel.value = '';
    });
    openEccModal('modal-assign-event');
  }

  function assignCheck(doAssign) {
    var venueId = state.assignVenueId || state.calVenueId || '';
    var eventId = val('ve-event');
    var spaceId = val('ve-space');
    var setup = val('ve-setup');
    var start = val('ve-start');
    var end = val('ve-end');
    var teardown = val('ve-teardown');
    var checkBox = document.getElementById('ve-check');
    if (!checkBox || !venueId) return;
    if (!eventId) { checkBox.innerHTML = '<div style="color:#fb7185;font-weight:700;">Select an event first.</div>'; return; }
    if (!start || !end) { checkBox.innerHTML = '<div style="color:#fb7185;font-weight:700;">Event start and end are required.</div>'; return; }
    if (end <= start) { checkBox.innerHTML = '<div style="color:#fb7185;font-weight:700;">Event end must be after start.</div>'; return; }
    if (setup && setup > start) { checkBox.innerHTML = '<div style="color:#fb7185;font-weight:700;">Setup must begin before the event starts.</div>'; return; }
    if (teardown && teardown < end) { checkBox.innerHTML = '<div style="color:#fb7185;font-weight:700;">Teardown must end after the event ends.</div>'; return; }
    if (!doAssign) {
      checkBox.innerHTML = '<div style="color:var(--ecc-text-dim);">Checking availability…</div>';
      get('check_availability', { venue_id: venueId, space_id: spaceId, event_start: start, teardown_end: teardown || end }).then(function(b) {
        if (!b.success) { checkBox.innerHTML = '<div style="color:#fb7185;font-weight:700;">' + esc(errMsg(b)) + '</div>'; return; }
        var r = (b.venue_result && (b.venue_result.availability || b.venue_result)) || {};
        var h = r.available
          ? '<div style="color:#34d399;font-weight:700;">Available for this window ✓</div>'
          : '<div style="color:#fb7185;font-weight:700;">Not fully available ✗</div>';
        (r.conflicts || []).forEach(function(c) {
          h += '<div style="color:var(--ecc-text-dim);margin-top:0.25rem;">· ' + esc(c.reason || '') + '</div>';
        });
        checkBox.innerHTML = h;
      });
      return;
    }
    if (!doAssign) return;
    post({
      action: 'assign_event', venue_id: venueId, event_id: eventId, space_id: spaceId,
      setup_start: setup, event_start: start, event_end: end, teardown_end: teardown
    }).then(function(b2) {
      if (!b2.success) {
        checkBox.innerHTML = '<div style="color:#fb7185;font-weight:700;">' + esc(errMsg(b2)) + '</div>';
        var confs = (b2.error && b2.error.details && b2.error.details.conflicts) || [];
        if (confs.length) {
          checkBox.innerHTML = '<div style="color:#fda4af;font-weight:700;">Assignment blocked — conflicts:</div>';
          confs.forEach(function(c) { checkBox.innerHTML += '<div style="color:var(--ecc-text-dim);margin-top:0.25rem;">· ' + esc(c.reason) + '</div>'; });
        }
        return;
      }
      closeEccModal('modal-assign-event');
      toast('Event assigned to venue');
      loadWorkspace();
      if (venueId === state.venueId) refreshDetail();
    });
  }

  /* ── Add Venue wizard (8 steps) ──────────────────────────── */

  function wizardDefaults() {
    return {
      name: '', type: '', description: '', contact_phone: '', contact_email: '', cover_image: '',
      address: '', city: '', district: '', region: '', country: 'Malawi', gps_lat: '', gps_lng: '',
      capacity: '',
      spaces: [{ name: 'Main Hall', type: 'Theatre', capacity: '', dimensions: '', description: '' }],
      facilities: [], customFac: [],
      mediaCover: '', mediaGallery: '',
      pricing: [{ name: '', price: '', description: '' }],
      policies: {
        cancellation_policy: '', advance_booking_days: '', min_duration_hours: '', max_duration_hours: '',
        restrictions: [], opening_time: '08:00', closing_time: '17:00',
        setup_period_minutes: '120', teardown_period_minutes: '60', check_in_time: ''
      }
    };
  }

  function wizardOpen() {
    state.vwStep = 1;
    state.vwData = wizardDefaults();
    wizardRenderStep();
    openEccModal('modal-add-venue');
  }

  function wizardClose() {
    closeEccModal('modal-add-venue');
    state.vwData = null;
  }

  function wizardStep(dir) {
    if (!state.vwData) return;
    if (!wizardSaveStep(state.vwStep)) return;
    var ns = state.vwStep + dir;
    if (ns < 1 || ns > 8) return;
    state.vwStep = ns;
    wizardRenderStep();
  }

  function wizardReady() {
    var d = state.vwData || {};
    return {
      name: !!String(d.name || '').trim(),
      type: !!String(d.type || '').trim(),
      spaces: (d.spaces || []).some(function(s) { return !!String(s.name || '').trim(); }),
      facilities: !!((d.facilities || []).length || (d.customFac || []).some(function(c) { return !!String(c.name || '').trim(); })),
      media: !!String(d.mediaCover || '').trim() || !!((d.mediaGallery || []).length),
      pricing: (d.pricing || []).some(function(p) { return !!String(p.name || '').trim(); })
    };
  }

  function wizardRenderStep() {
    var title = document.getElementById('vw-step-title');
    var titles = ['Identity', 'Location', 'Capacity & Spaces', 'Facilities', 'Media', 'Pricing', 'Policies', 'Review & Publish'];
    if (title) title.textContent = titles[state.vwStep - 1] || 'Identity';
    document.querySelectorAll('#vw-steps .vw-step').forEach(function(s) {
      s.className = 'vw-step' + (Number(s.getAttribute('data-vw')) === state.vwStep ? ' active' : '');
    });
    document.getElementById('vw-body').innerHTML = wizardStepHtml(state.vwStep);
    var prev = document.getElementById('vw-prev');
    if (prev) prev.style.visibility = state.vwStep === 1 ? 'hidden' : 'visible';
    var next = document.getElementById('vw-next');
    if (!next) return;
    if (state.vwStep === 8) {
      next.style.display = 'none';
    } else {
      next.style.display = '';
      next.textContent = state.vwStep === 7 ? 'Review →' : 'Continue →';
    }
  }

  function wizardSaveStep(step) {
    var d = state.vwData;
    if (!d) return false;
    if (step === 1) {
      d.name = val('ww-i-name');
      d.type = val('ww-i-type');
      d.description = val('ww-i-desc');
      d.contact_phone = val('ww-i-phone');
      d.contact_email = val('ww-i-email');
      d.cover_image = val('ww-i-cover');
      if (!String(d.name || '').trim()) { toast('Venue name is required.'); return false; }
    } else if (step === 2) {
      d.address = val('ww-l-addr');
      d.city = val('ww-l-city');
      d.district = val('ww-l-district');
      d.region = val('ww-l-region');
      d.country = val('ww-l-country');
      d.gps_lat = val('ww-l-lat');
      d.gps_lng = val('ww-l-lng');
    } else if (step === 3) {
      d.capacity = val('ww-c-capacity');
      var rows = [];
      document.querySelectorAll('.ww-space-row').forEach(function(r) {
        rows.push({
          name: r.querySelector('.ww-space-name') ? r.querySelector('.ww-space-name').value : '',
          type: r.querySelector('.ww-space-type') ? r.querySelector('.ww-space-type').value : '',
          capacity: r.querySelector('.ww-space-cap') ? r.querySelector('.ww-space-cap').value : '',
          dimensions: r.querySelector('.ww-space-dim') ? r.querySelector('.ww-space-dim').value : '',
          description: r.querySelector('.ww-space-desc') ? r.querySelector('.ww-space-desc').value : ''
        });
      });
      d.spaces = rows;
      if (!rows.some(function(s) { return !!String(s.name || '').trim(); })) { toast('Add at least one space with a name.'); return false; }
    } else if (step === 4) {
      var facs = [];
      document.querySelectorAll('.ww-fac-preset:checked').forEach(function(r) {
        var g = r.getAttribute('data-group');
        var n = r.getAttribute('data-name');
        var desc = '';
        (FACILITY_PRESETS[g] || []).forEach(function(p) { if (p[0] === n) desc = p[1]; });
        facs.push({ name: n, group: g, description: desc, available: true });
      });
      var customs = [];
      document.querySelectorAll('.ww-cf-row').forEach(function(r) {
        var g = r.querySelector('.ww-cf-group') ? r.querySelector('.ww-cf-group').value : 'GENERAL';
        var n = r.querySelector('.ww-cf-name') ? r.querySelector('.ww-cf-name').value : '';
        if (String(n || '').trim()) customs.push({ name: n, group: g, description: '', available: true });
      });
      d.facilities = facs;
      d.customFac = customs;
    } else if (step === 5) {
      d.mediaCover = val('ww-m-cover');
      d.mediaGallery = String(val('ww-m-gallery') || '').split('\n').map(function(u) { return u.trim(); }).filter(Boolean);
    } else if (step === 6) {
      var prices = [];
      document.querySelectorAll('.ww-price-row').forEach(function(r) {
        prices.push({
          name: r.querySelector('.ww-price-name') ? r.querySelector('.ww-price-name').value : '',
          price: r.querySelector('.ww-price-amount') ? r.querySelector('.ww-price-amount').value : '',
          description: r.querySelector('.ww-price-desc') ? r.querySelector('.ww-price-desc').value : ''
        });
      });
      d.pricing = prices.filter(function(p) { return !!String(p.name || '').trim(); });
    } else if (step === 7) {
      var p = d.policies;
      p.cancellation_policy = val('ww-p-cancel');
      p.advance_booking_days = val('ww-p-advance');
      p.min_duration_hours = val('ww-p-minh');
      p.max_duration_hours = val('ww-p-maxh');
      p.opening_time = val('ww-p-open');
      p.closing_time = val('ww-p-close');
      p.setup_period_minutes = val('ww-p-setup');
      p.teardown_period_minutes = val('ww-p-teardown');
      p.check_in_time = val('ww-p-checkin');
      p.restrictions = [];
      document.querySelectorAll('.ww-restr:checked').forEach(function(r) {
        p.restrictions.push(r.getAttribute('data-name'));
      });
    }
    return true;
  }

  function wizardSpaceRow(s, i) {
    var types = SPACE_TYPES.map(function(t) { return '<option' + (s.type === t ? ' selected' : '') + '>' + t + '</option>'; }).join('');
    return '<div class="ww-space-row" style="border:1px solid var(--ecc-border);border-radius:12px;padding:0.8rem;margin-bottom:0.6rem;">'
      + '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.5rem;">'
      + '<label style="font-weight:700;font-size:0.72rem;">Name<input type="text" class="ecc-input ww-space-name" value="' + esc(s.name) + '" placeholder="e.g. Main Hall" style="display:block;width:100%;margin-top:0.2rem;"></label>'
      + '<label style="font-weight:700;font-size:0.72rem;">Layout<select class="ecc-input ww-space-type" style="display:block;width:100%;margin-top:0.2rem;">' + types + '</select></label>'
      + '<label style="font-weight:700;font-size:0.72rem;">Capacity<input type="number" min="1" class="ecc-input ww-space-cap" value="' + esc(s.capacity || '') + '" placeholder="e.g. 250" style="display:block;width:100%;margin-top:0.2rem;"></label>'
      + '</div>'
      + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-top:0.5rem;">'
      + '<label style="font-weight:700;font-size:0.72rem;">Dimensions<input type="text" class="ecc-input ww-space-dim" value="' + esc(s.dimensions || '') + '" placeholder="e.g. 20m × 15m" style="display:block;width:100%;margin-top:0.2rem;"></label>'
      + '<label style="font-weight:700;font-size:0.72rem;">Description<input type="text" class="ecc-input ww-space-desc" value="' + esc(s.description || '') + '" placeholder="Optional notes" style="display:block;width:100%;margin-top:0.2rem;"></label>'
      + '</div>'
      + '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;margin-top:0.5rem;" onclick="VenuesControlCenter.wizardDelSpace(' + i + ')">Remove space</button>'
      + '</div>';
  }

  function wizardPriceRow(p, i) {
    return '<div class="ww-price-row" style="display:grid;grid-template-columns:1.2fr 0.8fr 2fr auto;gap:0.5rem;align-items:center;margin-bottom:0.5rem;">'
      + '<input type="text" class="ecc-input ww-price-name" value="' + esc(p.name || '') + '" placeholder="Package name" style="font-size:0.75rem;">'
      + '<input type="number" min="0" step="0.01" class="ecc-input ww-price-amount" value="' + esc(p.price || '') + '" placeholder="Price (MWK)" style="font-size:0.75rem;">'
      + '<input type="text" class="ecc-input ww-price-desc" value="' + esc(p.description || '') + '" placeholder="What is included" style="font-size:0.75rem;">'
      + '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.2rem 0.55rem;" onclick="VenuesControlCenter.wizardDelPrice(' + i + ')">✕</button>'
      + '</div>';
  }

  function wizardStepHtml(step) {
    var d = state.vwData || {};
    var h = '';
    if (step === 1) {
      var types = VENUE_TYPES.map(function(t) { return '<option' + (d.type === t ? ' selected' : '') + '>' + t + '</option>'; }).join('');
      h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.7rem;">'
        + '<label style="font-weight:700;font-size:0.75rem;">Venue name *<input type="text" id="ww-i-name" class="ecc-input" value="' + esc(d.name) + '" placeholder="e.g. Bingu International Convention Centre" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">Venue type<select id="ww-i-type" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;"><option value="">Select…</option>' + types + '</select></label>'
        + '</div>'
        + '<label style="font-weight:700;font-size:0.75rem;display:block;margin-top:0.7rem;">Description<textarea id="ww-i-desc" class="ecc-input" rows="4" style="display:block;width:100%;margin-top:0.2rem;resize:vertical;">' + esc(d.description) + '</textarea></label>'
        + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.7rem;margin-top:0.7rem;">'
        + '<label style="font-weight:700;font-size:0.75rem;">Contact phone<input type="text" id="ww-i-phone" class="ecc-input" value="' + esc(d.contact_phone) + '" placeholder="+265 99 000 0000" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">Contact email<input type="email" id="ww-i-email" class="ecc-input" value="' + esc(d.contact_email) + '" placeholder="bookings@venue.mw" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '</div>'
        + '<label style="font-weight:700;font-size:0.75rem;display:block;margin-top:0.7rem;">Cover image URL<input type="url" id="ww-i-cover" class="ecc-input" value="' + esc(d.cover_image || d.mediaCover) + '" placeholder="https://…/cover.jpg" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<div style="margin-top:0.4rem;"><label style="font-size:0.68rem;color:var(--ecc-text-dim);">Or upload an image (JPG, PNG or WebP, max 10 MB)</label>'
        + '<div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.25rem;">'
        + '<input type="file" id="ww-i-cover-file" accept="image/jpeg,image/png,image/webp" style="font-size:0.7rem;flex:1;min-width:0;">'
        + '<button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.68rem;padding:0.25rem 0.6rem;display:inline-flex;align-items:center;gap:0.3rem;" id="ww-i-cover-up" onclick="VenuesControlCenter.wizardUploadCover(\'ww-i-cover-file\', \'ww-i-cover\', this)">' + icon('clock') + ' Upload</button>'
        + '</div><div id="ww-i-cover-msg" style="font-size:0.64rem;color:var(--ecc-text-dim);margin-top:0.15rem;"></div></div>';
    } else if (step === 2) {
      h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.7rem;">'
        + '<label style="font-weight:700;font-size:0.75rem;">Street address<input type="text" id="ww-l-addr" class="ecc-input" value="' + esc(d.address) + '" placeholder="e.g. Convention Drive" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">City / town<input type="text" id="ww-l-city" class="ecc-input" value="' + esc(d.city) + '" placeholder="e.g. Lilongwe" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">District<input type="text" id="ww-l-district" class="ecc-input" value="' + esc(d.district) + '" placeholder="e.g. Lilongwe" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">Region<input type="text" id="ww-l-region" class="ecc-input" value="' + esc(d.region) + '" placeholder="e.g. Central Region" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '</div>'
        + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.7rem;margin-top:0.7rem;">'
        + '<label style="font-weight:700;font-size:0.75rem;">Country<input type="text" id="ww-l-country" class="ecc-input" value="' + esc(d.country) + '" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">Coordinates<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.2rem 0.6rem;margin-left:0.4rem;" onclick="VenuesControlCenter.wizardGeo()">Use my location</button><div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-top:0.3rem;"><input type="text" id="ww-l-lat" class="ecc-input" value="' + esc(d.gps_lat) + '" placeholder="Latitude" style="font-size:0.72rem;"><input type="text" id="ww-l-lng" class="ecc-input" value="' + esc(d.gps_lng) + '" placeholder="Longitude" style="font-size:0.72rem;"></div></label>'
        + '</div>'
        + '<div style="font-size:0.7rem;color:var(--ecc-text-dim);margin-top:0.7rem;">Both latitude and longitude are stored as decimals (e.g. -13.9626, 33.7741 for Lilongwe). "Use my location" fills them from the browser GPS.</div>';
    } else if (step === 3) {
      h += '<label style="font-weight:700;font-size:0.75rem;">Total venue capacity<input type="number" min="1" id="ww-c-capacity" class="ecc-input" value="' + esc(d.capacity || '') + '" placeholder="e.g. 1200" style="display:block;width:100%;margin-top:0.2rem;"></label>';
      h += '<div style="display:flex;justify-content:space-between;align-items:center;margin:0.9rem 0 0.6rem;"><strong style="font-size:0.78rem;">Bookable spaces</strong><button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.7rem;padding:0.3rem 0.7rem;" onclick="VenuesControlCenter.wizardAddSpace()">+ Add space</button></div>';
      h += '<div style="font-size:0.7rem;color:var(--ecc-text-dim);margin-bottom:0.6rem;">Spaces can be booked individually or as a whole venue. At least one named space is required.</div>';
      (d.spaces || []).forEach(function(s, i) { h += wizardSpaceRow(s, i); });
    } else if (step === 4) {
      h += '<div style="font-size:0.7rem;color:var(--ecc-text-dim);margin-bottom:0.7rem;">Tick the facilities available at this venue, or add your own.</div>';
      Object.keys(FACILITY_PRESETS).forEach(function(g) {
        h += '<div style="margin-bottom:0.8rem;"><strong style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--ecc-text-dim);">' + g + '</strong>';
        h += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.35rem;margin-top:0.4rem;">';
        FACILITY_PRESETS[g].forEach(function(p) {
          var checked = (d.facilities || []).some(function(f) { return f.name === p[0] && f.group === g; });
          h += '<label style="font-size:0.72rem;display:flex;gap:0.4rem;align-items:center;cursor:pointer;"><input type="checkbox" class="ww-fac-preset" data-group="' + g + '" data-name="' + esc(p[0]) + '"' + (checked ? ' checked' : '') + '> ' + esc(p[0]) + '</label>';
        });
        h += '</div></div>';
      });
      h += '<div style="display:flex;justify-content:space-between;align-items:center;margin:0.4rem 0 0.6rem;"><strong style="font-size:0.78rem;">Custom facilities</strong><button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.7rem;padding:0.3rem 0.7rem;" onclick="VenuesControlCenter.wizardAddCustomFac()">+ Add</button></div>';
      (d.customFac || []).forEach(function(c, i) {
        h += '<div class="ww-cf-row" style="display:grid;grid-template-columns:0.8fr 1.6fr auto;gap:0.5rem;margin-bottom:0.5rem;">'
          + '<select class="ecc-input ww-cf-group" style="font-size:0.72rem;">' + Object.keys(FACILITY_PRESETS).map(function(g) { return '<option' + (c.group === g ? ' selected' : '') + '>' + g + '</option>'; }).join('') + '</select>'
          + '<input type="text" class="ecc-input ww-cf-name" value="' + esc(c.name) + '" placeholder="Facility name" style="font-size:0.72rem;">'
          + '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.2rem 0.55rem;" onclick="VenuesControlCenter.wizardDelCustomFac(' + i + ')">✕</button>'
          + '</div>';
      });
    } else if (step === 5) {
      h += '<label style="font-weight:700;font-size:0.75rem;">Cover image URL<input type="url" id="ww-m-cover" class="ecc-input" value="' + esc(d.mediaCover || '') + '" placeholder="https://…/cover.jpg" style="display:block;width:100%;margin-top:0.2rem;"></label>';
      h += '<div style="margin-top:0.4rem;"><label style="font-size:0.68rem;color:var(--ecc-text-dim);">Or upload an image (JPG, PNG or WebP, max 10 MB)</label>'
        + '<div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.25rem;">'
        + '<input type="file" id="ww-m-cover-file" accept="image/jpeg,image/png,image/webp" style="font-size:0.7rem;flex:1;min-width:0;">'
        + '<button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.68rem;padding:0.25rem 0.6rem;display:inline-flex;align-items:center;gap:0.3rem;" id="ww-m-cover-up" onclick="VenuesControlCenter.wizardUploadCover(\'ww-m-cover-file\', \'ww-m-cover\', this)">' + icon('clock') + ' Upload</button>'
        + '</div><div id="ww-m-cover-msg" style="font-size:0.64rem;color:var(--ecc-text-dim);margin-top:0.15rem;"></div></div>';
      h += '<div style="display:flex;justify-content:space-between;align-items:center;margin:0.9rem 0 0.6rem;"><strong style="font-size:0.78rem;">Gallery</strong><label style="font-size:0.7rem;color:var(--ecc-text-dim);">One image URL per line</label></div>';
      h += '<textarea id="ww-m-gallery" class="ecc-input" rows="5" placeholder="https://…/photo1.jpg&#10;https://…/photo2.jpg" style="display:block;width:100%;resize:vertical;">' + esc((d.mediaGallery || []).join('\n')) + '</textarea>';
      h += '<div style="font-size:0.7rem;color:var(--ecc-text-dim);margin-top:0.6rem;">Floor plans and extra media can be managed later from the venue workspace.</div>';
    } else if (step === 6) {
      h += '<div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.9rem;">';
      PRICE_PRESETS.forEach(function(p, i) {
        h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.25rem 0.6rem;" onclick="VenuesControlCenter.wizardPricePresets(' + i + ')">+ ' + p.name + '</button>';
      });
      h += '</div>';
      (d.pricing || []).forEach(function(p, i) { h += wizardPriceRow(p, i); });
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.7rem;padding:0.3rem 0.7rem;" onclick="VenuesControlCenter.wizardAddPrice()">+ Add custom package</button>';
      h += '<div style="font-size:0.7rem;color:var(--ecc-text-dim);margin-top:0.6rem;">One package name and price are required. Prices are in MWK.</div>';
    } else if (step === 7) {
      var p = d.policies || {};
      h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.7rem;">'
        + '<label style="font-weight:700;font-size:0.75rem;">Advance booking (days)<input type="number" min="0" id="ww-p-advance" class="ecc-input" value="' + esc(p.advance_booking_days || '') + '" placeholder="e.g. 7" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">Min duration (hours)<input type="number" min="0" id="ww-p-minh" class="ecc-input" value="' + esc(p.min_duration_hours || '') + '" placeholder="e.g. 2" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">Max duration (hours)<input type="number" min="0" id="ww-p-maxh" class="ecc-input" value="' + esc(p.max_duration_hours || '') + '" placeholder="e.g. 48" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">Check-in time<input type="time" id="ww-p-checkin" class="ecc-input" value="' + esc(p.check_in_time || '') + '" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">Opening time<input type="time" id="ww-p-open" class="ecc-input" value="' + esc(p.opening_time || '08:00') + '" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">Closing time<input type="time" id="ww-p-close" class="ecc-input" value="' + esc(p.closing_time || '17:00') + '" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">Setup period (min)<input type="number" min="0" id="ww-p-setup" class="ecc-input" value="' + esc(p.setup_period_minutes || '') + '" placeholder="e.g. 120" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '<label style="font-weight:700;font-size:0.75rem;">Teardown period (min)<input type="number" min="0" id="ww-p-teardown" class="ecc-input" value="' + esc(p.teardown_period_minutes || '') + '" placeholder="e.g. 60" style="display:block;width:100%;margin-top:0.2rem;"></label>'
        + '</div>'
        + '<label style="font-weight:700;font-size:0.75rem;display:block;margin-top:0.7rem;">Cancellation policy<textarea id="ww-p-cancel" class="ecc-input" rows="3" style="display:block;width:100%;margin-top:0.2rem;resize:vertical;">' + esc(p.cancellation_policy || '') + '</textarea></label>'
        + '<strong style="font-size:0.75rem;display:block;margin-top:0.9rem;">Restrictions</strong>'
        + '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.35rem;margin-top:0.4rem;">';
      RESTRICTION_PRESETS.forEach(function(r) {
        var checked = (p.restrictions || []).indexOf(r) !== -1;
        h += '<label style="font-size:0.72rem;display:flex;gap:0.4rem;align-items:center;cursor:pointer;"><input type="checkbox" class="ww-restr" data-name="' + esc(r) + '"' + (checked ? ' checked' : '') + '> ' + esc(r) + '</label>';
      });
      h += '</div>';
    } else if (step === 8) {
      var r = wizardReady();
      var checks = [
        ['Venue name', r.name], ['Venue type', r.type], ['Bookable spaces', r.spaces],
        ['Facilities', r.facilities], ['Media', r.media], ['Pricing packages', r.pricing]
      ];
      h += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:0.45rem;margin-bottom:1rem;">';
      checks.forEach(function(c) {
        h += '<div style="padding:0.55rem 0.7rem;border-radius:10px;background:var(--ecc-surface-2);border:1px solid ' + (c[1] ? 'rgba(52,211,153,0.35)' : 'rgba(251,113,133,0.35)') + ';font-size:0.72rem;">'
          + (c[1] ? '<span style="color:#34d399;display:inline-flex;align-items:center;">' + icon('check') + '</span>' : '<span style="color:#fbbf24;display:inline-flex;align-items:center;">' + icon('warn') + '</span>') + ' ' + c[0] + '</div>';
      });
      h += '</div>';
      h += '<div class="ecc-card" style="padding:0.9rem 1rem;margin-bottom:1rem;font-size:0.72rem;">';
      h += '<strong style="font-size:0.78rem;">' + esc(d.name || 'Untitled venue') + '</strong>'
        + '<div style="color:var(--ecc-text-dim);margin-top:0.3rem;">'
        + esc([d.address, d.city, d.district, d.region, d.country].filter(Boolean).join(', ') || 'Location not set')
        + ' · ' + esc(String(d.capacity || '').replace(/\B(?=(\d{3})+(?!\d))/g, ',') || '—') + ' capacity'
        + ' · ' + (d.spaces || []).length + ' space(s) · ' + (d.pricing || []).length + ' package(s)'
        + '</div></div>';
      h += '<div style="display:flex;gap:0.6rem;">';
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="flex:1;font-size:0.74rem;" onclick="VenuesControlCenter.wizardSubmit(\'DRAFT\')">Save as Draft</button>';
      h += '<button type="button" class="ecc-btn ecc-btn-primary" style="flex:1;font-size:0.74rem;" onclick="VenuesControlCenter.wizardSubmit(\'ACTIVE\')">Publish Venue</button>';
      h += '</div>';
      h += '<div style="font-size:0.7rem;color:var(--ecc-text-dim);margin-top:0.7rem;">Publishing makes the venue visible in the public directory. You can still manage everything from the workspace afterwards.</div>';
    }
    return h;
  }

  function wizardAddSpace() {
    (state.vwData.spaces = state.vwData.spaces || []).push({ name: '', type: 'Theatre', capacity: '', dimensions: '', description: '' });
    wizardRenderStep();
  }
  function wizardDelSpace(i) {
    state.vwData.spaces.splice(i, 1);
    wizardRenderStep();
  }
  function wizardAddPrice() {
    (state.vwData.pricing = state.vwData.pricing || []).push({ name: '', price: '', description: '' });
    wizardRenderStep();
  }
  function wizardDelPrice(i) {
    state.vwData.pricing.splice(i, 1);
    wizardRenderStep();
  }
  function wizardPricePresets(idx) {
    var p = PRICE_PRESETS[idx];
    if (!p) return;
    (state.vwData.pricing = state.vwData.pricing || []).push({ name: p.name, price: p.price, description: p.desc });
    wizardRenderStep();
  }
  function wizardAddCustomFac() {
    (state.vwData.customFac = state.vwData.customFac || []).push({ group: 'GENERAL', name: '' });
    wizardRenderStep();
  }
  function wizardDelCustomFac(i) {
    state.vwData.customFac.splice(i, 1);
    wizardRenderStep();
  }
  function wizardGeo() {
    if (!window.UthengaTieLocation) { toast('Location helper unavailable.'); return; }
    UthengaTieLocation.currentPosition().then(function(position) {
      state.vwData.gps_lat = String(Number(position.coords.latitude).toFixed(6));
      state.vwData.gps_lng = String(Number(position.coords.longitude).toFixed(6));
      wizardRenderStep();
    }).catch(function(e) {
      toast('Location: ' + ((e && (e.message || e.code)) || 'permission denied'));
    });
  }

  function wizardUploadCover(fileId, urlId, btn) {
    var f = document.getElementById(fileId);
    var msg = document.getElementById(urlId + '-msg');
    if (!f || !f.files || !f.files.length) { if (msg) msg.textContent = 'Choose an image file first.'; return; }
    var file = f.files[0];
    if (file.size > 10 * 1024 * 1024) { if (msg) { msg.textContent = 'The file must be smaller than 10 MB.'; msg.style.color = '#fb7185'; } return; }
    var fd = new FormData();
    fd.append('file', file);
    var original = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = 'Uploading…'; }
    if (msg) { msg.textContent = 'Uploading…'; msg.style.color = 'var(--ecc-text-dim)'; }
    fetch(base + 'api/tie/vendor/events/cover-image.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf },
      body: fd
    }).then(function(r) { return r.json(); }).then(function(d) {
      if (btn) { btn.disabled = false; btn.innerHTML = original; }
      var urlInput = document.getElementById(urlId);
      if (d && d.success && d.url) {
        if (urlInput) urlInput.value = d.url;
        if (msg) { msg.textContent = 'Cover uploaded.'; msg.style.color = '#34d399'; }
        toast('Cover image uploaded.');
      } else {
        if (msg) { msg.textContent = ((d && d.error && d.error.message) || 'Upload failed.'); msg.style.color = '#fb7185'; }
      }
    }).catch(function() {
      if (btn) { btn.disabled = false; btn.innerHTML = original; }
      if (msg) { msg.textContent = 'Upload failed. Check your connection and try again.'; msg.style.color = '#fb7185'; }
    });
  }

  function wizardSubmit(status) {
    var d = state.vwData;
    if (!d) return;
    wizardSaveStep(state.vwStep);
    var r = wizardReady();
    if (!String(d.name || '').trim()) { state.vwStep = 1; wizardRenderStep(); toast('Venue name is required.'); return; }
    if (!r.spaces || !r.facilities || !r.pricing) { toast('Add at least one space, one facility and one pricing package.'); return; }
    var facilities = (d.facilities || []).concat(d.customFac || []);
    var media = [];
    if (String(d.mediaCover || '').trim()) media.push({ url: String(d.mediaCover).trim(), type: 'COVER', is_cover: 1 });
    (d.mediaGallery || []).forEach(function(u) { media.push({ url: u, type: 'GALLERY', is_cover: 0 }); });
    var btn = document.getElementById('vw-next');
    if (btn) btn.style.display = 'none';
    post({
      action: 'create_venue', status: status,
      name: d.name, type: d.type, description: d.description,
      contact_phone: d.contact_phone, contact_email: d.contact_email, cover_image: String(d.mediaCover || d.cover_image || '').trim(),
      address: d.address, city: d.city, district: d.district, region: d.region, country: d.country,
      gps_lat: d.gps_lat, gps_lng: d.gps_lng, capacity: d.capacity,
      spaces: d.spaces, facilities: facilities, media: media, pricing: d.pricing,
      policies: d.policies
    }).then(function(b) {
      if (btn) btn.style.display = '';
      if (!b.success) { toast('Venue: ' + errMsg(b)); return; }
      toast(status === 'ACTIVE' ? 'Venue published' : 'Venue saved as draft');
      closeEccModal('modal-add-venue');
      state.vwData = null;
      loadWorkspace();
    });
  }  function openBlock(venueId, dayStr) {
    var d = (venueId === state.venueId && state.detail) ? state.detail : null;
    var sp = document.getElementById('vb-space');
    sp.innerHTML = '<option value="">Whole venue</option>';
    ((d && d.spaces) || []).forEach(function(s) {
      var opt = document.createElement('option');
      opt.value = s.id;
      opt.textContent = s.name;
      sp.appendChild(opt);
    });
    if (dayStr && /^\d{4}-\d{2}-\d{2}$/.test(dayStr)) {
      document.getElementById('vb-start').value = dayStr + 'T08:00';
      document.getElementById('vb-end').value = dayStr + 'T17:00';
    } else {
      var now = new Date();
      var t = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 8, 0);
      var t2 = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 17, 0);
      function iso(x) { return x.getFullYear() + '-' + String(x.getMonth() + 1).padStart(2, '0') + '-' + String(x.getDate()).padStart(2, '0') + 'T' + String(x.getHours()).padStart(2, '0') + ':' + String(x.getMinutes()).padStart(2, '0'); }
      document.getElementById('vb-start').value = iso(t);
      document.getElementById('vb-end').value = iso(t2);
    }
    document.getElementById('vb-reason').value = '';
    document.getElementById('vb-status').value = 'BLOCKED';
    openEccModal('modal-block-date');
  }
  function blockSubmit() {
    post({
      action: 'set_availability', venue_id: state.venueId,
      start_at: val('vb-start'), end_at: val('vb-end'),
      status: val('vb-status'), reason: val('vb-reason'), space_id: val('vb-space')
    }).then(function(b) {
      if (!b.success) { toast('Block: ' + errMsg(b)); return; }
      closeEccModal('modal-block-date');
      toast('Availability updated');
      applyDetailResult(b);
    });
  }

  /* ── Status / delete / helpers ────────────────────────────── */
  function quickStatus(venueId, status) {
    if (!status) return;
    post({ action: 'update_status', venue_id: venueId, status: status }).then(function(b) {
      if (!b.success) { toast('Status: ' + errMsg(b)); return; }
      toast('Venue status updated');
      if (venueId === state.venueId) refreshDetail();
      loadWorkspace();
    });
  }
  function removeVenue(venueId) {
    if (!window.confirm('Delete this venue? Venues with confirmed bookings cannot be removed.')) return;
    if (!window.confirm('Are you really sure? This cannot be undone.')) return;
    post({ action: 'delete_venue', venue_id: venueId }).then(function(b) {
      if (!b.success) { toast('Venue: ' + errMsg(b)); return; }
      toast('Venue removed');
      if (venueId === state.venueId) closeWorkspace(); else loadWorkspace();
    });
  }
  function toggleCardMenu(venueId) {
    var el = document.getElementById('vc-menu-' + venueId);
    if (!el) return;
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }
  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value : '';
  }

  /* ── Boot ─────────────────────────────────────────────────── */
  function init() {
    var search = document.getElementById('vc-search');
    if (search) {
      search.addEventListener('input', function() {
        window.clearTimeout(state._searchTimer);
        state._searchTimer = window.setTimeout(function() {
          state.search = String(search.value || '').trim();
          loadWorkspace();
        }, 350);
      });
    }
    loadWorkspace();
    window.onEccModuleShow = window.onEccModuleShow || function() {};
  }

  return {
    state: state,
    init: init,
    loadWorkspace: loadWorkspace,
    switchView: switchView,
    jumpToCal: jumpToCal,
    calNav: calNav,
    calToday: calToday,
    calVenueChanged: calVenueChanged,
    calDayClick: calDayClick,
    openWorkspace: openWorkspace,
    closeWorkspace: closeWorkspace,
    goSub: goSub,
    renderSub: renderSub,
    saveDetails: saveDetails,
    addSpaceFn: addSpaceFn,
    editSpace: editSpace,
    removeSpace: removeSpace,
    avNav: avNav,
    unassign: unassign,
    removeBlock: removeBlock,
    pricePresets: pricePresets,
    priceAdd: priceAdd,
    priceRemove: priceRemove,
    priceSaveAll: priceSaveAll,
    facAdd: facAdd,
    facSave: facSave,
    mediaMove: mediaMove,
    mediaCover: mediaCover,
    mediaRemove: mediaRemove,
    mediaAdd: mediaAdd,
    mediaSave: mediaSave,
    savePoliciesFn: savePoliciesFn,
    wizardOpen: wizardOpen,
    wizardClose: wizardClose,
    wizardStep: wizardStep,
    wizardAddSpace: wizardAddSpace,
    wizardAddPrice: wizardAddPrice,
    wizardPricePresets: wizardPricePresets,
    wizardAddCustomFac: wizardAddCustomFac,
    wizardGeo: wizardGeo,
    wizardUploadCover: wizardUploadCover,
    wizardSubmit: wizardSubmit,
    wizardDelSpace: wizardDelSpace,
    wizardDelPrice: wizardDelPrice,
    wizardDelCustomFac: wizardDelCustomFac,
    openAssign: openAssign,
    assignCheck: assignCheck,
    openBlock: openBlock,
    blockSubmit: blockSubmit,
    quickStatus: quickStatus,
    removeVenue: removeVenue,
    toggleCardMenu: toggleCardMenu
  };
})();

if (window.VenuesControlCenter && typeof window.VenuesControlCenter.init === 'function') {
  window.VenuesControlCenter.init();
}
