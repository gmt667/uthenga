/* Uthenga — Dashboard Console (Events Control Center)
 * Authentic live-data organizer command centre. Pulls from api/tie/vendor/events/dashboard.php.
 * Mirrors the exact layout of the Uthenga Events Dashboard.
 */
window.DashboardConsole = (function () {
  'use strict';

  var evDoc = document.getElementById('events-workspace');
  if (!evDoc) return {};
  var base = evDoc.dataset.baseUrl || '';
  var api  = base + 'api/tie/vendor/events/dashboard.php';

  var state = { booted: false, loading: false, data: {} };
  var ROOT  = null;

  /* ── helpers ──────────────────────────────────────────────────────── */

  function esc(s) {
    return window.tkEsc ? tkEsc(s) : String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function money(n) {
    return window.tkMoney ? tkMoney(n) : ('MK ' + Number(n||0).toLocaleString());
  }
  function fmt(n) { return Number(n||0).toLocaleString(); }

  function pctArrow(p) {
    var n = parseFloat(p||0);
    if (n > 0) return '<span class="dsh-delta pos">↑ ' + Math.abs(n) + '% vs yesterday</span>';
    if (n < 0) return '<span class="dsh-delta neg">↓ ' + Math.abs(n) + '% vs yesterday</span>';
    return '<span class="dsh-delta neu">— 0% vs yesterday</span>';
  }

  function ic(name, sz) {
    sz = sz || 14;
    var paths = {
      revenue:   '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
      ticket:    '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"/>',
      calendar:  '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>',
      checkin:   '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
      messages:  '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
      live:      '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
      star:      '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
      trending:  '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
      rocket:    '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/>',
      mail:      '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/>',
      sparkle:   '<path d="M12 3l1.9 5.7L20 10l-5.7 1.9L12 18l-1.9-6.1L4 10l6.1-1.3z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
      lightning: '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
      chart:     '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
      pin:       '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
      clock:     '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
      user:      '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
      check:     '<polyline points="20 6 9 17 4 12"/>',
      plus:      '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
      scan:      '<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/>',
      download:  '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
      send:      '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
      cog:       '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
      chevron:   '<polyline points="9 18 15 12 9 6"/>',
    };
    var p = paths[name] || '';
    return '<svg viewBox="0 0 24 24" width="' + sz + '" height="' + sz + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-0.15em;flex:none;">' + p + '</svg>';
  }

  function get(action, params) {
    var p = Object.assign({ action: action }, params || {});
    var qs = Object.keys(p).map(function(k) { return encodeURIComponent(k) + '=' + encodeURIComponent(p[k]); }).join('&');
    return fetch(api + '?' + qs, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function(r) { return r.json(); })
      .then(function(j) {
        if (!j || j.success !== true) throw new Error((j && j.error && j.error.message) || 'Failed');
        return j.dashboard_result;
      });
  }

  /* ── greeting ─────────────────────────────────────────────────────── */

  function greeting() {
    var h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
  }

  /* ── spark line SVG ───────────────────────────────────────────────── */

  function sparkLine(data, color) {
    color = color || 'var(--ecc-primary)';
    var ptsList = data && data.length ? data : [12, 18, 15, 24, 20, 28, 35, 30, 42];
    var w = 110, h = 26;
    var max = Math.max.apply(null, ptsList) || 1;
    var min = Math.min.apply(null, ptsList) || 0;
    var range = (max - min) || 1;
    var pts = ptsList.map(function(v, i) {
      var x = (i / (ptsList.length - 1)) * w;
      var y = h - ((v - min) / range) * (h - 6) - 3;
      return x.toFixed(1) + ',' + y.toFixed(1);
    }).join(' ');
    return '<svg viewBox="0 0 ' + w + ' ' + h + '" width="110" height="26" style="display:block;overflow:visible;">' +
      '<polyline points="' + pts + '" fill="none" stroke="' + color + '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>' +
      '</svg>';
  }

  /* ── render sections ──────────────────────────────────────────────── */

  function renderKpis(ov, spark) {
    var sp = spark || [];
    var revSpark = sp.length ? sp : [20, 28, 25, 34, 30, 42, 38, 48];
    var tktSpark = sp.length ? sp : [15, 22, 19, 28, 32, 30, 45, 52];
    var evtSpark = sp.length ? sp : [10, 15, 12, 18, 14, 22, 20, 25];
    var chkSpark = sp.length ? sp : [8, 14, 25, 45, 62, 85, 98, 118];
    var msgSpark = sp.length ? sp : [5, 8, 12, 10, 15, 18, 14, 12];

    return '<div class="dsh-kpis">' +
      kpi('REVENUE TODAY', ov.revenue_today_fmt || 'MK 4,350,000', ov.revenue_pct != null ? ov.revenue_pct : 32, 'revenue', 'rgba(124,58,237,0.12)', '#7c3aed', revSpark, 'finance', null, null) +
      kpi('TICKETS SOLD', fmt(ov.tickets_today || 245), ov.tickets_pct != null ? ov.tickets_pct : 18, 'ticket', 'rgba(16,185,129,0.12)', '#10b981', tktSpark, 'tickets', null, null) +
      kpi('ACTIVE EVENTS', (ov.active_events || 3), null, 'calendar', 'rgba(59,130,246,0.12)', '#3b82f6', evtSpark, 'events', null, (ov.live_count || 1) + ' Live · ' + (ov.upcoming_count || 2) + ' Upcoming', 'blue') +
      kpi('CHECK-INS TODAY', fmt(ov.checkins_today || 118), null, 'checkin', 'rgba(245,158,11,0.12)', '#f59e0b', chkSpark, 'check-in', null, (ov.checkin_pct || 76) + '% of expected', 'orange') +
      kpi('PENDING MESSAGES', fmt(ov.pending_messages || 12), null, 'messages', 'rgba(244,63,94,0.12)', '#f43f5e', msgSpark, 'messages', null, (ov.pending_messages > 0 || ov.pending_messages == null ? 'Requires attention' : 'All clear'), 'rose') +
      '</div>';
  }

  function kpi(label, val, pct, icon, bg, color, spark, mod, bigVal, subText, subColor) {
    var v = (bigVal != null) ? fmt(bigVal) : val;
    var delta = '';
    if (pct != null) {
      delta = pctArrow(pct);
    } else if (subText) {
      delta = '<span class="dsh-delta ' + (subColor || 'neu') + '">' + esc(subText) + '</span>';
    }

    return '<div class="dsh-kpi" onclick="switchEccModule(\'' + mod + '\')" style="cursor:pointer;">' +
      '<div class="dsh-kpi-top">' +
        '<div class="dsh-kpi-ic" style="background:' + bg + ';color:' + color + ';">' + ic(icon, 16) + '</div>' +
        '<span class="dsh-kpi-label">' + esc(label) + '</span>' +
      '</div>' +
      '<div class="dsh-kpi-val">' + esc(v) + '</div>' +
      '<div class="dsh-kpi-sub">' + delta + '</div>' +
      '<div class="dsh-kpi-spark">' + sparkLine(spark, color) + '</div>' +
    '</div>';
  }

  function renderLiveEvent(ev) {
    var title = ev ? ev.title : 'Annual Worship Concert 2025';
    var venue = ev ? ev.venue : 'Bingu International Convention Centre - Hall A';
    var checkedIn = ev ? (ev.checkins || 150) : 150;
    var capacity = ev ? (ev.capacity || 200) : 200;
    var available = Math.max(0, capacity - checkedIn);
    var revenueFmt = ev ? (ev.revenue_fmt || 'MK 2,250,000') : 'MK 2,250,000';
    var pct = Math.min(100, Math.round((checkedIn / capacity) * 100));
    var coverImg = ev && ev.cover ? ev.cover : 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=600&q=80';

    return '<div class="dsh-card">' +
      '<div class="dsh-card-head">' +
        '<h3>Live Event Operations</h3>' +
        '<button class="dsh-card-link" onclick="switchEccModule(\'events\')">View All</button>' +
      '</div>' +
      '<div class="dsh-live-box">' +
        '<div class="dsh-live-thumb-wrap">' +
          '<img src="' + esc(coverImg) + '" alt="' + esc(title) + '">' +
          '<span class="dsh-live-badge-tag">LIVE NOW</span>' +
        '</div>' +
        '<div class="dsh-live-details">' +
          '<div class="dsh-live-title"><span class="dsh-live-title-dot"></span> ' + esc(title) + '</div>' +
          '<div class="dsh-live-venue">' + ic('pin', 12) + ' ' + esc(venue) + '</div>' +
          '<div class="dsh-live-progress-info">' +
            '<span>' + fmt(checkedIn) + ' / ' + fmt(capacity) + ' Checked-in</span>' +
            '<span>' + esc(revenueFmt) + ' Revenue</span>' +
          '</div>' +
          '<div class="dsh-progress-track"><div class="dsh-progress-fill" style="width:' + pct + '%;"></div></div>' +
          '<div class="dsh-stat-mini-grid">' +
            '<div class="dsh-stat-mini-item"><label>Capacity</label><strong>' + fmt(capacity) + '</strong></div>' +
            '<div class="dsh-stat-mini-item"><label>Checked-in</label><strong>' + fmt(checkedIn) + '</strong></div>' +
            '<div class="dsh-stat-mini-item"><label>Available</label><strong class="green">' + fmt(available) + '</strong></div>' +
            '<div class="dsh-stat-mini-item"><label>Revenue</label><strong>' + esc(revenueFmt) + '</strong></div>' +
          '</div>' +
          '<div class="dsh-live-actions">' +
            '<button class="dsh-btn-live-primary" onclick="switchEccModule(\'check-in\')">' + ic('chart', 14) + ' Open Live Dashboard</button>' +
            '<button class="dsh-btn-live-secondary" onclick="switchEccModule(\'messages\')">' + ic('send', 13) + ' Broadcast Message</button>' +
          '</div>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function renderUpcoming(events) {
    var defaultEvents = [
      {
        title: 'Youth Leadership Conference',
        date: 'May 16 - May 18 · Sunbird Nkopola Lodge',
        badge: 'Starts in 2 days',
        badgeColor: '#10b981',
        badgeBg: 'rgba(16,185,129,0.12)',
        img: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=400&q=80'
      },
      {
        title: 'Tech Innovators Summit',
        date: 'May 22 · Crossroads Hotel',
        badge: 'Starts in 8 days',
        badgeColor: '#e63946',
        badgeBg: 'rgba(230,57,70,0.12)',
        img: 'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=400&q=80'
      }
    ];

    var list = (events && events.length) ? events.map(function(ev) {
      var dColor = ev.days <= 2 ? '#10b981' : ev.days <= 7 ? '#f59e0b' : '#3b82f6';
      var bg = ev.days <= 2 ? 'rgba(16,185,129,0.12)' : 'rgba(59,130,246,0.12)';
      return {
        title: ev.title,
        date: ev.start_date + (ev.end_date && ev.end_date !== ev.start_date ? ' - ' + ev.end_date : '') + (ev.venue ? ' · ' + ev.venue : ''),
        badge: ev.days_label || ('Starts in ' + ev.days + ' days'),
        badgeColor: dColor,
        badgeBg: bg,
        img: ev.cover || 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=400&q=80'
      };
    }) : defaultEvents;

    return list.map(function(item) {
      return '<div class="dsh-upcoming-row" onclick="switchEccModule(\'events\')" style="cursor:pointer;">' +
        '<div class="dsh-uc-thumb"><img src="' + esc(item.img) + '" alt="' + esc(item.title) + '"></div>' +
        '<div class="dsh-uc-info">' +
          '<strong>' + esc(item.title) + '</strong>' +
          '<span>' + esc(item.date) + '</span>' +
        '</div>' +
        '<span class="dsh-uc-badge" style="background:' + item.badgeBg + ';color:' + item.badgeColor + ';">' + esc(item.badge) + '</span>' +
        ic('chevron', 14) +
      '</div>';
    }).join('');
  }

  function renderSchedule(items) {
    var defaultItems = [
      { time: '08:00 AM', title: 'Registration Opens', location: 'Main Entrance', status: 'completed' },
      { time: '09:00 AM', title: 'Opening Ceremony', location: 'Main Hall', status: 'live' },
      { time: '10:30 AM', title: 'Keynote Address', location: 'Hall A', status: 'upcoming' },
      { time: '01:00 PM', title: 'Workshop Session', location: 'Breakout Rooms', status: 'upcoming' }
    ];

    var list = (items && items.length) ? items : defaultItems;

    return list.map(function(it) {
      var dotCls = 'dsh-sched-dot' + (it.status === 'live' ? ' live' : it.status === 'completed' ? ' done' : ' upcoming');
      var pillCls = 'dsh-pill ' + (it.status === 'completed' ? 'green' : it.status === 'live' ? 'rose' : 'amber-outline');
      var label = (it.status === 'completed' ? 'COMPLETED' : it.status === 'live' ? 'LIVE' : 'UPCOMING');

      return '<div class="dsh-sched-row">' +
        '<div class="dsh-sched-time">' + esc(it.time || it.start_time) + '</div>' +
        '<div class="' + dotCls + '"></div>' +
        '<div class="dsh-sched-info"><strong>' + esc(it.title) + '</strong><small>' + esc(it.location) + '</small></div>' +
        '<span class="' + pillCls + '">' + label + '</span>' +
      '</div>';
    }).join('');
  }

  function renderBookings(rows) {
    var defaultRows = [
      { customer: 'John Phiri', event: 'Worship Concert', ticket_type: 'VIP', amount_fmt: 'MK 25,000', status: 'Paid' },
      { customer: 'Grace Malunga', event: 'Tech Summit', ticket_type: 'Standard', amount_fmt: 'MK 10,000', status: 'Paid' },
      { customer: 'Mary Moyo', event: 'Wedding Expo', ticket_type: 'VIP', amount_fmt: 'MK 30,000', status: 'Pending' },
      { customer: 'Brighton Chilemba', event: 'Youth Conference', ticket_type: 'Standard', amount_fmt: 'MK 8,000', status: 'Paid' },
      { customer: 'Emma Zulu', event: 'Worship Concert', ticket_type: 'Standard', amount_fmt: 'MK 12,000', status: 'Paid' }
    ];

    var list = (rows && rows.length) ? rows : defaultRows;

    var thead = '<tr><th>ATTENDEE</th><th>EVENT</th><th>TICKET</th><th>AMOUNT</th><th>STATUS</th></tr>';
    var tbody = list.map(function(r) {
      var isPaid = r.status === 'Paid';
      var pill = isPaid
        ? '<span class="dsh-pill green">' + ic('check', 11) + ' Paid</span>'
        : '<span class="dsh-pill amber">Pending ⓘ</span>';
      return '<tr>' +
        '<td><strong>' + esc(r.customer) + '</strong></td>' +
        '<td>' + esc(r.event) + '</td>' +
        '<td>' + esc(r.ticket_type) + '</td>' +
        '<td>' + esc(r.amount_fmt) + '</td>' +
        '<td>' + pill + '</td>' +
      '</tr>';
    }).join('');

    return '<table class="dsh-table"><thead>' + thead + '</thead><tbody>' + tbody + '</tbody></table>';
  }

  function renderWeekOverview(w) {
    var items = [
      { label: 'Revenue (YTD)', val: (w && w.revenue_ytd_fmt) || 'MK 145.2M', pct: '↑ 24%', icon: 'revenue', color: 'rgba(124,58,237,0.12)', fg: '#7c3aed' },
      { label: 'Tickets Sold (YTD)', val: (w && w.tickets_ytd ? fmt(w.tickets_ytd) : '8,452'), pct: '↑ 18%', icon: 'ticket', color: 'rgba(16,185,129,0.12)', fg: '#10b981' },
      { label: 'Events This Month', val: (w && w.events_month ? String(w.events_month) : '12'), pct: '↑ 3', icon: 'calendar', color: 'rgba(59,130,246,0.12)', fg: '#3b82f6' },
      { label: 'Attendees (YTD)', val: (w && w.attendees_ytd ? fmt(w.attendees_ytd) : '23,814'), pct: '↑ 21%', icon: 'checkin', color: 'rgba(245,158,11,0.12)', fg: '#f59e0b' },
      { label: 'Check-in Rate', val: (w && w.checkin_rate ? w.checkin_rate + '%' : '92%'), pct: '↑ 6%', icon: 'check', color: 'rgba(230,57,70,0.12)', fg: '#e63946' },
      { label: 'Avg. Rating', val: (w && w.avg_rating ? w.avg_rating + ' / 5' : '4.7 / 5'), pct: '↑ 0.3', icon: 'star', color: 'rgba(168,85,247,0.12)', fg: '#a855f7' },
    ];

    return '<div class="dsh-week-grid">' + items.map(function(it) {
      return '<div class="dsh-week-item">' +
        '<div class="dsh-week-ic" style="background:' + it.color + ';color:' + it.fg + ';">' + ic(it.icon, 15) + '</div>' +
        '<div class="dsh-week-main">' +
          '<div class="dsh-week-label">' + esc(it.label) + '</div>' +
          '<div class="dsh-week-val">' + esc(it.val) + '</div>' +
        '</div>' +
        '<div class="dsh-week-pct dsh-delta pos">' + esc(it.pct) + '</div>' +
      '</div>';
    }).join('') + '</div>';
  }

  function renderInsights(items) {
    var defaultInsights = [
      {
        icon: '🚀',
        iconBg: 'rgba(124,58,237,0.12)',
        title: 'VIP tickets nearly sold out!',
        body: 'Only 220 VIP passes left for Worship Concert 2026.',
        action: 'Increase Price / Cap',
        mod: 'tickets'
      },
      {
        icon: '📈',
        iconBg: 'rgba(16,185,129,0.12)',
        title: 'Sales velocity increased',
        body: 'Ticket sales are 34% higher than last week.',
        action: 'View Insights',
        mod: 'analytics'
      },
      {
        icon: '🅿️',
        iconBg: 'rgba(245,158,11,0.12)',
        title: 'Parking capacity near limit.',
        body: 'Expected 1,840 cars at Gate A in 40 mins. Review parking plan.',
        action: 'Review Venue Layout',
        mod: 'venues'
      }
    ];

    var list = defaultInsights;

    return list.map(function(it) {
      return '<div class="dsh-ai-card">' +
        '<div class="dsh-ai-top">' +
          '<div class="dsh-ai-ic" style="background:' + it.iconBg + ';">' + it.icon + '</div>' +
          '<div class="dsh-ai-content">' +
            '<div class="dsh-ai-title">' + esc(it.title) + '</div>' +
            '<div class="dsh-ai-body">' + esc(it.body) + '</div>' +
          '</div>' +
        '</div>' +
        '<button class="dsh-ai-btn" onclick="switchEccModule(\'' + esc(it.mod) + '\')">' + esc(it.action) + '</button>' +
      '</div>';
    }).join('');
  }

  function renderQuickActions() {
    var actions = [
      { label: 'Create Ticket Type', mod: 'tickets', icon: 'plus' },
      { label: 'Scan Ticket', mod: 'check-in', icon: 'scan' },
      { label: 'View Check-in LIVE', mod: 'check-in', icon: 'chart', badge: 'LIVE' },
      { label: 'Send Event Reminder', mod: 'messages', icon: 'send' },
      { label: 'Download Sales Report', mod: 'finance', icon: 'download' },
    ];

    return '<div class="dsh-qa-list">' + actions.map(function(a) {
      return '<button class="dsh-qa-row" onclick="switchEccModule(\'' + a.mod + '\')">' +
        '<div class="dsh-qa-ic">' + ic(a.icon, 14) + '</div>' +
        '<span>' + esc(a.label) + '</span>' +
        (a.badge ? '<span class="dsh-live-badge-tag" style="position:static;margin-left:auto;padding:0.15rem 0.5rem;font-size:0.55rem;">' + a.badge + '</span>' : '') +
      '</button>';
    }).join('') + '</div>';
  }

  /* ── main render ──────────────────────────────────────────────────── */

  function render() {
    if (!ROOT) ROOT = document.getElementById('mod-dashboard');
    if (!ROOT) return;

    var d = state.data;
    var ov = d.overview || {};
    var spark = d.spark || [];
    var live = d.live || null;
    var upcoming = d.upcoming || [];
    var sched = d.schedule || [];
    var bookings = d.bookings || [];
    var week = d.week || {};
    var insights = d.insights || [];

    var h = new Date().getHours();
    var emoji = h < 12 ? '☀️' : h < 17 ? '👋' : '🌙';

    var name = window.__eccUserName || 'Patrick';

    ROOT.innerHTML =
      '<div class="dsh-root">' +
        /* ── Main Column ── */
        '<div class="dsh-main-col">' +

          /* Header */
          '<div class="dsh-header">' +
            '<div class="dsh-header-left">' +
              '<h1 class="dsh-greeting">' + greeting() + ', <span class="dsh-greeting-name">' + esc(name) + '</span> ' + emoji + '</h1>' +
              '<p class="dsh-subtitle">Here\'s what\'s happening with your events today.</p>' +
            '</div>' +
            '<button type="button" class="dsh-customise-btn" onclick="eccNotify(\'Dashboard customization panel active!\')">' +
              ic('cog', 14) +
              '<span>Customise Dashboard</span>' +
              ic('chevron', 12) +
            '</button>' +
          '</div>' +

          /* Top KPI Cards */
          renderKpis(ov, spark) +

          /* Mid Row: Live Operations & Upcoming */
          '<div class="dsh-mid-row">' +
            renderLiveEvent(live) +
            '<div class="dsh-card">' +
              '<div class="dsh-card-head"><h3>Upcoming Events</h3><button class="dsh-card-link" onclick="switchEccModule(\'events\')">View All</button></div>' +
              '<div class="dsh-upcoming-list">' + renderUpcoming(upcoming) + '</div>' +
            '</div>' +
          '</div>' +

          /* Lower Middle Row: Schedule & Bookings */
          '<div class="dsh-bottom-row">' +
            '<div class="dsh-card">' +
              '<div class="dsh-card-head"><h3>Today\'s Schedule</h3><button class="dsh-card-link" onclick="switchEccModule(\'events\')">Full Schedule</button></div>' +
              '<div class="dsh-sched-list">' + renderSchedule(sched) + '</div>' +
            '</div>' +
            '<div class="dsh-card">' +
              '<div class="dsh-card-head"><h3>Recent Bookings</h3><button class="dsh-card-link" onclick="switchEccModule(\'attendees\')">View All</button></div>' +
              renderBookings(bookings) +
            '</div>' +
          '</div>' +

          /* Week Overview */
          '<div class="dsh-card">' +
            '<div class="dsh-card-head"><h3>This Week Overview</h3></div>' +
            renderWeekOverview(week) +
          '</div>' +

        '</div>' + /* end dsh-main-col */
      '</div>';
  }

  /* ── data fetch ───────────────────────────────────────────────────── */

  function loadAll() {
    if (state.loading) return;
    state.loading = true;

    var existingName = document.querySelector('#mod-dashboard .ecc-hero-greeting h1, .acc-user-name');
    if (existingName) {
      var raw = existingName.textContent.trim();
      var parts = raw.split(',');
      if (parts[1]) window.__eccUserName = parts[1].trim().replace(/[^a-zA-Z\s]/g,'').trim();
      else if (raw) window.__eccUserName = raw.split(' ')[0];
    }

    Promise.all([
      get('overview'),
      get('spark'),
      get('live'),
      get('upcoming', { limit: 3 }),
      get('schedule', { limit: 5 }),
      get('bookings', { limit: 6 }),
      get('week'),
      get('insights'),
    ]).then(function(results) {
      state.data = {
        overview: results[0],
        spark:    results[1].data || results[1],
        live:     results[2].data !== undefined ? results[2].data : results[2],
        upcoming: results[3].data || results[3],
        schedule: results[4].data || results[4],
        bookings: results[5].data || results[5],
        week:     results[6],
        insights: results[7].data || results[7],
      };
      if (Array.isArray(results[1])) state.data.spark = results[1];
      state.loading = false;
      state.booted  = true;
      render();
    }).catch(function(err) {
      state.loading = false;
      console.warn('[Dashboard] load error:', err);
      render();
    });
  }

  /* ── public API ───────────────────────────────────────────────────── */

  return {
    init: function() {
      ROOT = document.getElementById('mod-dashboard');
      if (!ROOT) return;
      loadAll();
    },
    refresh: function() {
      state.booted = false;
      loadAll();
    },
  };
})();
