/* Uthenga — Analytics Intelligence Console (Events V2).
 * Turns Events, Tickets, Attendees, Finance, Marketing, Customers and Check-In
 * data into decisions. Every metric is derived live from the operational tables;
 * analytics never invents numbers. Each insight deep-links back to the module
 * where the organizer can act on it.
 */
window.AnalyticsControlCenter = (function() {
  'use strict';

  var evDoc = document.getElementById('events-workspace');
  if (!evDoc) return {};
  var base = evDoc.dataset.baseUrl ? evDoc.dataset.baseUrl : '';
  var csrf = evDoc.dataset.csrf ? evDoc.dataset.csrf : '';
  var api = base + 'api/tie/vendor/events/analytics.php';

  var RANGES = [
    { id: '7d', label: 'Last 7 days' },
    { id: '30d', label: 'Last 30 days' },
    { id: '90d', label: 'Last 90 days' },
    { id: 'all', label: 'All time' }
  ];
  var REV_MODES = [
    { id: 'gross', label: 'Gross', icon: 'fas fa-money-bill-trend-up' },
    { id: 'net', label: 'Net', icon: 'fas fa-hand-holding-dollar' },
    { id: 'orders', label: 'Orders', icon: 'fas fa-receipt' },
    { id: 'aov', label: 'Average order value', icon: 'fas fa-calculator' }
  ];

  var state = {
    eventId: 'all',
    range: '30d',
    from: '',
    to: '',
    metric: 'gross',
    events: [],
    loaded: false,
    loading: false,
    data: {},
    alertCfg: null
  };

  /* ── Helpers ────────────────────────────────────────────────────── */

  function esc(s) { return window.tkEsc ? tkEsc(s) : String(s == null ? '' : s); }
  function money(n, compact) { return window.tkMoney ? tkMoney(n, compact) : ('MK ' + (Number(n) || 0).toLocaleString()); }
  function date(s) { return window.tkDate ? tkDate(s) : String(s || '—'); }
  function icon(c) { return '<i class="' + c + '"></i>'; }
  function fmt(n) {
    var v = Number(n) || 0;
    return v >= 1e6 ? (v / 1e6).toFixed(2) + 'M' : v >= 1e3 ? (v / 1e3).toFixed(1) + 'K' : String(Math.round(v));
  }
  function toast(m, err) {
    if (window.eccNotify) { window.eccNotify(m); return; }
    var el = document.createElement('div');
    el.textContent = m;
    el.style.cssText = 'position:fixed;bottom:' + (err ? '70px' : '20px') + ';right:20px;z-index:9999;background:' + (err ? '#e63946' : '#10b981') + ';color:#fff;padding:10px 16px;border-radius:10px;font:700 13px Inter,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.25)';
    document.body.appendChild(el);
    setTimeout(function() { el.remove(); }, 3200);
  }
  function num(n) { return Number(n) || 0; }

  /* ── API Layer ─────────────────────────────────────────────────── */

  function qs(obj) {
    return Object.keys(obj).filter(function(k) { return obj[k] !== '' && obj[k] != null; })
      .map(function(k) { return encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]); }).join('&');
  }
  function getJson(action, params) {
    var p = { event_id: state.eventId, range: state.range, from: state.from, to: state.to, metric: state.metric };
    for (var k in (params || {})) p[k] = params[k];
    var url = api + '?action=' + encodeURIComponent(action) + '&' + qs(p);
    return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function(r) { return r.json(); })
      .then(function(j) {
        if (!j || j.success !== true) {
          var msg = j && j.error && j.error.message ? j.error.message : 'Request failed.';
          throw new Error(msg);
        }
        return j.analytics_result !== undefined ? j.analytics_result : j;
      });
  }
  function postJson(payload) {
    var body = qs(payload);
    return fetch(api, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-CSRF-Token': csrf },
      body: body
    }).then(function(r) { return r.json(); }).then(function(j) {
      if (!j || j.success !== true) {
        var msg = j && j.error && j.error.message ? j.error.message : 'Operation failed.';
        throw new Error(msg);
      }
      return j.analytics_result !== undefined ? j.analytics_result : j;
    });
  }
  function csvExport() {
    var url = api + '?' + qs({ action: 'csv_export', event_id: state.eventId, range: state.range, from: state.from, to: state.to });
    var a = document.createElement('a');
    a.href = url; a.download = 'uthenga-analytics.csv'; a.click();
  }

  /* ── Idempotent render helpers ─────────────────────────────────── */

  function subSection(label, body, cls) {
    return '<section class="anl-block' + (cls ? ' ' + cls : '') + '">' +
      '<h3 class="anl-block-h">' + esc(label) + '</h3>' + body + '</section>';
  }

  function kpiCard(k) {
    var chg = (k.change_pct === null || isNaN(k.change_pct)) ? '' :
      '<span class="anl-chg ' + (k.change_pct >= 0 ? 'up' : 'down') + '">' +
      (k.change_pct >= 0 ? '&#9650; ' : '&#9660; ') + Math.abs(k.change_pct) + '% vs prior</span>';
    return '<div class="anl-kpi" onclick="AnalyticsControlCenter.go(\'' + (k.link || 'analytics') + '\')">' +
      '<div class="anl-kpi-top"><b>' + k.formatted + '</b>' + chg + '</div>' +
      '<span>' + esc(k.label) + '</span>' +
      (k.rate !== undefined ? '<small class="anl-kpi-rate">' + k.rate + '% attendance</small>' : '') +
      '</div>';
  }

  /* ── Charts (pure SVG, no dependencies) ────────────────────────── */

  function barChart(bars, W, H, opts) {
    opts = opts || {};
    var pad = 30, bw = Math.max(4, Math.min(26, Math.floor((W - 60) / Math.max(1, bars.length))));
    var max = Math.max.apply(null, bars.map(function(b) { return Math.max(num(b.value), 1); }));
    var maxV = opts.scaleMax || max;
    var gap = Math.max(2, Math.floor((W - 70) / Math.max(1, bars.length)) - bw);
    var out = '<svg width="100%" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" role="img">';
    out += '<line x1="' + pad + '" y1="' + (H - 28) + '" x2="' + (W - 20) + '" y2="' + (H - 28) + '" stroke="#1e2a38" stroke-opacity=".15"/>';
    bars.forEach(function(b, i) {
      var h = Math.max(2, Math.round((num(b.value) / maxV) * (H - 55)));
      var x = pad + i * (bw + gap);
      var y = H - 28 - h;
      var fill = b.color || opts.color || '#8b5cf6';
      out += '<rect x="' + x + '" y="' + y + '" width="' + bw + '" height="' + h + '" rx="3" fill="' + fill + '" fill-opacity=".85"><title>' + esc(b.label || '') + ': ' + b.value + '</title></rect>';
      if (opts.labels !== false) {
        var lblLen = (b.labels || b.label || '').length;
        out += '<text x="' + (x + bw / 2) + '" y="' + (H - 10) + '" text-anchor="' + (lblLen > 4 ? 'end' : 'middle') + '" transform="' + (lblLen > 4 ? 'rotate(-35 ' + (x + bw / 2) + ' ' + (H - 10) + ')' : '') + '" font-size="9" fill="#7b8794">' + esc(b.labels || b.label || '') + '</text>';
      }
      out += '<text x="' + (x + bw / 2) + '" y="' + (y - 5) + '" text-anchor="middle" font-size="9" font-weight="700" fill="#243244">' + esc(b.display || fmt(b.value)) + '</text>';
    });
    out += '</svg>';
    return out;
  }

  function lineChart(points, W, H, mode) {
    if (!points.length) return '<p class="anl-empty">No revenue data for this range yet.</p>';
    var pad = 44, bot = 28, top = 18;
    var maxV = Math.max.apply(null, points.map(function(p) { return num(p[mode]); })) || 1;
    var iw = W - pad - 16, ih = H - top - bot;
    var step = points.length > 1 ? iw / (points.length - 1) : 0;
    var pts = points.map(function(p, i) {
      var x = pad + i * step;
      var y = top + ih - (num(p[mode]) / maxV) * ih;
      return [x, y, p];
    });
    var path = pts.map(function(pt, i) { return (i === 0 ? 'M' : 'L') + pt[0].toFixed(1) + ' ' + pt[1].toFixed(1); }).join(' ');
    var area = path + ' L' + pts[pts.length - 1][0].toFixed(1) + ' ' + (H - bot) + ' L' + pts[0][0].toFixed(1) + ' ' + (H - bot) + ' Z';
    var out = '<svg width="100%" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" role="img">';
    out += '<line x1="' + pad + '" y1="' + (H - bot) + '" x2="' + (W - 10) + '" y2="' + (H - bot) + '" stroke="#1e2a38" stroke-opacity=".15"/>';
    var ticks = 4;
    for (var t = 0; t <= ticks; t++) {
      var gy = top + (ih / ticks) * t;
      var gv = maxV - (maxV / ticks) * t;
      out += '<line x1="' + pad + '" y1="' + gy + '" x2="' + (W - 10) + '" y2="' + gy + '" stroke="#1e2a38" stroke-opacity=".06"/>';
      out += '<text x="' + (pad - 6) + '" y="' + (gy + 3) + '" text-anchor="end" font-size="9" fill="#7b8794">' + fmt(gv) + '</text>';
    }
    out += '<path d="' + area + '" fill="#8b5cf6" fill-opacity=".10" stroke="none"/>';
    out += '<path d="' + path + '" fill="none" stroke="#8b5cf6" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>';
    pts.forEach(function(pt) {
      out += '<circle cx="' + pt[0].toFixed(1) + '" cy="' + pt[1].toFixed(1) + '" r="3" fill="#fff" stroke="#8b5cf6" stroke-width="2"><title>' + esc(pt[2].day) + ': ' + fmt(pt[2][mode]) + '</title></circle>';
    });
    out += '</svg>';
    return out;
  }

  /* ── Section builders ───────────────────────────────────────────── */

  function buildFilters() {
    var h = '<div class="anl-toolbar">';
    h += '<div class="anl-filters">';
    h += '<label class="anl-filter">' + icon('fas fa-calendar-alt') + '<select id="anl-event" onchange="AnalyticsControlCenter.setEvent(this.value)">' +
         '<option value="all">All events</option>' +
         state.events.map(function(e) { return '<option value="' + esc(e.event_id) + '"' + (state.eventId === e.event_id ? ' selected' : '') + '>' + esc(e.title) + '</option>'; }).join('') +
         '</select></label>';
    h += '<label class="anl-filter">' + icon('fas fa-clock') + '<select id="anl-range" onchange="AnalyticsControlCenter.setRange(this.value)">' +
         RANGES.map(function(r) { return '<option value="' + r.id + '"' + (state.range === r.id ? ' selected' : '') + '>' + r.label + '</option>'; }).join('') +
         '</select></label>';
    h += '<span class="anl-live"><span class="anl-live-dot"></span> Live &middot; ' + new Date().toLocaleTimeString() + '</span>';
    h += '</div>';
    h += '<div class="anl-toolbar-r">';
    h += '<button type="button" class="fin-btn fin-btn-line fin-btn-sm" onclick="AnalyticsControlCenter.csvExport()">' + icon('fas fa-file-csv') + ' CSV</button>' +
         '<button type="button" class="fin-btn fin-btn-line fin-btn-sm" onclick="AnalyticsControlCenter.refresh()">' + icon('fas fa-sync-alt') + ' Refresh</button>';
    h += '</div>';
    h += '</div>';
    return h;
  }

  function buildKpis(ov) {
    var k = ov.kpis;
    var h = '<div class="anl-kpis">';
    h += kpiCard({ label: 'Gross revenue', formatted: k.gross_revenue.formatted, change_pct: k.gross_revenue.change_pct, link: 'finance' });
    h += kpiCard({ label: 'Net revenue (after fees & refunds)', formatted: k.net_revenue.formatted, link: 'finance' });
    h += kpiCard({ label: 'Tickets sold', formatted: k.tickets_sold.formatted, change_pct: k.tickets_sold.change_pct, link: 'tickets' });
    h += kpiCard({ label: 'Attendance (checked in)', formatted: k.attendance.formatted, link: 'checkin', rate: k.attendance.rate });
    h += kpiCard({ label: 'Customers', formatted: k.customers.formatted, change_pct: k.customers.change_pct, link: 'customers' });
    h += '</div>';
    return h;
  }

  function buildFunnel(f) {
    var stages = f.stages;
    var total = Math.max.apply(null, stages.map(function(s) { return s.value; })) || 1;
    var h = '<div class="anl-funnel">';
    stages.forEach(function(s, i) {
      var pct = Math.max(4, Math.round((s.value / total) * 100));
      h += '<div class="anl-funnel-row">' +
        '<div class="anl-funnel-meta"><span class="anl-funnel-step">' + (i + 1) + '</span><b>' + esc(s.label) + '</b><em>' + s.formatted + '</em></div>' +
        '<div class="anl-funnel-bar"><div class="anl-funnel-fill" style="width:' + pct + '%"></div></div></div>';
    });
    h += '</div>';
    h += '<div class="anl-funnel-conv">' +
      '<span>Views → selection <b>' + f.conversion.views_to_selection + '%</b></span>' +
      '<span>Selection → checkout <b>' + f.conversion.selection_to_checkout + '%</b></span>' +
      '<span>Checkout → purchase <b>' + f.conversion.checkout_to_purchased + '%</b></span>' +
      '<span>Overall <b>' + f.conversion.overall + '%</b></span></div>';
    if (stages[0].value === 0 && stages[2].value > 0) {
      h += '<p class="anl-hint">' + icon('fas fa-info-circle') + ' View tracking has not been captured for these events yet, so the top of the funnel is shown as 0 — checkouts and purchases below come from your real booking records.</p>';
    }
    return h;
  }

  function buildRevenue(rv) {
    var h = '<div class="anl-rev-tool">' + REV_MODES.map(function(m) {
      return '<button type="button" class="anl-rev-mode' + (state.metric === m.id ? ' active' : '') + '" onclick="AnalyticsControlCenter.setMetric(\'' + m.id + '\')">' + icon(m.icon) + ' ' + esc(m.label) + '</button>';
    }).join('') + '</div>';
    h += '<div class="anl-chart">' + lineChart(rv.points, 900, 240, state.metric) + '</div>';
    return h;
  }

  function buildVelocity(v) {
    var h = '<div class="anl-vel-top">';
    if (v.acceleration_pct !== null && v.acceleration_pct !== undefined && !isNaN(v.acceleration_pct)) {
      var up = v.acceleration_pct >= 0;
      h += '<span class="anl-vel-accel ' + (up ? 'up' : 'down') + '">' + (up ? '&#9650;' : '&#9660;') + ' ' + Math.abs(v.acceleration_pct) + '% momentum in last 3h</span>';
    } else {
      h += '<span class="anl-vel-accel neutral">Flat — sales are steady</span>';
    }
    h += '</div>';
    var bars = v.hours.map(function(hh) { return { label: hh.hour, labels: hh.label.slice(0, 2) + 'h', value: hh.orders, display: hh.orders > 0 ? String(hh.orders) : '' }; });
    h += '<div class="anl-chart">' + barChart(bars, 900, 180, { labels: true, color: '#6366f1' }) + '</div>';
    return h;
  }

  function buildTickets(t) {
    if (!t.rows.length) return '<p class="anl-empty">No ticket types for the selected scope.</p>';
    var h = '<div class="anl-ticket-summary">' +
      '<span><b>' + t.sold.toLocaleString() + '</b> sold</span>' +
      '<span><b>' + t.capacity.toLocaleString() + '</b> capacity</span>' +
      '<span><b>' + t.sell_through + '%</b> sell-through</span></div>';
    h += '<div class="anl-table-wrap"><table class="anl-table"><thead><tr>' +
      '<th>Ticket type</th><th>Price</th><th>Sold</th><th>Remaining</th><th>Sell-through</th><th>Demand</th><th>Revenue</th></tr></thead><tbody>';
    t.rows.forEach(function(r) {
      var barW = Math.max(2, Math.min(100, r.sell_through));
      h += '<tr class="' + (r.near_sold_out ? 'soldout' : '') + '">' +
        '<td><b>' + esc(r.name) + '</b><br><small>' + esc(r.tier || r.category || '') + '</small></td>' +
        '<td>' + money(r.price) + '</td>' +
        '<td><b>' + r.sold.toLocaleString() + '</b></td>' +
        '<td>' + r.remaining.toLocaleString() + '</td>' +
        '<td><div class="anl-heat"><div class="anl-heat-fill" style="width:' + barW + '%"></div></div><span class="anl-heat-txt">' + r.sell_through + '%</span>' +
        (r.near_sold_out ? ' <span class="anl-tag warn">sold out</span>' : '') + '</td>' +
        '<td>' + money(r.revenue) + '</td></tr>';
    });
    h += '</tbody></table></div>';
    return h;
  }

  function buildAttendance(a) {
    var h = '<div class="anl-att-grid">' +
      '<div class="anl-att-cell"><b>' + a.sold.toLocaleString() + '</b><span>Valid tickets</span></div>' +
      '<div class="anl-att-cell"><b>' + a.checked_in.toLocaleString() + '</b><span>Checked in</span></div>' +
      '<div class="anl-att-cell"><b>' + a.no_show.toLocaleString() + '</b><span>No-shows</span></div>' +
      '<div class="anl-att-cell"><b>' + a.attendance_rate + '%</b><span>Attendance rate</span></div>' +
      '</div>';
    h += '<div class="anl-gauge"><div class="anl-gauge-fill" style="width:' + Math.min(100, a.attendance_rate) + '%"></div></div>';
    if (a.over_time.length) {
      var overBars = a.over_time.map(function(o, i) {
        return { label: i, labels: o.slot.slice(11, 13) + 'h', value: o.checked_in, display: o.checked_in > 0 ? String(o.checked_in) : '' };
      });
      h += '<div class="anl-chart">' + barChart(overBars, 800, 150, { labels: true, color: '#0ea5e9' }) + '</div>';
    }
    return h;
  }

  function buildCheckins(c) {
    var h = '<div class="anl-check-grid">' +
      '<div class="anl-check-cell allow"><b>' + (c.summary && c.summary.ALLOW || 0) + '</b><span>Allowed</span></div>' +
      '<div class="anl-check-cell deny"><b>' + (c.summary && c.summary.DENY || 0) + '</b><span>Denied</span></div>' +
      '<div class="anl-check-cell review"><b>' + (c.summary && c.summary.REVIEW || 0) + '</b><span>Needing review</span></div>' +
      '<div class="anl-check-cell total"><b>' + c.total + '</b><span>Total scans</span></div>' +
      '</div>';
    if (!c.gates.length) {
      h += '<p class="anl-hint">' + icon('fas fa-info-circle') + ' No gate scans have been recorded for the selected scope yet.</p>';
      return h;
    }
    h += '<div class="anl-table-wrap"><table class="anl-table"><thead><tr><th>Gate</th><th>Allowed</th><th>Denied</th><th>Review</th><th>Total</th></tr></thead><tbody>';
    c.gates.forEach(function(g) {
      h += '<tr><td><b>' + esc(g.gate) + '</b></td><td>' + g.ALLOW + '</td><td>' + g.DENY + '</td><td>' + g.REVIEW + '</td><td><b>' + g.total + '</b></td></tr>';
    });
    h += '</tbody></table></div>';
    return h;
  }

  function buildCustomers(c) {
    var h = '<div class="anl-cust-grid">' +
      '<div class="anl-cust-cell"><b>' + c.total + '</b><span>Customers</span></div>' +
      '<div class="anl-cust-cell"><b>' + c.new + '</b><span>New</span></div>' +
      '<div class="anl-cust-cell"><b>' + c.returning + '</b><span>Returning</span></div>' +
      '<div class="anl-cust-cell"><b>' + c.repeat_rate + '%</b><span>Repeat rate</span></div>' +
      '<div class="anl-cust-cell"><b>' + money(c.avg_spend) + '</b><span>Avg spend</span></div>' +
      '</div>';
    var tiers = [
      { label: 'High (£200k+)', v: c.tiers.high, c: '#10b981' },
      { label: 'Mid (£50k–200k)', v: c.tiers.mid, c: '#f59e0b' },
      { label: 'Low (< £50k)', v: c.tiers.low, c: '#6366f1' }
    ];
    var tv = Math.max.apply(null, tiers.map(function(t) { return t.v; })) || 1;
    h += '<div class="anl-tier">';
    tiers.forEach(function(t) {
      h += '<div class="anl-tier-row"><span>' + t.label + '</span><div class="anl-tier-bar"><div class="anl-tier-fill" style="width:' + (100 * t.v / tv) + '%;background:' + t.c + '"></div></div><b>' + t.v + '</b></div>';
    });
    h += '</div>';
    if (c.top && c.top.length) {
      h += '<div class="anl-table-wrap"><table class="anl-table"><thead><tr><th>Customer</th><th>Orders</th><th>Spent</th><th>Last booking</th></tr></thead><tbody>';
      c.top.forEach(function(r) {
        h += '<tr><td><b>' + esc(r.email) + '</b></td><td>' + r.orders + '</td><td>' + money(r.spent) + '</td><td>' + date(r.last_booking) + '</td></tr>';
      });
      h += '</tbody></table></div>';
    }
    return h;
  }

  function buildMarketing(m) {
    var h = '<div class="anl-mkt-grid">' +
      '<div class="anl-mkt-cell"><b>' + (m.total_reach >= 1000 ? (m.total_reach / 1000).toFixed(1) + 'K' : m.total_reach) + '</b><span>Campaign reach</span></div>' +
      '<div class="anl-mkt-cell"><b>' + m.total_clicks.toLocaleString() + '</b><span>Clicks</span></div>' +
      '<div class="anl-mkt-cell"><b>' + m.total_sales.toLocaleString() + '</b><span>Sales</span></div>' +
      '<div class="anl-mkt-cell"><b>' + money(m.total_revenue_attributed) + '</b><span>Attributed revenue</span></div>' +
      '<div class="anl-mkt-cell"><b>' + m.click_through + '%</b><span>Click-through</span></div>' +
      '</div>';
    if (!m.campaigns.length) {
      h += '<p class="anl-hint">' + icon('fas fa-info-circle') + ' No campaigns recorded for the selected scope. ' + esc(m.attribution_note) + '</p>';
      return h;
    }
    h += '<div class="anl-table-wrap"><table class="anl-table"><thead><tr><th>Campaign</th><th>Channel</th><th>Status</th><th>Reach</th><th>Clicks</th><th>Sales</th><th>Revenue</th><th>Conv.</th></tr></thead><tbody>';
    m.campaigns.forEach(function(cmp) {
      h += '<tr><td><b>' + esc(cmp.title) + '</b><br><small>' + esc(cmp.listing_title || '') + '</small></td>' +
        '<td>' + esc(cmp.channel) + '</td>' +
        '<td><span class="anl-pill ' + (cmp.status === 'active' ? 'green' : (cmp.status === 'paused' ? 'amber' : 'gray')) + '">' + esc(cmp.status) + '</span></td>' +
        '<td>' + (cmp.reach >= 1000 ? (cmp.reach / 1000).toFixed(1) + 'K' : cmp.reach) + '</td>' +
        '<td>' + cmp.clicks.toLocaleString() + '</td>' +
        '<td>' + cmp.sales.toLocaleString() + '</td>' +
        '<td>' + money(cmp.revenue) + '</td>' +
        '<td>' + (cmp.conversion !== null ? cmp.conversion + '%' : '—') + '</td></tr>';
    });
    h += '</tbody></table></div>';
    h += '<p class="anl-hint">' + esc(m.attribution_note) + '</p>';
    return h;
  }

  function buildComparison(cmp) {
    if (!cmp.length) return '<p class="anl-empty">No events to compare.</p>';
    var h = '<div class="anl-table-wrap"><table class="anl-table"><thead><tr><th>Event</th><th>Status</th><th>Orders</th><th>Revenue</th><th>Sold</th><th>Checked in</th><th>Attendance</th></tr></thead><tbody>';
    cmp.forEach(function(e) {
      h += '<tr onclick="AnalyticsControlCenter.go(\'events\')"><td><b>' + esc(e.title) + '</b></td>' +
        '<td><span class="anl-pill ' + ((e.status || '').toLowerCase() === 'published' ? 'green' : 'gray') + '">' + esc(e.status) + '</span></td>' +
        '<td>' + e.orders + '</td><td>' + money(e.revenue) + '</td>' +
        '<td>' + e.sold.toLocaleString() + '</td><td>' + e.checked_in.toLocaleString() + '</td>' +
        '<td><span class="anl-heat"><span class="anl-heat-fill" style="width:' + Math.min(100, e.attendance_rate) + '%"></span></span> ' + e.attendance_rate + '%</td></tr>';
    });
    h += '</tbody></table></div>';
    return h;
  }

  function buildHealth(h) {
    var ring = Math.round(h.score);
    var off = 2 * Math.PI * 42 * (1 - h.score / 100);
    var svg = '<svg width="96" height="96" viewBox="0 0 96 96">' +
      '<circle cx="48" cy="48" r="42" fill="none" stroke="#e8ecf1" stroke-width="9"/>' +
      '<circle cx="48" cy="48" r="42" fill="none" stroke="' + (h.band === 'good' ? '#10b981' : h.band === 'medium' ? '#f59e0b' : '#e63946') + '" stroke-width="9" stroke-linecap="round" stroke-dasharray="' + (2 * Math.PI * 42 - off) + ' ' + (2 * Math.PI * 42) + '" transform="rotate(-90 48 48)"/>' +
      '<text x="48" y="53" text-anchor="middle" font-size="22" font-weight="800" fill="#1e2a38">' + ring + '</text>' +
      '</svg>';
    var hh = '<div class="anl-health">' +
      '<div class="anl-health-ring">' + svg + '</div>' +
      '<div class="anl-health-info"><h4>' + esc(h.label) + '</h4>' +
      '<div class="anl-health-reasons">' +
      h.reasons.map(function(r) { return '<div class="anl-reason ' + r.kind + '">' + icon('fas fa-' + (r.kind === 'positive' ? 'check-circle' : r.kind === 'neutral' ? 'info-circle' : 'exclamation-circle')) + '<span>' + esc(r.text) + '</span></div>'; }).join('') +
      '</div></div></div>';
    return hh;
  }

  function buildForecast(f) {
    var bandW = Math.max(8, Math.round(100 * (f.projected_revenue - f.confidence_low) / Math.max(f.confidence_high, 1)));
    var h = '<div class="anl-forecast">' +
      '<div class="anl-forecast-main">' +
      '<div class="anl-forecast-num"><small>Projected revenue (' + f.horizon_days + ' day' + (f.horizon_days === 1 ? '' : 's') + ')</small>' +
      '<b>' + money(f.projected_revenue) + '</b>' +
      '<span class="anl-forecast-range">Range ' + money(f.confidence_low) + ' – ' + money(f.confidence_high) + ' <em class="anl-forecast-level ' + f.confidence_level + '">' + f.confidence_level + ' confidence</em></span></div>' +
      '<div class="anl-forecast-now"><small>Now</small><b>' + money(f.current) + '</b></div>' +
      '</div>' +
      '<div class="anl-forecast-track"><div class="anl-forecast-fill" style="left:0;width:' + Math.min(100, bandW) + '%"></div></div>' +
      '<div class="anl-forecast-scale"><span>' + money(f.current) + '</span><span>' + money(f.confidence_high) + '</span></div>' +
      '<p class="anl-hint">' + icon('fas fa-info-circle') + ' ' + esc(f.basis) + '</p></div>';
    return h;
  }

  function buildInsights(ins) {
    if (!ins.length) return '<p class="anl-empty">No actionable signals yet — check back as more sales data arrives.</p>';
    var h = '<div class="anl-insights">';
    ins.forEach(function(i) {
      h += '<div class="anl-insight ' + i.tone + '"><div class="anl-insight-ico">' + icon('fas fa-' + (i.icon === 'revenue' ? 'money-bill-trend-up' : i.icon === 'inventory' ? 'box-open' : i.icon === 'customers' ? 'users' : i.icon === 'funnel' ? 'filter' : i.icon === 'health' ? 'heart-pulse' : 'wrench')) + '</div>' +
        '<div class="anl-insight-bd"><h5>' + esc(i.title) + '</h5><p>' + esc(i.text) + '</p></div>' +
        '<button type="button" class="fin-btn fin-btn-primary fin-btn-sm" onclick="AnalyticsControlCenter.go(\'' + i.link + '\')">' + esc(i.action) + '</button></div>';
    });
    h += '</div>';
    return h;
  }

  function buildAlerts(al) {
    var h = '<div class="anl-alerts">' +
      '<div class="anl-alerts-settings">' +
      '<div class="anl-as-head"><h5>' + icon('fas fa-bell') + ' Alert preferences</h5><span>' + esc(al.config.notify_sales ? 'Notifications on' : 'Notifications off') + '</span></div>' +
      '<button type="button" class="fin-btn fin-btn-line fin-btn-sm" onclick="AnalyticsControlCenter.openAlerts()">' + icon('fas fa-sliders-h') + ' Configure</button>' +
      '</div>';
    if (!al.alerts.length) {
      h += '<p class="anl-hint">' + icon('fas fa-check-circle') + ' No alerts are currently firing. Thresholds are defined in your alert settings.</p>';
    } else {
      h += '<div class="anl-alert-list">';
      al.alerts.forEach(function(a) {
        h += '<div class="anl-alert ' + a.level + '">' + icon('fas fa-' + (a.icon === 'sales' ? 'chart-line' : a.icon === 'inventory' ? 'box-open' : a.icon === 'checkin' ? 'user-check' : a.icon === 'pace' ? 'gauge-high' : a.icon === 'revenue' ? 'money-bill-trend-up' : 'users')) +
          '<div class="anl-alert-bd"><b>' + esc(a.title) + '</b><p>' + esc(a.text) + '</p></div>' +
          '<button type="button" class="fin-btn fin-btn-line fin-btn-sm" onclick="AnalyticsControlCenter.go(\'' + a.module + '\')">Open</button></div>';
      });
      h += '</div>';
    }
    return h;
  }

  function buildAsk() {
    var h = '<div class="anl-ask">' +
      '<div class="anl-ask-input"><input id="anl-ask-q" type="text" placeholder="Ask about your events — e.g. \'Which tickets are closest to selling out?\'" onkeydown="if(event.key===\'Enter\')AnalyticsControlCenter.ask()"/>' +
      '<button type="button" class="fin-btn fin-btn-primary" onclick="AnalyticsControlCenter.ask()">' + icon('fas fa-robot') + ' Ask</button></div>' +
      '<div class="anl-ask-chips">' +
      ['How much gross revenue have I earned?', 'Which tickets are closest to selling out?', 'What is my attendance rate?', 'Forecast my revenue', 'Who are my top customers?', 'How is my funnel converting?'].map(function(c) {
        return '<button type="button" class="anl-chip" onclick="AnalyticsControlCenter.suggest(\'' + c.replace(/'/g, "\\'") + '\')">' + esc(c) + '</button>';
      }).join('') + '</div>' +
      '<div class="anl-ask-answer" id="anl-ask-answer" style="display:none"></div></div>';
    return h;
  }

  /* ── Assembling the workspace ──────────────────────────────────── */

  function renderAll() {
    var root = document.getElementById('anl-root');
    if (!root) return;
    if (state.loading) {
      root.innerHTML = '<div class="anl-loading">' + icon('fas fa-spinner') + ' Crunching your event intelligence…</div>';
      return;
    }
    if (!state.loaded) return;
    var d = state.data;

    var h = '<div class="anl-head">' +
      '<div class="anl-head-l"><h2>Analytics & Conversion Funnel</h2>' +
      '<p>Live insight into demand, revenue, attendance and customer behaviour — every number derived from your operational records.</p></div>' +
      '<div class="anl-head-r"><span class="anl-head-badge">' + icon('fas fa-chart-line') + ' Real-time intelligence</span></div>' +
      '</div>';
    h += buildFilters();

    h += '<div class="anl-grid">';

    // KPI cards
    h += '<div class="anl-kpi-row">' + buildKpis(d.overview) + '</div>';

    // Conversion funnel + revenue
    h += '<div class="anl-cols-3">' +
      '<div class="anl-bulk">' + subSection('Event conversion funnel', buildFunnel(d.funnel)) + '</div>' +
      '<div class="anl-bulk2">' + subSection('Revenue performance', buildRevenue(d.revenue)) + '</div>' +
      '</div>';

    // Velocity + tickets
    h += '<div class="anl-cols-2">' +
      '<div>' + subSection('Sales velocity (last 24h)', buildVelocity(d.velocity)) + '</div>' +
      '<div>' + subSection('Ticket performance & demand', buildTickets(d.tickets)) + '</div>' +
      '</div>';

    // Attendance + check-ins
    h += '<div class="anl-cols-2">' +
      '<div>' + subSection('Attendance & check-in analytics', buildAttendance(d.attendance)) + '</div>' +
      '<div>' + subSection('Check-in performance by gate', buildCheckins(d.checkins)) + '</div>' +
      '</div>';

    // Customers + marketing
    h += '<div class="anl-cols-2">' +
      '<div>' + subSection('Customer insights', buildCustomers(d.customers)) + '</div>' +
      '<div>' + subSection('Marketing attribution', buildMarketing(d.marketing)) + '</div>' +
      '</div>';

    // Health + forecast
    h += '<div class="anl-cols-2">' +
      '<div>' + subSection('Event health score', buildHealth(d.health)) + '</div>' +
      '<div>' + subSection('Revenue forecast', buildForecast(d.forecast)) + '</div>' +
      '</div>';

    // Comparison
    h += '<div>' + subSection('Event comparison', buildComparison(d.comparison)) + '</div>';

    // AI panel + alerts
    h += '<div class="anl-cols-2">' +
      '<div>' + subSection('AI insights' + ' <span class="anl-tag ai">auto-generated</span>', buildInsights(d.insights)) + '</div>' +
      '<div>' + subSection('Alerts & thresholds', buildAlerts(d.alerts)) + '</div>' +
      '</div>';

    // Ask
    h += '<div>' + subSection('Ask analytics', buildAsk()) + '</div>';

    h += '</div>';

    h += '<div class="anl-modal-overlay" id="anl-modal-overlay" onclick="if(event.target===this)AnalyticsControlCenter.modalClose()">' +
      '<div class="anl-modal"><div class="anl-modal-hd"><h3 id="anl-modal-title">Alert settings</h3><button type="button" class="fin-close" onclick="AnalyticsControlCenter.modalClose()" title="Close">' + icon('fas fa-times') + '</button></div>' +
      '<div class="anl-modal-bd" id="anl-modal-bd"></div>' +
      '<div class="anl-modal-ft" id="anl-modal-ft"></div></div></div>';

    root.innerHTML = h;
    root.dataset.built = '1';
  }

  function load() {
    if (state.loading) return;
    state.loading = true;
    renderAll();
    var jobs = {
      overview: getJson('overview'),
      funnel: getJson('funnel'),
      revenue: getJson('revenue'),
      velocity: getJson('velocity'),
      tickets: getJson('tickets'),
      attendance: getJson('attendance'),
      checkins: getJson('checkins'),
      customers: getJson('customers'),
      marketing: getJson('marketing'),
      comparison: getJson('comparison'),
      health: getJson('health'),
      forecast: getJson('forecast'),
      insights: getJson('insights'),
      alerts: getJson('alerts')
    };
    if (!state.alertCfg) jobs.alert_config = getJson('alert_config');
    Promise.all(Object.keys(jobs).map(function(k) {
      return jobs[k].then(function(v) { state.data[k] = v; }).catch(function(e) { state.data[k] = null; });
    })).then(function() {
      state.loading = false;
      state.loaded = true;
      if (state.data.alerts) state.data.alerts.config = state.data.alert_config || state.data.alerts.config;
      renderAll();
    });
  }

  function refresh() {
    state.loaded = false;
    load();
  }

  /* ── User actions ──────────────────────────────────────────────── */

  function setEvent(id) { state.eventId = id; refresh(); }
  function setRange(r) { state.range = r; refresh(); }
  function setMetric(m) { state.metric = m; renderAll(); }

  function go(module) {
    if (window.switchEccModule) window.switchEccModule({ checkin: 'check-in' }[module] || module);
  }

  function ask() {
    var el = document.getElementById('anl-ask-q');
    var q = el ? el.value.trim() : '';
    if (!q) { toast('Type a question about your events.', true); return; }
    var ans = document.getElementById('anl-ask-answer');
    if (ans) { ans.style.display = 'block'; ans.innerHTML = icon('fas fa-spinner') + ' Thinking…'; }
    getJson('ask', { q: q }).then(function(res) {
      if (ans) {
        ans.style.display = 'block';
        ans.innerHTML = '<div class="anl-ask-q">' + esc(q) + '</div><div class="anl-ask-a">' + icon('fas fa-robot') + ' <span>' + esc(res.answer) + '</span></div>';
      }
    }).catch(function(e) {
      if (ans) { ans.style.display = 'block'; ans.innerHTML = '<div class="anl-ask-q">' + esc(q) + '</div><div class="anl-ask-a err">' + icon('fas fa-triangle-exclamation') + ' ' + esc(e.message) + '</div>'; }
    });
  }
  function suggest(q) {
    var el = document.getElementById('anl-ask-q');
    if (el) el.value = q;
    ask();
  }

  function openAlerts() {
    var cfg = state.data.alert_config || state.data.alerts.config || {};
    var ls = function(k, label) {
      return '<label class="anl-alert-cfg-row"><span>' + label + '</span><input type="checkbox" id="anl-alert-' + k + '" ' + (cfg['notify_' + k] ? 'checked' : '') + '/></label>';
    };
    var bd = '<div class="anl-alert-fields">' +
      '<label class="anl-alert-cfg-row"><span>Sales target (tickets)</span><input type="number" id="anl-alert-sales_target" value="' + (cfg.sales_target || 0) + '"/></label>' +
      '<label class="anl-alert-cfg-row"><span>Attendance target (%)</span><input type="number" step="any" id="anl-alert-attendance_rate" value="' + (cfg.attendance_rate || 0) + '"/></label>' +
      '</div>' +
      '<div class="anl-alert-toggles">' +
      ls('sales', 'Sales target reached') + ls('velocity', 'Sales accelerating') + ls('inventory', 'Inventory critical') +
      ls('attendance', 'Attendance below target') + ls('revenue', 'Net revenue negative') + ls('customers', 'Low repeat rate') +
      '</div>';
    openModal('Alert settings', bd, '<button type="button" class="fin-btn fin-btn-primary" onclick="AnalyticsControlCenter.saveAlerts()">Save alerts</button>');
  }

  function saveAlerts() {
    var flag = function(name) {
      var el = document.getElementById('anl-alert-' + name);
      return el ? (el.checked ? 1 : 0) : 0;
    };
    var payload = {
      action: 'save_alert_config',
      sales_target: Number(document.getElementById('anl-alert-sales_target').value || 0),
      attendance_rate: Number(document.getElementById('anl-alert-attendance_rate').value || 0),
      notify_sales: flag('sales'),
      notify_velocity: flag('velocity'),
      notify_inventory: flag('inventory'),
      notify_attendance: flag('attendance'),
      notify_revenue: flag('revenue'),
      notify_customers: flag('customers')
    };
    postJson(payload).then(function(res) {
      state.data.alert_config = res;
      state.data.alerts.config = res;
      modalClose();
      toast('Alert settings saved.');
      refresh();
    }).catch(function(e) { toast(e.message, true); });
  }

  function modalClose() {
    var m = document.getElementById('anl-modal-overlay');
    if (m) { m.classList.remove('active'); setTimeout(function() { m.style.display = 'none'; }, 200); }
  }
  function openModal(title, bd, ft) {
    var m = document.getElementById('anl-modal-overlay');
    if (!m) return;
    m.style.display = 'flex';
    void m.offsetWidth;
    m.classList.add('active');
    document.getElementById('anl-modal-title').textContent = title;
    document.getElementById('anl-modal-bd').innerHTML = bd;
    document.getElementById('anl-modal-ft').innerHTML = ft || '';
  }

  /* ── Boot ──────────────────────────────────────────────────────── */

  function init() {
    buildShell();
    loadEvents();
  }

  function buildShell() {
    var root = document.getElementById('anl-root');
    if (!root || root.dataset.built) return;
    root.innerHTML = '<div class="anl-loading">' + icon('fas fa-spinner') + ' Loading analytics workspace…</div>';
  }

  function loadEvents() {
    getJson('events').then(function(events) {
      state.events = events || [];
      state.alert_cfg = state.alert_cfg || null;
      return getJson('alert_config').then(function(cfg) { state.alertCfg = cfg; });
    }).catch(function() {}).then(function() {
      load();
    });
  }

  return {
    init: init,
    refresh: refresh,
    setEvent: setEvent,
    setRange: setRange,
    setMetric: setMetric,
    go: go,
    ask: ask,
    suggest: suggest,
    openAlerts: openAlerts,
    saveAlerts: saveAlerts,
    modalClose: modalClose,
    csvExport: csvExport
  };
})();

/* Auto-engage when the user opens the Analytics tab. */
(function() {
  var prev = window.onEccModuleShow;
  window.onEccModuleShow = function(modId) {
    if (typeof prev === 'function') { try { prev(modId); } catch (e) {} }
    if (modId === 'analytics' && window.AnalyticsControlCenter) {
      window.AnalyticsControlCenter.init();
    }
  };
})();