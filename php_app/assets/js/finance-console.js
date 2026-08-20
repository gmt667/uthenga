/* Uthenga — Finance Console controller (Events V2).
 * Renders the organizer's revenue, transactions, settlements, refunds, fees,
 * invoices and reconciliation workspace backed by the read/compute finance
 * engine at api/tie/vendor/events/finance.php, plus a read-only AI advisor.
 * Icons are inline SVG — no emoji.
 */
window.FinanceControlCenter = (function() {
  'use strict';

  var evDoc = document.getElementById('events-workspace');
  if (!evDoc) return {};
  var base = evDoc.dataset.baseUrl ? evDoc.dataset.baseUrl : '';
  var csrf = evDoc.dataset.csrf ? evDoc.dataset.csrf : '';
  var api = base + 'api/tie/vendor/events/finance.php';

  var TABS = [
    { id: 'overview', label: 'Overview' },
    { id: 'transactions', label: 'Transactions' },
    { id: 'revenue', label: 'Revenue' },
    { id: 'settlements', label: 'Settlements' },
    { id: 'refunds', label: 'Refunds' },
    { id: 'fees', label: 'Fees' },
    { id: 'documents', label: 'Documents' },
    { id: 'reconciliation', label: 'Reconciliation' }
  ];
  var METHODS = ['Airtel Money', 'TNM Mpamba', 'Bank Card', 'Uthenga Pay'];
  var STATUSES = ['Paid', 'Pending', 'Failed', 'Cancelled', 'Refunded'];

  var state = {
    tab: 'overview',
    tx: { offset: 0, limit: 25, filters: { q: '', event: '', status: '', method: '', from: '', to: '' } },
    revenue: { range: '30d', from: '', to: '' },
    events: [],
    loaded: {}
  };

  /* ── helpers ────────────────────────────────────────────────── */

  function esc(s) {
    return window.tkEsc ? tkEsc(s) : String(s == null ? '' : s);
  }
  function money(n, compact) {
    return window.tkMoney ? tkMoney(n, compact) : ('MK ' + (Number(n) || 0).toLocaleString());
  }
  function date(s) {
    return window.tkDate ? tkDate(s) : String(s || '—');
  }
  function dateTime(s) {
    return window.tkDateTime ? tkDateTime(s) : String(s || '—');
  }
  function toast(m, err) {
    if (window.eccNotify) { window.eccNotify(m); return; }
    var el = document.createElement('div');
    el.textContent = m;
    el.style.cssText = 'position:fixed;bottom:' + (err ? '70px' : '20px') + ';right:20px;z-index:9999;background:' + (err ? '#e63946' : '#10b981') + ';color:#fff;padding:10px 16px;border-radius:10px;font:700 13px Inter,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.25)';
    document.body.appendChild(el);
    setTimeout(function() { el.remove(); }, 3200);
  }
  function pct(n) { return (Number(n) || 0).toFixed(1) + '%'; }
  function fmt(n) {
    var v = Number(n) || 0;
    return v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : v >= 1000 ? (v / 1000).toFixed(1) + 'K' : String(Math.round(v));
  }

  var ICON_PATHS = {
    grid: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
    wallet: '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
    chart: '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
    settle: '<path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
    rotate: '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
    percent: '<line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
    doc: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
    check: '<path d="M20 6L9 17l-5-5"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
    search: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    refresh: '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
    x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    warn: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    bank: '<path d="M3 21h18"/><path d="M4 17h16"/><path d="M12 7l9-4-9-4-9 4 9 4z"/><path d="M4 17v-6"/><path d="M20 17v-6"/>',
    phone: '<rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
    spark: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    send: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
    eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    arrow: '<polyline points="9 18 15 12 9 6"/>',
    banknote: '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>'
  };
  function icon(name, size) {
    var p = ICON_PATHS[name] || '';
    var s = size || 16;
    return '<svg viewBox="0 0 24 24" width="' + s + '" height="' + s + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-0.14em">' + p + '</svg>';
  }

  /* ── api ─────────────────────────────────────────────────────── */

  function qs(obj) {
    return Object.keys(obj).filter(function(k) { return obj[k] !== '' && obj[k] != null; })
      .map(function(k) { return encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]); }).join('&');
  }
  function getJson(action, params) {
    var url = api + '?action=' + encodeURIComponent(action) + (params ? '&' + qs(params) : '');
    return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function(r) { return r.json(); })
      .then(function(j) {
        if (!j || j.success !== true) {
          var msg = j && j.error && j.error.message ? j.error.message : 'Request failed.';
          throw new Error(msg);
        }
        return j.finance_result !== undefined ? j.finance_result : j;
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
      return j.finance_result !== undefined ? j.finance_result : j;
    });
  }

  /* ── shell ───────────────────────────────────────────────────── */

  function build() {
    var root = document.getElementById('fin-root');
    if (!root || root.dataset.built) return;
    root.dataset.built = '1';

    var h = '<div class="fin-head">';
    h += '<div class="fin-head-l"><h2>Finance console</h2><p>Revenue, settlements, fees and reconciliation for your events — reconciled against live transactions.</p></div>';
    h += '<div class="fin-head-r"><span class="fin-head-badge">' + icon('shield', 13) + 'Realtime</span>' +
      '<button type="button" class="fin-btn fin-btn-line fin-btn-sm" onclick="FinanceControlCenter.refreshAll()">' + icon('refresh', 13) + 'Refresh</button></div>';
    h += '</div>';

    h += '<div class="fin-toolbar">';
    h += '<div class="fin-subnav" role="tablist">';
    TABS.forEach(function(t) {
      h += '<button type="button" data-fin-tab="' + t.id + '" class="' + (t.id === 'overview' ? 'active' : '') + '" onclick="FinanceControlCenter.loadTab(\'' + t.id + '\')">' + esc(t.label) + '</button>';
    });
    h += '</div>';
    h += '<button type="button" class="fin-ai-toggle" onclick="FinanceControlCenter.advisorToggle()">' + icon('spark', 14) + 'Finance AI</button>';
    h += '</div>';
    TABS.forEach(function(t) {
      h += '<div class="fin-pane' + (t.id === 'overview' ? ' active' : '') + '" id="fin-pane-' + t.id + '"><div id="fin-body-' + t.id + '" class="fin-loading">' + icon('refresh', 15) + 'Loading…</div></div>';
    });
    h += '<div class="fin-drawer" id="fin-drawer">' +
      '<div class="fin-drawer-hd"><h3 id="fin-drawer-title">Transaction</h3><button type="button" class="fin-close" onclick="FinanceControlCenter.drawerClose()" title="Close">' + icon('x', 17) + '</button></div>' +
      '<div class="fin-drawer-bd" id="fin-drawer-bd"></div></div>';
    h += '<div class="fin-modal-overlay" id="fin-modal-overlay" onclick="if(event.target===this)FinanceControlCenter.modalClose()">' +
      '<div class="fin-modal"><div class="fin-modal-hd"><h3 id="fin-modal-title">Modal</h3><button type="button" class="fin-close" onclick="FinanceControlCenter.modalClose()" title="Close">' + icon('x', 17) + '</button></div>' +
      '<div class="fin-modal-bd" id="fin-modal-bd"></div>' +
      '<div class="fin-modal-ft" id="fin-modal-ft"></div></div></div>';
    h += '<div class="fin-adv" id="fin-adv">' +
      '<div class="fin-adv-hd">' + icon('spark', 17) + '<div><b>Finance AI</b><br><small>Read-only advisor · explains, never edits</small></div><button type="button" class="fin-close" onclick="FinanceControlCenter.advisorToggle()">' + icon('x', 17) + '</button></div>' +
      '<div class="fin-adv-body" id="fin-adv-body"></div>' +
      '<div class="fin-adv-actions" id="fin-adv-actions"></div>' +
      '<div class="fin-adv-follow" id="fin-adv-follow"></div>' +
      '<div class="fin-adv-in"><input id="fin-adv-input" class="fin-input" placeholder="Ask about your revenue, fees…" onkeydown="if(event.key===\'Enter\')FinanceControlCenter.advisorAsk()"><button type="button" class="fin-btn fin-btn-primary" onclick="FinanceControlCenter.advisorAsk()">' + icon('send', 13) + '</button></div>' +
      '</div>';
    root.insertAdjacentHTML('beforeend', h);
  }

  /* ── tabs ────────────────────────────────────────────────────── */

  function loadTab(id, force) {
    if (!id) id = state.tab;
    state.tab = id;
    document.querySelectorAll('.fin-subnav button').forEach(function(b) {
      b.classList.toggle('active', b.getAttribute('data-fin-tab') === id);
    });
    document.querySelectorAll('.fin-pane').forEach(function(p) { p.classList.remove('active'); });
    var pane = document.getElementById('fin-pane-' + id);
    if (pane) pane.classList.add('active');
    if (force || !state.loaded[id]) loadPane(id);
  }
  function loadPane(id) {
    if (id === 'overview') loadOverview();
    else if (id === 'transactions') loadTransactions();
    else if (id === 'revenue') loadRevenue();
    else if (id === 'settlements') loadSettlements();
    else if (id === 'refunds') loadRefunds();
    else if (id === 'fees') loadFees();
    else if (id === 'documents') loadDocuments();
    else if (id === 'reconciliation') loadReconciliation();
  }
  function body(id) { return document.getElementById('fin-body-' + id); }
  function refreshAll() {
    state.loaded = {};
    state.overview = null;
    loadTab(state.tab, true);
  }
  function busy(id) {
    var b = body(id);
    if (b) b.innerHTML = '<div class="fin-loading">' + icon('refresh', 15) + 'Loading…</div>';
  }

  /* ── overview ────────────────────────────────────────────────── */

  function loadOverview() {
    busy('overview');
    getJson('overview').then(function(o) {
      state.overview = o;
      var h = '';

      h += '<div class="fin-kpis">';
      h += kpi('Gross revenue', money(o.gross_revenue), o.paid_transactions + ' paid transactions', 'wallet', '');
      h += kpi('Platform fee', money(o.platform_fee), o.commission_rate + '% commission', 'percent', 'amber');
      h += kpi('Refunds', money(o.refunds_total, true), (o.transaction_counts.refunded || 0) + ' transactions refunded', 'rotate', 'purple');
      h += kpi('Net revenue', money(o.net_revenue), 'after fees & refunds', 'banknote', 'green');
      h += kpi('Available', money(o.settlement.available_balance), 'eligible minus committed withdrawals', 'settle', 'cyan');
      h += '</div>';

      if (o.alerts && o.alerts.length) {
        h += '<div style="margin-bottom:0.8rem">';
        o.alerts.forEach(function(a) {
          h += '<div class="fin-alert ' + (a.type === 'warn' ? 'warn' : (a.type === 'notice' ? 'notice' : 'info')) + '">' +
            '<span class="fin-alert-ico">' + icon(a.type === 'warn' ? 'warn' : 'spark', 15) + '</span>' +
            '<div><b>' + esc(a.title) + '</b><p>' + esc(a.body) + '</p></div>' +
            '<span class="fin-spacer" style="flex:1"></span>' +
            '<button type="button" class="fin-btn fin-btn-line fin-btn-xs" onclick="FinanceControlCenter.loadTab(\'' + esc(a.action) + '\')">Open</button></div>';
        });
        h += '</div>';
      }

      h += gridTwo(
        card('Settlement snapshot', settlementMini(o.settlement)),
        card('Reconciliation', reconciliationMini(o.reconciliation))
      );

      h += gridTwo(
        card('Payment methods', methodsBlock(o.payment_methods)),
        card('Recent activity', activityBlock(o.recent_activity))
      );

      body('overview').innerHTML = h;
      state.loaded.overview = true;
    }).catch(function(e) { fail('overview', e); });
  }
  function kpi(label, value, sub, ico, tone) {
    return '<div class="fin-kpi ' + tone + '"><span class="fin-kpi-ico">' + icon(ico, 20) + '</span>' +
      '<label>' + esc(label) + '</label><b>' + value + '</b><small>' + esc(sub) + '</small></div>';
  }
  function card(title, inner, extra) {
    return '<div class="fin-card"><div class="fin-card-hd"><h3>' + esc(title) + '</h3><span class="fin-spacer"></span>' + (extra || '') + '</div><div class="fin-card-bd">' + inner + '</div></div>';
  }
  function gridTwo(a, b) {
    return '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem;margin-bottom:1rem">' + a + b + '</div>';
  }
  function settlementMini(s) {
    var rows = [
      ['Available balance', money(s.available_balance)],
      ['Pending settlement', money(s.pending_net) + ' (' + s.pending_count + ' txns)'],
      ['Paid out total', money(s.paid_out_total)],
      ['Committed withdrawals', money(s.withdrawn_total)],
      ['Next settlement estimate', money(s.next_settlement.estimated)]
    ];
    var h = '';
    rows.forEach(function(r) {
      h += '<div class="fin-doc-line"><span>' + esc(r[0]) + '</span><b>' + r[1] + '</b></div>';
    });
    h += '<div style="margin-top:0.7rem;display:flex;gap:0.5rem">' +
      '<button type="button" class="fin-btn fin-btn-primary fin-btn-xs" onclick="FinanceControlCenter.loadTab(\'settlements\')">' + icon('plus', 12) + 'Settle</button>' +
      '<button type="button" class="fin-btn fin-btn-line fin-btn-xs" onclick="FinanceControlCenter.withdrawalOpen()">' + icon('bank', 12) + 'Withdraw</button></div>';
    return h;
  }
  function reconciliationMini(r) {
    var ok = r.status === 'BALANCED' && r.open_exceptions === 0;
    var h = '<div class="fin-alert ' + (ok ? 'info' : 'warn') + '">' +
      '<span class="fin-alert-ico">' + icon(ok ? 'check' : 'warn', 15) + '</span>' +
      '<div><b>' + (ok ? 'Reconciliation balanced' : 'Review needed') + '</b>' +
      '<p>Last check ' + (r.checked_at ? date(r.checked_at) : 'never run') + ' · ' + r.exception_count + ' exceptions · difference MK ' + fmt(r.difference) + '</p></div></div>';
    r.matches.forEach(function(m) {
      h += '<div class="fin-recon-item ' + (m.ok ? 'ok' : 'bad') + '">' + icon(m.ok ? 'check' : 'x', 13) + esc(m.label) + '</div>';
    });
    return h;
  }
  function methodsBlock(list) {
    if (!list || !list.length) return '<div class="fin-empty">No completed payments yet.</div>';
    var h = '<div>';
    list.forEach(function(m) {
      h += '<div class="fin-bar-row"><span class="fin-bar-label">' + esc(m.method) + '</span>' +
        '<span class="fin-bar-track"><span class="fin-bar-fill" style="width:' + m.percent + '%"></span></span>' +
        '<span class="fin-bar-val">' + money(m.amount) + ' · ' + m.percent + '%</span></div>';
    });
    return h + '</div>';
  }
  function activityBlock(list) {
    if (!list || !list.length) return '<div class="fin-empty">No activity yet.</div>';
    var h = '';
    list.forEach(function(a) {
      var ico = a.type === 'payment' ? 'banknote' : 'rotate';
      var pill = a.type === 'payment' ? '<span class="fin-pill green">' + icon('check', 11) + 'Paid</span>' : '<span class="fin-pill purple">Refund</span>';
      h += '<div style="display:flex;align-items:center;gap:0.7rem;padding:0.45rem 0;border-bottom:1px dashed var(--ecc-border)">' +
        '<span style="color:var(--ecc-primary)">' + icon(ico, 15) + '</span>' +
        '<div style="flex:1;min-width:0"><b style="font-size:0.72rem;color:var(--ecc-text);display:block">' + esc(a.title) + '</b>' +
        '<small style="color:var(--ecc-text-dim);font-size:0.63rem;font-weight:600">' + dateTime(a.at) + ' · ' + esc(a.method || a.reason || '') + '</small></div>' +
        '<b style="font-size:0.74rem;color:var(--ecc-text);font-variant-numeric:tabular-nums">' + money(a.amount, true) + '</b></div>';
    });
    return h;
  }

  /* ── transactions ────────────────────────────────────────────── */

  function txFilters() {
    var f = state.tx.filters;
    return { q: f.q, event: f.event, status: f.status, method: f.method, from: f.from, to: f.to };
  }
  function loadTransactions(reset) {
    if (reset) state.tx.offset = 0;
    busy('transactions');
    if (!state.events.length) {
      getJson('events').then(function(evs) {
        state.events = evs || [];
        fetchTxRows();
      }).catch(function() { state.events = []; fetchTxRows(); });
    } else fetchTxRows();
  }
  function fetchTxRows() {
    var p = txFilters();
    p.limit = state.tx.limit;
    p.offset = state.tx.offset;
    getJson('transactions', p).then(function(t) {
      renderTransactions(t);
      state.loaded.transactions = true;
    }).catch(function(e) { fail('transactions', e); });
  }
  function renderTransactions(t) {
    var h = '';
    var opts = '<option value="">All events</option>';
    state.events.forEach(function(ev) {
      opts += '<option value="' + esc(ev.listing_id) + '"' + (state.tx.filters.event === ev.listing_id ? ' selected' : '') + '>' + esc(ev.title) + '</option>';
    });
    var mOpts = '<option value="">All methods</option>';
    METHODS.forEach(function(m) {
      mOpts += '<option value="' + esc(m) + '"' + (state.tx.filters.method === m ? ' selected' : '') + '>' + esc(m) + '</option>';
    });
    var sOpts = '<option value="">All statuses</option>';
    STATUSES.forEach(function(s) {
      sOpts += '<option value="' + s + '"' + (state.tx.filters.status === s ? ' selected' : '') + '>' + s + '</option>';
    });

    h += '<div class="fin-filters">' +
      '<input class="fin-input" id="fin-tx-q" placeholder="Search reference, email, name…" value="' + esc(state.tx.filters.q) + '">' +
      '<span class="fin-filter"><select id="fin-tx-event">' + opts + '</select></span>' +
      '<span class="fin-filter"><select id="fin-tx-status">' + sOpts + '</select></span>' +
      '<span class="fin-filter"><select id="fin-tx-method">' + mOpts + '</select></span>' +
      '<input class="fin-input" id="fin-tx-from" type="date" value="' + esc(state.tx.filters.from) + '" style="min-width:140px">' +
      '<input class="fin-input" id="fin-tx-to" type="date" value="' + esc(state.tx.filters.to) + '" style="min-width:140px">' +
      '<button type="button" class="fin-btn fin-btn-primary" onclick="FinanceControlCenter.txApply()">' + icon('search', 13) + 'Apply</button>' +
      '<button type="button" class="fin-btn fin-btn-ghost" onclick="FinanceControlCenter.txReset()">Reset</button>' +
      '<span style="flex:1"></span>' +
      '<button type="button" class="fin-btn fin-btn-line" onclick="FinanceControlCenter.txExport()">' + icon('download', 13) + 'Export CSV</button></div>';

    if (!t.items || !t.items.length) {
      h += '<div class="fin-card"><div class="fin-empty">No transactions match the current filters.</div></div>';
      body('transactions').innerHTML = h;
      return;
    }
    h += '<div class="fin-card"><div class="fin-card-bd" style="padding-top:0.6rem"><table class="fin-table"><thead><tr>' +
      '<th>Reference</th><th>Event</th><th>Amount</th><th>Status</th><th>Method</th><th>Date</th><th></th></tr></thead><tbody>';
    t.items.forEach(function(r) {
      h += '<tr class="clickable" onclick="FinanceControlCenter.txOpen(\'' + esc(r.reference) + '\')">' +
        '<td class="fin-mono">' + esc(r.reference) + '</td>' +
        '<td style="max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(r.event) + '</td>' +
        '<td><b style="font-variant-numeric:tabular-nums">' + money(r.amount) + '</b></td>' +
        '<td>' + statusPill(r.status) + '</td>' +
        '<td>' + esc(r.method) + '</td>' +
        '<td style="white-space:nowrap">' + dateTime(r.date) + '</td>' +
        '<td>' + icon('arrow', 13) + '</td></tr>';
    });
    h += '</tbody></table></div></div>';
    var pages = Math.max(1, Math.ceil(t.total / t.limit));
    var cur = Math.floor(t.offset / t.limit) + 1;
    h += '<div class="fin-pager">' + t.total + ' transactions' +
      '<button type="button" class="fin-btn fin-btn-ghost fin-btn-xs" onclick="FinanceControlCenter.txPage(-1)"' + (cur <= 1 ? ' disabled' : '') + '>← Prev</button>' +
      '<span>' + cur + ' / ' + pages + '</span>' +
      '<button type="button" class="fin-btn fin-btn-ghost fin-btn-xs" onclick="FinanceControlCenter.txPage(1)"' + (cur >= pages ? ' disabled' : '') + '>Next →</button></div>';
    body('transactions').innerHTML = h;
  }
  function statusPill(s) {
    s = String(s || '');
    var cls = 'gray';
    if (/^paid$/i.test(s) || /^(processed|approved)$/i.test(s) || s === 'PAID' || s === 'ELIGIBLE') cls = s === 'ELIGIBLE' ? 'cyan' : 'green';
    else if (/^pending$/i.test(s) || s === 'PENDING' || s === 'REQUESTED' || s === 'PROCESSING') cls = 'amber';
    else if (/^(failed|cancelled)$/i.test(s) || s === 'CANCELLED' || s === 'REJECTED') cls = 'rose';
    else if (/^refunded$/i.test(s) || s === 'OPEN' || s === 'ISSUES') cls = 'purple';
    else if (s === 'RESOLVED' || s === 'BALANCED') cls = 'green';
    var ico = cls === 'rose' ? 'x' : (cls === 'green' ? 'check' : (cls === 'amber' ? 'warn' : ''));
    return '<span class="fin-pill ' + cls + '">' + (ico ? icon(ico, 11) : '') + esc(s) + '</span>';
  }
  function fail(id, e) {
    var b = body(id);
    if (b) b.innerHTML = '<div class="fin-empty">' + esc(e.message || 'Something went wrong.') + '</div>';
    toast(e.message || 'Request failed.', true);
  }

  /* ── transaction drawer ──────────────────────────────────────── */

  function txOpen(ref) {
    getJson('transaction_detail', { ref: ref }).then(function(d) {
      var h = '';
      h += '<div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.9rem">' +
        '<span style="color:var(--ecc-primary)">' + icon('banknote', 20) + '</span>' +
        '<div><b style="font-size:0.86rem;color:var(--ecc-text);display:block">' + esc(d.reference) + '</b>' +
        '<small style="color:var(--ecc-text-dim);font-size:0.65rem;font-weight:600">' + esc(d.event) + '</small></div>' +
        '<span style="flex:1"></span>' + statusPill(d.status) + '</div>';
      h += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.5rem;margin-bottom:1rem">';
      h += '<div class="fin-kpi" style="margin:0"><label>Gross</label><b style="font-size:0.92rem">' + money(d.gross) + '</b></div>';
      h += '<div class="fin-kpi amber" style="margin:0"><label>Platform fee</label><b style="font-size:0.92rem">' + money(d.platform_fee) + '</b></div>';
      h += '<div class="fin-kpi purple" style="margin:0"><label>Refunds</label><b style="font-size:0.92rem">' + money(d.refunds) + '</b></div>';
      h += '<div class="fin-kpi green" style="margin:0"><label>Net</label><b style="font-size:0.92rem">' + money(d.net) + '</b></div>';
      h += '</div>';
      h += '<div class="fin-doc-line"><span>Payment method</span><b>' + esc(d.method) + '</b></div>';
      h += '<div class="fin-doc-line"><span>Commission rate</span><b>' + d.rate + '%</b></div>';
      if (d.card_reference && d.card_reference !== '—') h += '<div class="fin-doc-line"><span>Gateway reference</span><b class="fin-mono">' + esc(d.card_reference) + '</b></div>';
      h += '<div class="fin-doc-line"><span>Booking</span><b class="fin-mono">' + esc(d.booking_id) + '</b></div>';
      h += '<div class="fin-doc-line"><span>Date</span><b>' + dateTime(d.date) + '</b></div>';
      if (d.tickets && d.tickets.length) {
        h += '<h4 style="margin:0.9rem 0 0.45rem;font-size:0.66rem;font-weight:900;text-transform:uppercase;letter-spacing:0.08em;color:var(--ecc-text-dim)">Tickets</h4><div style="display:flex;flex-wrap:wrap;gap:0.4rem">';
        d.tickets.forEach(function(t) {
          h += '<span class="fin-pill ' + (/REFUNDED/i.test(t.status) ? 'purple' : 'green') + '">' + esc(t.type) + ' · ' + esc(t.status) + '</span>';
        });
        h += '</div>';
      }
      if (d.timeline && d.timeline.length) {
        h += '<h4 style="margin:0.9rem 0 0.4rem;font-size:0.66rem;font-weight:900;text-transform:uppercase;letter-spacing:0.08em;color:var(--ecc-text-dim)">Payment timeline</h4>';
        h += '<ul class="fin-timeline">';
        d.timeline.forEach(function(t) {
          h += '<li><b>' + esc(t.label) + '</b>' + dateTime(t.at) + (t.amount ? ' · ' + money(t.amount) : '') + '</li>';
        });
        h += '</ul>';
      }
      var dl = document.getElementById('fin-drawer');
      document.getElementById('fin-drawer-title').textContent = 'Transaction ' + d.reference;
      document.getElementById('fin-drawer-bd').innerHTML = h;
      if (dl) dl.classList.add('open');
    }).catch(function(e) { toast(e.message || 'Failed to load transaction.', true); });
  }
  function drawerClose() {
    var dl = document.getElementById('fin-drawer');
    if (dl) dl.classList.remove('open');
  }

  /* ── revenue ─────────────────────────────────────────────────── */

  function loadRevenue() {
    busy('revenue');
    getJson('revenue', { range: state.revenue.range, from: state.revenue.from, to: state.revenue.to }).then(function(r) {
      renderRevenue(r);
      state.loaded.revenue = true;
    }).catch(function(e) { fail('revenue', e); });
  }
  function renderRevenue(r) {
    var h = '';
    h += '<div class="fin-filters" style="align-items:center">';
    [['7d', '7 days'], ['30d', '30 days'], ['90d', '90 days'], ['custom', 'Custom']].forEach(function(c) {
      h += '<button type="button" class="fin-btn ' + (state.revenue.range === c[0] ? 'fin-btn-primary' : 'fin-btn-ghost') + ' fin-btn-xs" data-fin-range="' + c[0] + '" onclick="FinanceControlCenter.revRange(\'' + c[0] + '\')">' + c[1] + '</button>';
    });
    if (state.revenue.range === 'custom') {
      h += '<input class="fin-input" type="date" id="fin-rev-from" value="' + esc(state.revenue.from) + '" style="min-width:140px">' +
        '<input class="fin-input" type="date" id="fin-rev-to" value="' + esc(state.revenue.to) + '" style="min-width:140px">' +
        '<button type="button" class="fin-btn fin-btn-primary fin-btn-xs" onclick="FinanceControlCenter.revCustom()">Go</button>';
    }
    h += '<span style="flex:1"></span><small style="color:var(--ecc-text-dim);font-size:0.66rem;font-weight:700">' + date(r.from) + ' → ' + date(r.to) + '</small></div>';

    h += gridTwo(
      card('Revenue trend', revenueChart(r.series), '<small class="fin-chart-legend"><span><i style="background:var(--ecc-primary)"></i>Gross</span><span><i style="background:var(--ecc-green)"></i>Net</span></small>'),
      card('By event', bars(r.by_event, function(e) { return e.net; }, function(e) { return e.event; }, function(e) { return 'MK ' + fmt(e.gross) + ' gross'; }))
    );
    h += card('By ticket type', bars(r.by_ticket_type, function(t) { return t.gross; }, function(t) { return t.type; }, function(t) { return t.count + ' bookings · share ' + t.share + '%'; }));
    body('revenue').innerHTML = h;
  }
  function revenueChart(series) {
    if (!series || !series.length) return '<div class="fin-empty">No revenue in this range.</div>';
    var w = Math.max(640, series.length * 30);
    var hgt = 190, padL = 8, padB = 24, padT = 12;
    var maxV = Math.max.apply(null, series.map(function(s) { return s.gross; })) || 1;
    var innerW = w - padL * 2, innerH = hgt - padT - padB;
    var bw = Math.max(3, (innerW / series.length) - 2);
    var bars = '', line = '';
    var step = Math.ceil(series.length / 14);
    series.forEach(function(s, i) {
      var x = padL + i * (innerW / series.length);
      var hG = s.gross / maxV * innerH;
      var hN = s.net / maxV * innerH;
      var yG = padT + innerH - hG;
      bars += '<rect x="' + x.toFixed(1) + '" y="' + yG.toFixed(1) + '" width="' + bw + '" height="' + Math.max(1, hG).toFixed(1) + '" rx="2" fill="rgba(230,57,70,.8)"><title>' + s.date + ' · ' + money(s.gross) + '</title></rect>';
      if (s.net > 0) bars += '<rect x="' + (x + bw + 2).toFixed(1) + '" y="' + (padT + innerH - hN).toFixed(1) + '" width="' + Math.max(1, bw - 3).toFixed(1) + '" height="' + Math.max(1, hN).toFixed(1) + '" rx="2" fill="rgba(16,185,129,.85)"><title>' + s.date + ' · ' + money(s.net) + '</title></rect>';
      if (i % step === 0 || i === series.length - 1) {
        var lbl = s.date.split('-').slice(1).reverse().join('/');
        bars += '<text x="' + x.toFixed(1) + '" y="' + (hgt - 7) + '" font-size="9" fill="currentColor" opacity="0.55">' + lbl + '</text>';
      }
    });
    line = '<polyline points="' + series.map(function(s, i) {
      var x = padL + i * (innerW / series.length) + innerW / series.length / 2;
      var y = padT + innerH - (s.net / maxV * innerH);
      return x.toFixed(1) + ',' + y.toFixed(1);
    }).join(' ') + '" fill="none" stroke="#10b981" stroke-width="1.6" opacity="0.8"><title>Net revenue</title></polyline>';
    return '<div class="fin-chart-wrap"><svg width="' + w + '" height="' + hgt + '" viewBox="0 0 ' + w + ' ' + hgt + '" style="display:block">' + bars + line + '</svg></div>';
  }
  function bars(list, valFn, labelFn, subFn) {
    if (!list || !list.length) return '<div class="fin-empty">No data yet.</div>';
    var maxV = Math.max.apply(null, list.map(valFn)) || 1;
    var h = '<div>';
    list.forEach(function(item) {
      var v = valFn(item);
      h += '<div class="fin-bar-row"><span class="fin-bar-label" title="' + esc(labelFn(item)) + '">' + esc(labelFn(item)) + '</span>' +
        '<span class="fin-bar-track"><span class="fin-bar-fill" style="width:' + (v / maxV * 100).toFixed(1) + '%"></span></span>' +
        '<span class="fin-bar-val" title="' + esc(subFn(item)) + '">' + money(v, true) + '</span></div>';
    });
    return h + '</div>';
  }

  /* ── settlements ─────────────────────────────────────────────── */

  function loadSettlements() {
    busy('settlements');
    getJson('settlements').then(function(s) {
      renderSettlements(s);
      state.loaded.settlements = true;
    }).catch(function(e) { fail('settlements', e); });
  }
  function renderSettlements(s) {
    var h = '';
    h += '<div class="fin-kpis">';
    h += kpi('Available balance', money(s.available_balance), 'ready to withdraw', 'wallet', 'green');
    h += kpi('Pending settlement', money(s.pending_net), s.pending_count + ' completed transactions', 'settle', 'cyan');
    h += kpi('Paid out total', money(s.paid_out_total), 'across settlements', 'bank', '');
    h += kpi('Committed withdrawals', money(s.withdrawn_total), 'in progress or paid', 'rotate', 'purple');
    h += '</div>';
    h += '<div style="display:flex;gap:0.5rem;margin-bottom:1rem;flex-wrap:wrap">' +
      '<button type="button" class="fin-btn fin-btn-primary" onclick="FinanceControlCenter.batchOpen()">' + icon('plus', 13) + 'Create settlement batch</button>' +
      '<button type="button" class="fin-btn fin-btn-line" onclick="FinanceControlCenter.withdrawalOpen()">' + icon('bank', 13) + 'Request withdrawal</button>' +
      '<button type="button" class="fin-btn fin-btn-line" onclick="FinanceControlCenter.accountsOpen()">' + icon('shield', 13) + 'Payout accounts</button>' +
      '<span style="flex:1"></span><small style="color:var(--ecc-text-dim);font-size:0.66rem;font-weight:700">Worst case open liability: ' + money(s.worst_case) + '</small></div>';

    h += card('Settlement batches', s.batches && s.batches.length ? batchTable(s.batches) : '<div class="fin-empty">No settlement batches yet. Create one when revenue is pending.</div>');
    h += card('Withdrawal requests', withdrawalTable(s.withdrawals || []));
    h += '<div id="fin-settle-wdlcat"></div>';
    body('settlements').innerHTML = h;
  }
  function batchTable(list) {
    var h = '<table class="fin-table"><thead><tr><th>Period</th><th>Gross</th><th>Platform fee</th><th>Refunds</th><th>Net</th><th>Status</th><th>Paid</th><th></th></tr></thead><tbody>';
    list.forEach(function(b) {
      h += '<tr><td>' + date(b.period_start) + ' → ' + date(b.period_end) + '</td>' +
        '<td>' + money(b.gross_amount) + '</td><td>' + money(b.platform_fee) + '</td><td>' + money(b.refunds_total) + '</td>' +
        '<td><b>' + money(b.net_amount) + '</b></td><td>' + statusPill(b.status) + '</td>' +
        '<td>' + (b.paid_at ? date(b.paid_at) : '—') + '</td>' +
        '<td>' + (b.status === 'ELIGIBLE' ? '<button type="button" class="fin-btn fin-btn-line fin-btn-xs" onclick="FinanceControlCenter.batchPaid(\'' + esc(b.id) + '\')">Mark paid</button>' : '') + '</td></tr>';
    });
    return h + '</tbody></table>';
  }
  function withdrawalTable(list) {
    if (!list || !list.length) return '<div class="fin-empty">No withdrawal requests.</div>';
    var h = '<table class="fin-table"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Destination</th><th>Status</th></tr></thead><tbody>';
    list.forEach(function(w) {
      h += '<tr><td>' + dateTime(w.requested_at) + '</td><td><b>' + money(w.amount) + '</b></td>' +
        '<td>' + esc(w.method) + '</td><td>' + esc(w.destination || '—') + ' <span class="fin-mono">' + esc(w.reference || '') + '</span></td>' +
        '<td>' + statusPill(w.status) + '</td></tr>';
    });
    return h + '</tbody></table>';
  }

  /* ── batch + withdrawal modals ───────────────────────────────── */

  function batchOpen() {
    var o = state.overview || {};
    var s = o.settlement || {};
    var rate = o.commission_rate || 10;
    var estGross = s.pending_net > 0 ? Math.round(s.pending_net / (1 - rate / 100)) : 0;
    var h = '<p style="font-size:0.74rem;color:var(--ecc-text-dim);line-height:1.55">A settlement batch locks in the eligible revenue and computes the net payout after platform commission and processed refunds. Once created it becomes available for withdrawal.</p>';
    h += '<div class="fin-doc-line"><span>Transactions pending</span><b>' + s.pending_count + '</b></div>';
    h += '<div class="fin-doc-line"><span>Pending gross</span><b>' + money(estGross, true) + '</b></div>';
    h += '<div class="fin-doc-line"><span>Estimated net payout</span><b>' + money(s.pending_net) + '</b></div>';
    if (!s.pending_count) h = '<p style="font-size:0.74rem;color:var(--ecc-text-dim)">There is no eligible revenue to settle right now.</p>';
    modal('Create settlement batch', h, [
      { label: 'Cancel', cls: 'fin-btn-ghost', fn: 'FinanceControlCenter.modalClose()' },
      { label: 'Create batch', cls: 'fin-btn-primary', fn: 'FinanceControlCenter.batchCreate()' }
    ]);
  }
  function batchCreate() {
    postJson({ action: 'batch_create' }).then(function(r) {
      state.overview = null;
      state.loaded.settlements = false;
      state.loaded.overview = false;
      modalClose();
      toast('Settlement batch created and available balance updated.');
      loadSettlements();
      loadOverview();
    }).catch(function(e) { toast(e.message, true); });
  }
  function batchPaid(id) {
    var ref = window.prompt('Settlement reference (bank / M-Pesa reference):', '');
    if (ref === null) return;
    postJson({ action: 'batch_paid', batch_id: id, reference: String(ref || '').trim() }).then(function() {
      toast('Batch marked as paid out.');
      loadSettlements();
    }).catch(function(e) { toast(e.message, true); });
  }
  function withdrawalOpen() {
    var s = (state.overview || {}).settlement || {};
    if (!s.available_balance || s.available_balance <= 0) {
      toast('No funds available to withdraw yet — settle revenue first.', true);
      return;
    }
    getJson('accounts').then(function(acc) {
      var items = acc.items || [];
      var opts = '<option value="">' + (items.length ? 'Choose an account' : 'Enter destination manually') + '</option>';
      items.forEach(function(a) {
        opts += '<option value="' + esc(a.id) + '">' + esc(a.label) + ' · ' + esc(a.account_number_masked) + '</option>';
      });
      var h = '<p style="font-size:0.7rem;color:var(--ecc-text-dim);font-weight:700">Available balance: <b style="color:var(--ecc-green)">' + money(s.available_balance) + '</b></p>';
      h += '<div class="fin-form-row"><label>Amount (MK)</label><input id="fin-wdl-amount" type="number" min="1" max="' + s.available_balance + '" step="500" placeholder="e.g. 500000"></div>';
      h += '<div class="fin-form-row"><label>Payout account</label><select id="fin-wdl-account">' + opts + '</select></div>';
      h += '<div class="fin-form-row"><label>Method</label><select id="fin-wdl-method"><option value="BANK">Bank transfer</option><option value="MOBILE_MONEY">Mobile money</option></select></div>';
      h += '<div class="fin-form-row"><label>Destination label</label><input id="fin-wdl-dest" placeholder="e.g. Standard Bank 1234567890"></div>';
      h += '<div class="fin-form-row"><label>Destination reference</label><input id="fin-wdl-ref" placeholder="e.g. Airtel number or account last digits"></div>';
      h += '<p style="font-size:0.64rem;color:var(--ecc-text-muted)">Withdrawals are reviewed by the platform before transfer — the request stays REQUESTED until then. Use "Add payout account" below if you have none.</p>';
      modal('Request withdrawal', h, [
        { label: 'Cancel', cls: 'fin-btn-ghost', fn: 'FinanceControlCenter.modalClose()' },
        { label: 'Add payout account', cls: 'fin-btn-line', fn: 'FinanceControlCenter.accountsOpen()' },
        { label: 'Request withdrawal', cls: 'fin-btn-primary', fn: 'FinanceControlCenter.withdrawalSubmit()' }
      ]);
      var accSel = document.getElementById('fin-wdl-account');
      if (accSel) accSel.onchange = function() {
        var id = accSel.value;
        var a = items.filter(function(x) { return x.id === id; })[0];
        if (!a) return;
        document.getElementById('fin-wdl-dest').value = a.label;
        document.getElementById('fin-wdl-method').value = a.method === 'MOBILE_MONEY' ? 'MOBILE_MONEY' : 'BANK';
        document.getElementById('fin-wdl-ref').value = a.account_number_masked;
      };
    }).catch(function(e) { toast(e.message, true); });
  }
  function withdrawalSubmit() {
    var amount = parseFloat(document.getElementById('fin-wdl-amount').value || '0');
    var account = document.getElementById('fin-wdl-account').value;
    var payload = {
      action: 'withdrawal_request', amount: amount,
      method: document.getElementById('fin-wdl-method').value || 'BANK',
      destination: (document.getElementById('fin-wdl-dest').value || '').trim(),
      destination_ref: (document.getElementById('fin-wdl-ref').value || '').trim()
    };
    if (account) payload.account_id = account;
    if (!amount || amount <= 0) { toast('Enter a valid amount.', true); return; }
    postJson(payload).then(function() {
      toast('Withdrawal request submitted for ' + money(amount) + '.');
      state.loaded.settlements = false;
      state.loaded.overview = false;
      modalClose();
      loadSettlements();
      loadOverview();
    }).catch(function(e) { toast(e.message, true); });
  }

  /* ── payout accounts ─────────────────────────────────────────── */

  function accountsOpen() {
    getJson('accounts').then(function(acc) {
      var h = '<div id="fin-acc-list"></div>';
      acc.items.forEach(function(a) {
        h += '<div class="fin-acc-item"><span class="fin-acc-ico">' + icon(a.method === 'MOBILE_MONEY' ? 'phone' : 'bank', 17) + '</span>' +
          '<div style="flex:1;min-width:0"><b>' + esc(a.label) + '</b>' +
          '<small>' + esc(a.account_name) + ' · ' + esc(a.account_number_masked) + (a.provider ? ' · ' + esc(a.provider) : '') + '</small></div>' +
          (a.is_verified ? '<span class="fin-pill green">' + icon('check', 11) + 'Verified</span>' : '<span class="fin-pill amber">Unverified</span>') +
          (a.is_default ? '<span class="fin-pill gray">Default</span>' : '') + '</div>';
      });
      if (!acc.items.length) h += '<div class="fin-empty" style="padding:0.8rem">No payout accounts yet.</div>';
      h += '<div style="border-top:1px dashed var(--ecc-border);margin-top:0.7rem;padding-top:0.8rem">';
      h += '<div class="fin-form-row"><label>Method</label><select id="fin-acc-method"><option value="BANK">Bank transfer</option><option value="MOBILE_MONEY">Mobile money</option></select></div>';
      h += '<div class="fin-form-row"><label>Label</label><input id="fin-acc-label" placeholder="e.g. Business bank account"></div>';
      h += '<div class="fin-form-row"><label>Account name</label><input id="fin-acc-name" placeholder="Full account holder name"></div>';
      h += '<div class="fin-form-row"><label>Account number</label><input id="fin-acc-number" placeholder="Account number / phone number"></div>';
      h += '<div class="fin-form-row"><label>Provider (optional)</label><input id="fin-acc-provider" placeholder="e.g. Standard Bank / Airtel Money"></div>';
      h += '<div class="fin-form-row"><label><input type="checkbox" id="fin-acc-default"> Set as default payout account</label></div>';
      h += '</div>';
      modal('Payout accounts', h, [
        { label: 'Close', cls: 'fin-btn-ghost', fn: 'FinanceControlCenter.modalClose()' },
        { label: 'Save account', cls: 'fin-btn-primary', fn: 'FinanceControlCenter.accountSave()' }
      ]);
    }).catch(function(e) { toast(e.message, true); });
  }
  function accountSave() {
    var payload = {
      action: 'account_save',
      method: document.getElementById('fin-acc-method').value,
      label: document.getElementById('fin-acc-label').value,
      account_name: document.getElementById('fin-acc-name').value,
      account_number: document.getElementById('fin-acc-number').value,
      provider: document.getElementById('fin-acc-provider').value,
      is_default: document.getElementById('fin-acc-default').checked ? 1 : 0
    };
    if (!payload.account_name.trim() || !payload.account_number.trim()) { toast('Account name and number are required.', true); return; }
    postJson(payload).then(function() {
      toast('Payout account saved.');
      accountsOpen();
    }).catch(function(e) { toast(e.message, true); });
  }

  /* ── refunds ─────────────────────────────────────────────────── */

  function loadRefunds() {
    busy('refunds');
    getJson('refunds').then(function(r) {
      var h = '<div class="fin-kpis">';
      h += kpi('Pending approval', r.summary.pending, 'awaiting your decision', 'warn', 'amber');
      h += kpi('Processed', r.summary.processed, 'refunded transactions', 'rotate', 'purple');
      h += kpi('Total refund value', money(r.summary.value), 'processed + pending', 'banknote', '');
      h += '</div>';
      if (!r.items.length) h += '<div class="fin-card"><div class="fin-empty">No refund records for your events.</div></div>';
      else {
        h += '<div class="fin-card"><div class="fin-card-bd" style="padding-top:0.6rem"><table class="fin-table"><thead><tr>' +
          '<th>Request</th><th>Event</th><th>Booking</th><th>Reason</th><th>Amount</th><th>Status</th><th>Decided</th></tr></thead><tbody>';
        r.items.forEach(function(x) {
          h += '<tr><td class="fin-mono">' + esc(x.id) + '</td><td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(x.event) + '</td>' +
            '<td class="fin-mono">' + esc(x.booking) + '</td><td style="max-width:200px">' + esc(x.reason) + '</td>' +
            '<td><b>' + money(x.amount) + '</b></td><td>' + statusPill(x.status) + '</td>' +
            '<td>' + (x.decided_at ? dateTime(x.decided_at) : '—') + '</td></tr>';
        });
        h += '</tbody></table></div></div>';
      }
      body('refunds').innerHTML = h;
      state.loaded.refunds = true;
    }).catch(function(e) { fail('refunds', e); });
  }

  /* ── fees ────────────────────────────────────────────────────── */

  function loadFees() {
    busy('fees');
    getJson('fees').then(function(f) {
      var h = '<div class="fin-alert info" style="margin-bottom:1rem"><span class="fin-alert-ico">' + icon('percent', 15) + '</span>' +
        '<div><b>Platform commission ' + f.rate + '%</b><p>Commission is applied per paid ticket sale and is calculated from the central settings table.</p></div></div>';
      h += '<div class="fin-kpis">';
      h += kpi('Gross ticket sales', money(f.gross), 'paid transactions', 'banknote', '');
      h += kpi('Platform commission', money(f.commission), f.rate + '% of gross', 'percent', 'amber');
      h += kpi('Refund charges', money(f.refund_charges), 'processed refunds', 'rotate', 'purple');
      h += kpi('Total deductions', money(f.total), 'commission + charges', 'settle', '');
      h += '</div>';
      h += card('By event', bars(f.by_event, function(e) { return e.fees; }, function(e) { return e.event; }, function(e) { return 'MK ' + fmt(e.gross) + ' gross' + (f.rate ? ' · ' + f.rate + '% fee' : ''); }));
      body('fees').innerHTML = h;
      state.loaded.fees = true;
    }).catch(function(e) { fail('fees', e); });
  }

  /* ── documents ───────────────────────────────────────────────── */

  function loadDocuments() {
    busy('documents');
    getJson('documents').then(function(d) {
      var h = '<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem">';
      [['SETTLEMENT', 'Settlement statement'], ['COMMISSION', 'Commission statement'], ['REFUND', 'Refund statement'], ['EVENT_STATEMENT', 'Event statement']].forEach(function(g) {
        h += '<button type="button" class="fin-btn fin-btn-primary" onclick="FinanceControlCenter.docGen(\'' + g[0] + '\')">' + icon('plus', 12) + 'Generate ' + g[1] + '</button>';
      });
      h += '</div>';
      if (!d.items.length) h += '<div class="fin-card"><div class="fin-empty">No finance documents yet — generate one above.</div></div>';
      else {
        h += '<div class="fin-card"><div class="fin-card-bd" style="padding-top:0.6rem"><table class="fin-table"><thead><tr>' +
          '<th>Reference</th><th>Type</th><th>Period</th><th>Issued</th><th></th></tr></thead><tbody>';
        d.items.forEach(function(x) {
          h += '<tr><td class="fin-mono">' + esc(x.reference) + '</td>' +
            '<td>' + statusPill(x.doc_type) + '</td>' +
            '<td>' + (x.period_start ? date(x.period_start) + ' → ' + date(x.period_end) : '—') + '</td>' +
            '<td>' + dateTime(x.created_at) + '</td>' +
            '<td><button type="button" class="fin-btn fin-btn-line fin-btn-xs" onclick="FinanceControlCenter.docView(\'' + esc(x.id) + '\')">' + icon('eye', 12) + 'View</button></td></tr>';
        });
        h += '</tbody></table></div></div>';
      }
      body('documents').innerHTML = h;
      state.loaded.documents = true;
    }).catch(function(e) { fail('documents', e); });
  }
  function docGen(type) {
    var payload = { action: 'doc_generate', doc_type: type };
    if (type === 'EVENT_STATEMENT') {
      var opts = '<option value="">Choose an event…</option>';
      (state.events || []).forEach(function(ev) {
        opts += '<option value="' + esc(ev.event_id) + '">' + esc(ev.title) + '</option>';
      });
      var h = '<div class="fin-form-row"><label>Event</label><select id="fin-doc-event">' + opts + '</select></div>';
      modal('Generate event statement', h, [
        { label: 'Cancel', cls: 'fin-btn-ghost', fn: 'FinanceControlCenter.modalClose()' },
        { label: 'Generate', cls: 'fin-btn-primary', fn: 'FinanceControlCenter.docGenSubmit()' }
      ]);
      return;
    }
    postJson(payload).then(function() {
      toast('Document generated.');
      loadDocuments();
    }).catch(function(e) { toast(e.message, true); });
  }
  function docGenSubmit() {
    var ev = document.getElementById('fin-doc-event').value;
    if (!ev) { toast('Choose an event first.', true); return; }
    postJson({ action: 'doc_generate', doc_type: 'EVENT_STATEMENT', event_id: ev }).then(function() {
      toast('Event statement generated.');
      modalClose();
      loadDocuments();
    }).catch(function(e) { toast(e.message, true); });
  }
  function docView(id) {
    getJson('document', { id: id }).then(function(d) {
      var p = d.payload || {};
      var h = '<p style="font-size:0.76rem;font-weight:900;color:var(--ecc-text)">' + esc(p.title || d.reference) + '</p>';
      h += '<p style="font-size:0.62rem;color:var(--ecc-text-dim);font-weight:700;margin-bottom:0.9rem">Reference ' + d.reference + ' · generated ' + dateTime(d.created_at) + '</p>';
      (p.sections || []).forEach(function(sec) {
        h += '<div class="fin-doc-sec"><h4>' + esc(sec.heading) + '</h4>';
        (sec.lines || []).forEach(function(l) {
          h += '<div class="fin-doc-line' + (l.total ? ' total' : '') + '"><span>' + esc(l.label) + '</span><b>' + money(l.amount) + '</b></div>';
        });
        h += '</div>';
      });
      modal(d.doc_type + ' document', h, [
        { label: 'Close', cls: 'fin-btn-ghost', fn: 'FinanceControlCenter.modalClose()' },
        { label: 'Download JSON', cls: 'fin-btn-line', fn: 'FinanceControlCenter.docDownload(\'' + esc(d.id) + '\',\'' + esc(d.reference) + '\')' }
      ]);
    }).catch(function(e) { toast(e.message, true); });
  }
  function docDownload(id, ref) {
    getJson('document', { id: id }).then(function(d) {
      var blob = new Blob([JSON.stringify(d.payload, null, 2)], { type: 'application/json' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'uthenga-' + (ref || id) + '.json';
      document.body.appendChild(a);
      a.click();
      setTimeout(function() { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    }).catch(function(e) { toast(e.message, true); });
  }

  /* ── reconciliation ──────────────────────────────────────────── */

  function loadReconciliation() {
    busy('reconciliation');
    getJson('reconciliation').then(function(r) {
      renderReconciliation(r);
      state.loaded.reconciliation = true;
    }).catch(function(e) { fail('reconciliation', e); });
  }
  function renderReconciliation(r) {
    var ok = r.status === 'BALANCED' && r.open_exceptions === 0;
    var h = '';
    h += '<div class="fin-alert ' + (ok ? 'info' : 'warn') + '">' +
      '<span class="fin-alert-ico">' + icon(ok ? 'check' : 'warn', 16) + '</span>' +
      '<div><b>' + (ok ? 'Books are balanced' : r.open_exceptions + ' open exception(s) need review') + '</b>' +
      '<p>Last check: ' + (r.checked_at ? dateTime(r.checked_at) : 'never run') + '</p></div>' +
      '<span style="flex:1"></span>' +
      '<button type="button" class="fin-btn fin-btn-primary fin-btn-xs" onclick="FinanceControlCenter.reconRun()">' + icon('refresh', 12) + 'Run check</button></div>';
    h += '<div class="fin-kpis">';
    h += kpi('Expected amount', money(r.expected_amount), 'paid bookings', 'banknote', '');
    h += kpi('Recorded amount', money(r.recorded_amount), 'after fees & refunds', 'settle', '');
    h += kpi('Difference', money(r.difference), 'fee + refund space', 'percent', r.difference > 0 ? 'amber' : 'green');
    h += kpi('Open exceptions', r.open_exceptions, 'require decision', 'warn', r.open_exceptions > 0 ? 'rose' : 'green');
    h += '</div>';
    h += card('Checks', '<div class="fin-recon-list">' + r.matches.map(function(m) {
      return '<div class="fin-recon-item ' + (m.ok ? 'ok' : 'bad') + '">' + icon(m.ok ? 'check' : 'x', 13) + esc(m.label) + '</div>';
    }).join('') + '</div>');
    getJson('exceptions').then(function(ex) {
      h += card('Open exceptions', ex.items && ex.items.length ? exceptionsTable(ex.items) : '<div class="fin-empty">No open exceptions.</div>');
      body('reconciliation').innerHTML = h;
    }).catch(function() { body('reconciliation').innerHTML = h; });
  }
  function exceptionsTable(list) {
    var h = '<table class="fin-table"><thead><tr><th>Category</th><th>Reference</th><th>Expected</th><th>Recorded</th><th>Note</th><th></th></tr></thead><tbody>';
    list.forEach(function(e) {
      h += '<tr><td>' + statusPill(e.category) + '</td><td class="fin-mono">' + esc(e.reference) + '</td>' +
        '<td>' + money(e.expected_amount) + '</td><td>' + money(e.recorded_amount) + '</td>' +
        '<td style="max-width:220px;font-size:0.66rem;color:var(--ecc-text-dim)">' + esc(e.resolution_note) + '</td>' +
        '<td><button type="button" class="fin-btn fin-btn-line fin-btn-xs" onclick="FinanceControlCenter.excResolve(\'' + esc(e.id) + '\')">Resolve</button></td></tr>';
    });
    return h + '</tbody></table>';
  }
  function reconRun() {
    var b = document.getElementById('fin-body-reconciliation');
    if (b) b.innerHTML = '<div class="fin-loading">' + icon('refresh', 15) + 'Running reconciliation…</div>';
    postJson({ action: 'reconciliation_run' }).then(function(r) {
      toast('Reconciliation check complete (' + r.exception_count + ' exceptions).');
      state.loaded.reconciliation = false;
      state.loaded.overview = false;
      loadReconciliation();
      loadOverview();
    }).catch(function(e) { toast(e.message, true); loadReconciliation(); });
  }
  function excResolve(id) {
    var note = window.prompt('Resolution note:', 'Reviewed by organizer — amounts confirmed.');
    if (note === null) return;
    postJson({ action: 'exception_resolve', id: id, note: String(note).trim() }).then(function() {
      toast('Exception resolved.');
      state.loaded.reconciliation = false;
      loadReconciliation();
    }).catch(function(e) { toast(e.message, true); });
  }

  /* ── advisor ─────────────────────────────────────────────────── */

  function advisorToggle() {
    var el = document.getElementById('fin-adv');
    if (!el) return;
    var open = !el.classList.contains('open');
    el.classList.toggle('open', open);
    if (open) {
      var bodyEl = document.getElementById('fin-adv-body');
      if (!bodyEl.children.length) {
        bodyEl.innerHTML = '<div class="fin-adv-msg bot">Hi — I am your read-only event finance advisor. Ask me about revenue, fees, settlements, refunds or reconciliation and I will answer from your actual numbers.</div>';
      }
      var input = document.getElementById('fin-adv-input');
      if (input) input.focus();
    }
  }
  function advisorAsk() {
    var input = document.getElementById('fin-adv-input');
    var msg = (input.value || '').trim();
    if (!msg) return;
    input.value = '';
    var bodyEl = document.getElementById('fin-adv-body');
    bodyEl.insertAdjacentHTML('beforeend', '<div class="fin-adv-msg user">' + esc(msg) + '</div>');
    bodyEl.insertAdjacentHTML('beforeend', '<div class="fin-adv-msg bot">Thinking…</div>');
    bodyEl.scrollTop = bodyEl.scrollHeight;
    getJson('advisor', { message: msg }).then(function(a) {
      var last = bodyEl.querySelector('.fin-adv-msg.bot:last-child');
      if (last) last.textContent = String(a.message || '');
      var conf = a.confidence || 'MEDIUM';
      var acts = document.getElementById('fin-adv-actions');
      [['Settlements', 'settlements'], ['Refunds', 'refunds'], ['Fees', 'fees'], ['Reconciliation', 'reconciliation']].forEach(function(pair) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = pair[0];
        b.onclick = function() { advisorToggle(); loadTab(pair[1]); };
        acts.appendChild(b);
      });
      var fol = document.getElementById('fin-adv-follow');
      fol.innerHTML = '';
      if (a.fallback_used) fol.innerHTML = '<span style="font-size:0.62rem;color:var(--ecc-text-muted);font-weight:700">' + icon('shield', 11) + ' Answer computed locally from your evidence.</span>';
      (a.follow_up_questions || []).forEach(function(q) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = q;
        b.onclick = function() { document.getElementById('fin-adv-input').value = q; advisorAsk(); };
        fol.appendChild(b);
      });
      bodyEl.scrollTop = bodyEl.scrollHeight;
    }).catch(function(e) {
      var last = bodyEl.querySelector('.fin-adv-msg.bot:last-child');
      if (last) last.textContent = e.message || 'Advisor unavailable right now.';
    });
  }

  /* ── misc shared ─────────────────────────────────────────────── */

  function modal(title, inner, buttons) {
    document.getElementById('fin-modal-title').textContent = title;
    document.getElementById('fin-modal-bd').innerHTML = inner;
    var ft = document.getElementById('fin-modal-ft');
    ft.innerHTML = buttons.map(function(b) {
      return '<button type="button" class="fin-btn ' + b.cls + '" onclick="' + b.fn + '">' + b.label + '</button>';
    }).join('');
    document.getElementById('fin-modal-overlay').classList.add('open');
  }
  function modalClose() {
    document.getElementById('fin-modal-overlay').classList.remove('open');
  }

  function txApply() {
    var f = state.tx.filters;
    f.q = document.getElementById('fin-tx-q').value;
    f.event = document.getElementById('fin-tx-event').value;
    f.status = document.getElementById('fin-tx-status').value;
    f.method = document.getElementById('fin-tx-method').value;
    f.from = document.getElementById('fin-tx-from').value;
    f.to = document.getElementById('fin-tx-to').value;
    loadTransactions(true);
  }
  function txReset() {
    state.tx.filters = { q: '', event: '', status: '', method: '', from: '', to: '' };
    state.loaded.transactions = false;
    loadTransactions(true);
  }
  function txPage(dir) {
    var n = state.tx.offset + dir * state.tx.limit;
    if (n < 0) return;
    state.tx.offset = n;
    fetchTxRows();
  }
  function txExport() {
    fetch(api, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-CSRF-Token': csrf },
      body: qs(Object.assign({ action: 'csv_export' }, state.tx.filters))
    }).then(function(r) {
      if (!r.ok) return r.json().then(function(j) { throw new Error(j && j.error ? j.error.message : 'Export failed.'); });
      var cd = r.headers.get('Content-Disposition') || '';
      var name = (cd.match(/filename="?([^";]+)/) || [])[1] || 'uthenga-transactions.csv';
      return r.blob().then(function(blob) {
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a);
        a.click();
        setTimeout(function() { URL.revokeObjectURL(a.href); a.remove(); }, 500);
      });
    }).catch(function(e) { toast(e.message || 'Export failed.', true); });
  }
  function revRange(r) {
    state.revenue.range = r;
    state.revenue.from = '';
    state.revenue.to = '';
    state.loaded.revenue = false;
    loadRevenue();
  }
  function revCustom() {
    state.revenue.from = document.getElementById('fin-rev-from').value;
    state.revenue.to = document.getElementById('fin-rev-to').value;
    if (!state.revenue.from || !state.revenue.to) { toast('Pick both dates.', true); return; }
    state.loaded.revenue = false;
    loadRevenue();
  }

  /* ── boot ────────────────────────────────────────────────────── */

  function init() {
    build();
    window.onEccModuleShow = (function(prev) {
      return function(modId) {
        if (typeof prev === 'function') { try { prev(modId); } catch (e) {} }
        if (modId === 'finance') {
          if (!state.loaded[state.tab]) loadPane(state.tab);
          else if (state.tab === 'settlements' || state.tab === 'overview') loadPane(state.tab);
        }
      };
    })(window.onEccModuleShow);
  }

  return {
    init: init,
    loadTab: loadTab,
    refreshAll: refreshAll,
    drawerClose: drawerClose,
    txOpen: txOpen,
    txApply: txApply,
    txReset: txReset,
    txPage: txPage,
    txExport: txExport,
    revRange: revRange,
    revCustom: revCustom,
    batchOpen: batchOpen,
    batchCreate: batchCreate,
    batchPaid: batchPaid,
    withdrawalOpen: withdrawalOpen,
    withdrawalSubmit: withdrawalSubmit,
    accountsOpen: accountsOpen,
    accountSave: accountSave,
    docGen: docGen,
    docGenSubmit: docGenSubmit,
    docView: docView,
    docDownload: docDownload,
    reconRun: reconRun,
    excResolve: excResolve,
    advisorToggle: advisorToggle,
    advisorAsk: advisorAsk,
    modalClose: modalClose
  };
})();

if (window.FinanceControlCenter && typeof window.FinanceControlCenter.init === 'function') {
  window.FinanceControlCenter.init();
}