/* Uthenga — Finance Console Controller (Events V2).
 * Renders organizer's revenue, transactions, settlements, refunds, fees,
 * invoices, and reconciliation workspace backed by the central finance engine.
 * Executive-grade event financial operations console.
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
    { id: 'documents', label: 'Invoices & Documents' },
    { id: 'reconciliation', label: 'Reconciliation' }
  ];

  var METHODS = ['Airtel Money', 'TNM Mpamba', 'Bank Card', 'Uthenga Pay', 'Manual (Organizer)', 'Complimentary'];
  var STATUSES = ['Paid', 'Pending', 'Failed', 'Cancelled', 'Refunded', 'Partially Refunded', 'Reversed', 'Disputed'];

  var state = {
    tab: 'overview',
    tx: { offset: 0, limit: 25, filters: { q: '', event: '', status: '', method: '', from: '', to: '' } },
    revenue: { range: '30d', from: '', to: '' },
    events: [],
    loaded: {},
    overview: null
  };

  /* ── Helpers & Formatting ───────────────────────────────────────── */

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
  function fmt(n) {
    var v = Number(n) || 0;
    return v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : v >= 1000 ? (v / 1000).toFixed(1) + 'K' : String(Math.round(v));
  }
  function icon(faClass) {
    return '<i class="' + faClass + '"></i>';
  }

  /* Structural Card Component Helpers */
  function card(title, inner, extra) {
    return '<div class="fin-card">' +
      '<div class="fin-card-hd"><h3>' + esc(title) + '</h3><span class="fin-spacer"></span>' + (extra || '') + '</div>' +
      '<div class="fin-card-bd">' + inner + '</div>' +
    '</div>';
  }

  function gridTwo(a, b) {
    return '<div class="fin-grid-2">' + a + b + '</div>';
  }

  function kpi(label, value, sub, icoClass, tone) {
    return '<div class="fin-kpi ' + tone + '"><span class="fin-kpi-ico">' + icon(icoClass) + '</span>' +
      '<label>' + esc(label) + '</label><b>' + value + '</b><small>' + esc(sub) + '</small></div>';
  }

  function statusPill(s) {
    s = String(s || '');
    var cls = 'gray';
    if (/^paid$/i.test(s) || /^(processed|approved)$/i.test(s) || s === 'PAID' || s === 'ELIGIBLE') cls = s === 'ELIGIBLE' ? 'cyan' : 'green';
    else if (/^pending$/i.test(s) || s === 'PENDING' || s === 'REQUESTED' || s === 'PROCESSING') cls = 'amber';
    else if (/^(failed|cancelled)$/i.test(s) || s === 'CANCELLED' || s === 'REJECTED') cls = 'rose';
    else if (/^refunded$/i.test(s) || s === 'OPEN' || s === 'ISSUES') cls = 'purple';
    else if (s === 'RESOLVED' || s === 'BALANCED') cls = 'green';
    var ico = cls === 'rose' ? 'fas fa-times' : (cls === 'green' ? 'fas fa-check' : (cls === 'amber' ? 'fas fa-clock' : ''));
    return '<span class="fin-pill ' + cls + '">' + (ico ? icon(ico) + ' ' : '') + esc(s.toUpperCase()) + '</span>';
  }

  /* ── API Layer ─────────────────────────────────────────────────── */

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

  /* ── Shell & UI Layout ─────────────────────────────────────────── */

  function build() {
    var root = document.getElementById('fin-root');
    if (!root || root.dataset.built) return;
    root.dataset.built = '1';

    var h = '<div class="fin-head">';
    h += '<div class="fin-head-l">' +
      '<h2>Finance Console</h2>' +
      '<p>Track revenue, payments, settlements, fees, and financial activity across your events.</p>' +
      '</div>';
    h += '<div class="fin-head-r">' +
      '<span class="fin-head-badge">' + icon('fas fa-shield-alt') + ' Verified Ledger</span>' +
      '<button type="button" class="fin-btn fin-btn-line fin-btn-sm" onclick="FinanceControlCenter.refreshAll()">' + icon('fas fa-sync-alt') + ' Refresh</button>' +
      '</div>';
    h += '</div>';

    h += '<div class="fin-toolbar">';
    h += '<div class="fin-subnav" role="tablist">';
    TABS.forEach(function(t) {
      h += '<button type="button" data-fin-tab="' + t.id + '" class="' + (t.id === 'overview' ? 'active' : '') + '" onclick="FinanceControlCenter.loadTab(\'' + t.id + '\')">' + esc(t.label) + '</button>';
    });
    h += '</div>';
    h += '<button type="button" class="fin-ai-toggle" onclick="FinanceControlCenter.advisorToggle()">' + icon('fas fa-robot') + ' Finance AI Assistant</button>';
    h += '</div>';

    TABS.forEach(function(t) {
      h += '<div class="fin-pane' + (t.id === 'overview' ? ' active' : '') + '" id="fin-pane-' + t.id + '"><div id="fin-body-' + t.id + '" class="fin-loading">' + icon('fas fa-spinner') + ' Loading financial data…</div></div>';
    });

    // Drawer & Modals
    h += '<div class="fin-drawer" id="fin-drawer">' +
      '<div class="fin-drawer-hd"><h3 id="fin-drawer-title">Transaction Details</h3><button type="button" class="fin-close" onclick="FinanceControlCenter.drawerClose()" title="Close">' + icon('fas fa-times') + '</button></div>' +
      '<div class="fin-drawer-bd" id="fin-drawer-bd"></div></div>';

    h += '<div class="fin-modal-overlay" id="fin-modal-overlay" onclick="if(event.target===this)FinanceControlCenter.modalClose()">' +
      '<div class="fin-modal"><div class="fin-modal-hd"><h3 id="fin-modal-title">Modal</h3><button type="button" class="fin-close" onclick="FinanceControlCenter.modalClose()" title="Close">' + icon('fas fa-times') + '</button></div>' +
      '<div class="fin-modal-bd" id="fin-modal-bd"></div>' +
      '<div class="fin-modal-ft" id="fin-modal-ft"></div></div></div>';

    // AI Advisor Panel
    h += '<div class="fin-adv" id="fin-adv">' +
      '<div class="fin-adv-hd">' + icon('fas fa-robot') + '<div><b>Finance AI Assistant</b><br><small>Read-only advisor · answers from your live ledger evidence</small></div><button type="button" class="fin-close" onclick="FinanceControlCenter.advisorToggle()">' + icon('fas fa-times') + '</button></div>' +
      '<div class="fin-adv-body" id="fin-adv-body"></div>' +
      '<div class="fin-adv-actions" id="fin-adv-actions"></div>' +
      '<div class="fin-adv-follow" id="fin-adv-follow"></div>' +
      '<div class="fin-adv-in"><input id="fin-adv-input" class="fin-input" placeholder="Ask about revenue, fees, settlements, refunds…" onkeydown="if(event.key===\'Enter\')FinanceControlCenter.advisorAsk()"><button type="button" class="fin-btn fin-btn-primary" onclick="FinanceControlCenter.advisorAsk()">' + icon('fas fa-paper-plane') + '</button></div>' +
      '</div>';

    root.insertAdjacentHTML('beforeend', h);
  }

  /* ── Tab Navigation ───────────────────────────────────────────── */

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
    if (b) {
      b.classList.add('fin-loading');
      b.innerHTML = icon('fas fa-spinner') + ' Loading financial data…';
    }
  }

  function renderContent(id, html) {
    var b = body(id);
    if (b) {
      b.classList.remove('fin-loading');
      b.innerHTML = html;
    }
  }

  function fail(id, e) {
    renderContent(id, '<div class="fin-empty">' + esc(e.message || 'Something went wrong.') + '</div>');
    toast(e.message || 'Request failed.', true);
  }

  /* ── 1. OVERVIEW SCREEN ────────────────────────────────────────── */

  function loadOverview() {
    busy('overview');
    getJson('overview').then(function(o) {
      state.overview = o;
      var h = '';

      // Top 4 KPI Cards (Grid Row)
      h += '<div class="fin-kpis">';
      h += kpi('Gross Revenue', money(o.gross_revenue), o.paid_transactions + ' paid ticket sales', 'fas fa-wallet', '');
      h += kpi('Net Revenue', money(o.net_revenue), 'after fees & refunds', 'fas fa-check-circle', 'green');
      h += kpi('Pending Settlement', money(o.settlement.pending_net), o.settlement.pending_count + ' completed transactions', 'fas fa-clock', 'cyan');
      h += kpi('Available Balance', money(o.settlement.available_balance), 'ready for payout withdrawal', 'fas fa-piggy-bank', 'amber');
      h += '</div>';

      // Secondary Summary Pills Row
      var tc = o.transaction_counts || {};
      h += '<div class="fin-summary-row">';
      h += '<div class="fin-stat-card"><span class="lbl">Total Transactions</span><span class="val">' + (tc.total || 0) + '</span></div>';
      h += '<div class="fin-stat-card"><span class="lbl">Successful</span><span class="val" style="color:var(--ecc-green);">' + (tc.successful || 0) + '</span></div>';
      h += '<div class="fin-stat-card"><span class="lbl">Pending</span><span class="val" style="color:var(--ecc-amber);">' + (tc.pending || 0) + '</span></div>';
      h += '<div class="fin-stat-card"><span class="lbl">Refunded</span><span class="val" style="color:var(--ecc-purple);">' + (tc.refunded || 0) + '</span></div>';
      h += '<div class="fin-stat-card"><span class="lbl">Failed</span><span class="val" style="color:var(--ecc-rose);">' + (tc.failed || 0) + '</span></div>';
      h += '</div>';

      // Gross vs Net Waterfall Card
      var commRate = o.commission_rate || 10;
      h += '<div class="fin-waterfall">';
      h += '<div style="display:flex;justify-content:space-between;align-items:center;">';
      h += '<div><strong style="font-size:0.92rem;color:var(--ecc-text-bright);">GROSS VS NET REVENUE WATERFALL</strong>';
      h += '<span style="font-size:0.72rem;color:var(--ecc-text-dim);display:block;margin-top:0.15rem;">Complete monetary breakdown from sales to organizer net revenue</span></div>';
      h += '<span class="fin-pill green">' + icon('fas fa-percent') + ' ' + commRate + '% Commission Model</span>';
      h += '</div>';

      h += '<div class="fin-waterfall-grid">';
      h += '<div class="fin-wf-step"><span class="lbl">Gross Sales</span><span class="val">' + money(o.gross_revenue) + '</span></div>';
      h += '<div class="fin-wf-op">−</div>';
      h += '<div class="fin-wf-step"><span class="lbl">Platform Fee (' + commRate + '%)</span><span class="val" style="color:var(--ecc-amber);">' + money(o.platform_fee) + '</span></div>';
      h += '<div class="fin-wf-op">−</div>';
      h += '<div class="fin-wf-step"><span class="lbl">Processing Fee</span><span class="val" style="color:var(--ecc-text-dim);">' + money(o.processing_fee || 0) + '</span></div>';
      h += '<div class="fin-wf-op">−</div>';
      h += '<div class="fin-wf-step"><span class="lbl">Refunds</span><span class="val" style="color:var(--ecc-purple);">' + money(o.refunds_total) + '</span></div>';
      h += '<div class="fin-wf-op">=</div>';
      h += '<div class="fin-wf-step result"><span class="lbl">Net Event Revenue</span><span class="val">' + money(o.net_revenue) + '</span></div>';
      h += '</div>';
      h += '</div>';

      // System Alerts
      if (o.alerts && o.alerts.length) {
        h += '<div style="margin-bottom:1.25rem">';
        o.alerts.forEach(function(a) {
          h += '<div class="fin-alert ' + (a.type === 'warn' ? 'warn' : (a.type === 'notice' ? 'notice' : 'info')) + '">' +
            '<span class="fin-alert-ico">' + icon(a.type === 'warn' ? 'fas fa-exclamation-triangle' : 'fas fa-info-circle') + '</span>' +
            '<div><b>' + esc(a.title) + '</b><p>' + esc(a.body) + '</p></div>' +
            '<span class="fin-spacer" style="flex:1"></span>' +
            '<button type="button" class="fin-btn fin-btn-line fin-btn-xs" onclick="FinanceControlCenter.loadTab(\'' + esc(a.action) + '\')">View ' + esc(a.action) + ' →</button></div>';
        });
        h += '</div>';
      }

      // Two Column Section: Events Breakdown & Payment Methods
      h += gridTwo(
        card('EVENT REVENUE BREAKDOWN', '<div id="fin-ov-events-box"><div class="fin-loading">' + icon('fas fa-spinner') + ' Loading events…</div></div>', '<button type="button" class="fin-btn fin-btn-ghost fin-btn-xs" onclick="FinanceControlCenter.loadTab(\'revenue\')">Detailed View →</button>'),
        card('PAYMENT METHODS', methodsBlock(o.payment_methods))
      );

      // Recent Activity Table
      h += card('RECENT FINANCIAL TRANSACTIONS', activityBlock(o.recent_activity), '<button type="button" class="fin-btn fin-btn-ghost fin-btn-xs" onclick="FinanceControlCenter.loadTab(\'transactions\')">All Transactions →</button>');

      renderContent('overview', h);
      state.loaded.overview = true;

      loadOverviewEventBreakdown();
    }).catch(function(e) { fail('overview', e); });
  }

  function loadOverviewEventBreakdown() {
    getJson('revenue', { range: '30d' }).then(function(rev) {
      var box = document.getElementById('fin-ov-events-box');
      if (!box) return;
      var events = rev.by_event || [];
      if (!events.length) {
        box.innerHTML = '<div class="fin-empty">No revenue generated across your events yet.</div>';
        return;
      }
      var h = '<table class="fin-table"><thead><tr><th>Event Title</th><th style="text-align:center;">Tickets</th><th>Gross</th><th>Net Revenue</th><th>Action</th></tr></thead><tbody>';
      events.forEach(function(ev) {
        h += '<tr>' +
          '<td><strong style="color:var(--ecc-text-bright);">' + esc(ev.event) + '</strong></td>' +
          '<td style="text-align:center;font-weight:700;">' + ev.tickets + '</td>' +
          '<td>' + money(ev.gross) + '</td>' +
          '<td><b style="color:var(--ecc-green);">' + money(ev.net) + '</b></td>' +
          '<td><button type="button" class="fin-btn fin-btn-line fin-btn-xs" onclick="FinanceControlCenter.loadTab(\'transactions\');">View Finance</button></td>' +
          '</tr>';
      });
      h += '</tbody></table>';
      box.innerHTML = h;
    }).catch(function() {});
  }

  function methodsBlock(list) {
    if (!list || !list.length) return '<div class="fin-empty">No completed payments recorded.</div>';
    var h = '<div style="display:flex;flex-direction:column;gap:0.75rem;">';
    list.forEach(function(m) {
      var pctVal = (Number(m.percent) || 0).toFixed(1);
      h += '<div>' +
        '<div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:0.25rem;">' +
          '<strong>' + esc(m.method) + '</strong>' +
          '<span style="color:var(--ecc-text-dim);">' + money(m.amount) + ' (' + pctVal + '%)</span>' +
        '</div>' +
        '<div style="height:8px;background:var(--ecc-surface-3);border-radius:10px;overflow:hidden;">' +
          '<div style="width:' + pctVal + '%;height:100%;background:linear-gradient(90deg,var(--ecc-primary),#f59e0b);border-radius:10px;"></div>' +
        '</div>' +
      '</div>';
    });
    return h + '</div>';
  }

  function activityBlock(list) {
    if (!list || !list.length) return '<div class="fin-empty">No activity recorded yet.</div>';
    var h = '<table class="fin-table"><thead><tr><th>Type</th><th>Description</th><th>Reference</th><th>Amount</th><th>Date & Time</th></tr></thead><tbody>';
    list.forEach(function(a) {
      var isPayment = a.type === 'payment';
      var pill = isPayment ? '<span class="fin-pill green">' + icon('fas fa-check') + ' Paid</span>' : '<span class="fin-pill purple">' + icon('fas fa-undo') + ' Refund</span>';
      h += '<tr>' +
        '<td>' + pill + '</td>' +
        '<td><strong>' + esc(a.title) + '</strong><div style="font-size:0.68rem;color:var(--ecc-text-dim);">' + esc(a.method || a.reason || '') + '</div></td>' +
        '<td class="fin-mono">' + esc(a.ref || '—') + '</td>' +
        '<td><b style="font-size:0.82rem;color:' + (isPayment ? 'var(--ecc-text-bright)' : 'var(--ecc-rose)') + ';">' + money(a.amount, true) + '</b></td>' +
        '<td style="font-size:0.72rem;color:var(--ecc-text-dim);">' + dateTime(a.at) + '</td>' +
        '</tr>';
    });
    return h + '</tbody></table>';
  }

  /* ── 2. TRANSACTIONS LEDGER ────────────────────────────────────── */

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
    var mOpts = '<option value="">All payment methods</option>';
    METHODS.forEach(function(m) {
      mOpts += '<option value="' + esc(m) + '"' + (state.tx.filters.method === m ? ' selected' : '') + '>' + esc(m) + '</option>';
    });
    var sOpts = '<option value="">All statuses</option>';
    STATUSES.forEach(function(s) {
      sOpts += '<option value="' + s + '"' + (state.tx.filters.status === s ? ' selected' : '') + '>' + s + '</option>';
    });

    h += '<div class="fin-filters">' +
      '<input class="fin-input" id="fin-tx-q" placeholder="Search reference, ticket ID, customer..." value="' + esc(state.tx.filters.q) + '" onkeydown="if(event.key===\'Enter\')FinanceControlCenter.txApply()">' +
      '<select id="fin-tx-event" class="fin-input">' + opts + '</select>' +
      '<select id="fin-tx-status" class="fin-input">' + sOpts + '</select>' +
      '<select id="fin-tx-method" class="fin-input">' + mOpts + '</select>' +
      '<input class="fin-input" id="fin-tx-from" type="date" value="' + esc(state.tx.filters.from) + '" style="min-width:130px">' +
      '<input class="fin-input" id="fin-tx-to" type="date" value="' + esc(state.tx.filters.to) + '" style="min-width:130px">' +
      '<button type="button" class="fin-btn fin-btn-primary" onclick="FinanceControlCenter.txApply()">' + icon('fas fa-search') + ' Apply</button>' +
      '<button type="button" class="fin-btn fin-btn-ghost" onclick="FinanceControlCenter.txReset()">Reset</button>' +
      '<span style="flex:1"></span>' +
      '<button type="button" class="fin-btn fin-btn-line" onclick="FinanceControlCenter.txExport()">' + icon('fas fa-download') + ' Export CSV</button></div>';

    if (!t.items || !t.items.length) {
      h += '<div class="fin-card"><div class="fin-empty">No transactions match your search filters.</div></div>';
      renderContent('transactions', h);
      return;
    }

    h += '<div class="fin-card"><div class="fin-card-bd" style="padding:0;"><table class="fin-table"><thead><tr>' +
      '<th>Transaction Ref</th><th>Customer</th><th>Event</th><th>Gross Amount</th><th>Status</th><th>Method</th><th>Date</th><th>Action</th></tr></thead><tbody>';
    
    t.items.forEach(function(r) {
      h += '<tr class="clickable" onclick="FinanceControlCenter.txOpen(\'' + esc(r.reference) + '\')">' +
        '<td class="fin-mono"><strong>' + esc(r.reference) + '</strong></td>' +
        '<td><strong>' + esc(r.customer_name || 'Customer') + '</strong></td>' +
        '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(r.event) + '</td>' +
        '<td><b style="font-size:0.85rem;color:var(--ecc-primary);">' + money(r.amount) + '</b></td>' +
        '<td>' + statusPill(r.status) + '</td>' +
        '<td><span class="ecc-chip" style="font-size:0.62rem;">' + esc(r.method) + '</span></td>' +
        '<td style="white-space:nowrap;font-size:0.72rem;color:var(--ecc-text-dim);">' + dateTime(r.date) + '</td>' +
        '<td><button type="button" class="fin-btn fin-btn-line fin-btn-xs">Details →</button></td></tr>';
    });

    h += '</tbody></table></div></div>';

    var pages = Math.max(1, Math.ceil(t.total / t.limit));
    var cur = Math.floor(t.offset / t.limit) + 1;
    h += '<div class="fin-pager">Total: ' + t.total + ' transactions ' +
      '<button type="button" class="fin-btn fin-btn-ghost fin-btn-xs" onclick="FinanceControlCenter.txPage(-1)"' + (cur <= 1 ? ' disabled' : '') + '>← Prev</button>' +
      '<span>Page ' + cur + ' of ' + pages + '</span>' +
      '<button type="button" class="fin-btn fin-btn-ghost fin-btn-xs" onclick="FinanceControlCenter.txPage(1)"' + (cur >= pages ? ' disabled' : '') + '>Next →</button></div>';
    
    renderContent('transactions', h);
  }

  /* ── Transaction Detail Drawer ────────────────────────────────── */

  function txOpen(ref) {
    getJson('transaction_detail', { ref: ref }).then(function(d) {
      var h = '';
      h += '<div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;padding-bottom:0.75rem;border-bottom:1px solid var(--ecc-border);">' +
        '<div style="width:40px;height:40px;border-radius:12px;background:var(--ecc-primary-light);color:var(--ecc-primary);display:grid;place-items:center;font-size:1.1rem;">' + icon('fas fa-receipt') + '</div>' +
        '<div><b style="font-size:0.95rem;color:var(--ecc-text-bright);display:block;">' + esc(d.reference) + '</b>' +
        '<small style="color:var(--ecc-text-dim);font-size:0.72rem;">' + esc(d.event) + '</small></div>' +
        '<span style="flex:1"></span>' + statusPill(d.status) + '</div>';

      h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1.1rem;">';
      h += '<div class="fin-kpi" style="margin:0;"><label>Gross Sales</label><b style="font-size:1.1rem;">' + money(d.gross) + '</b></div>';
      h += '<div class="fin-kpi amber" style="margin:0;"><label>Platform Fee</label><b style="font-size:1.1rem;">' + money(d.platform_fee) + '</b></div>';
      h += '<div class="fin-kpi purple" style="margin:0;"><label>Refunds</label><b style="font-size:1.1rem;">' + money(d.refunds) + '</b></div>';
      h += '<div class="fin-kpi green" style="margin:0;"><label>Net Revenue</label><b style="font-size:1.1rem;">' + money(d.net) + '</b></div>';
      h += '</div>';

      h += '<div class="fin-doc-line"><span>Payment Method</span><b>' + esc(d.method) + '</b></div>';
      h += '<div class="fin-doc-line"><span>Commission Rate</span><b>' + d.rate + '%</b></div>';
      if (d.card_reference && d.card_reference !== '—') h += '<div class="fin-doc-line"><span>Gateway Reference</span><b class="fin-mono">' + esc(d.card_reference) + '</b></div>';
      h += '<div class="fin-doc-line"><span>Booking ID</span><b class="fin-mono">' + esc(d.booking_id) + '</b></div>';
      h += '<div class="fin-doc-line"><span>Transaction Date</span><b>' + dateTime(d.date) + '</b></div>';

      if (d.tickets && d.tickets.length) {
        h += '<h4 style="margin:1.1rem 0 0.5rem;font-size:0.72rem;font-weight:900;text-transform:uppercase;letter-spacing:0.08em;color:var(--ecc-text-dim);">Issued Digital Tickets</h4><div style="display:flex;flex-wrap:wrap;gap:0.4rem">';
        d.tickets.forEach(function(t) {
          h += '<span class="fin-pill ' + (/REFUNDED/i.test(t.status) ? 'purple' : 'green') + '">' + esc(t.type) + ' · ' + esc(t.status) + '</span>';
        });
        h += '</div>';
      }

      // Financial Lifecycle Timeline
      h += '<h4 style="margin:1.25rem 0 0.5rem;font-size:0.72rem;font-weight:900;text-transform:uppercase;letter-spacing:0.08em;color:var(--ecc-text-dim);">Payment & Ledger Timeline</h4>';
      h += '<ul class="fin-timeline">';
      if (d.timeline && d.timeline.length) {
        d.timeline.forEach(function(t) {
          h += '<li><b>' + esc(t.label) + '</b>' + dateTime(t.at) + (t.amount ? ' · ' + money(t.amount) : '') + '</li>';
        });
      } else {
        h += '<li><b>Payment Initiated</b>' + dateTime(d.date) + '</li>';
        h += '<li><b>Authorization & Gateway Settlement</b>' + dateTime(d.date) + '</li>';
        h += '<li><b>Payment Confirmed</b>' + dateTime(d.date) + ' · ' + money(d.gross) + '</li>';
        h += '<li><b>Ticket Issued & Ledger Recorded</b>' + dateTime(d.date) + '</li>';
      }
      h += '</ul>';

      var dl = document.getElementById('fin-drawer');
      document.getElementById('fin-drawer-title').textContent = 'Transaction ' + d.reference;
      document.getElementById('fin-drawer-bd').innerHTML = h;
      if (dl) dl.classList.add('open');
    }).catch(function(e) { toast(e.message || 'Failed to load transaction details.', true); });
  }

  function drawerClose() {
    var dl = document.getElementById('fin-drawer');
    if (dl) dl.classList.remove('open');
  }

  /* ── 3. REVENUE TAB ───────────────────────────────────────────── */

  function loadRevenue() {
    busy('revenue');
    getJson('revenue', { range: state.revenue.range, from: state.revenue.from, to: state.revenue.to }).then(function(r) {
      renderRevenue(r);
      state.loaded.revenue = true;
    }).catch(function(e) { fail('revenue', e); });
  }

  function renderRevenue(r) {
    var h = '';
    h += '<div class="fin-filters" style="align-items:center;">';
    [['7d', '7 Days'], ['30d', '30 Days'], ['90d', '90 Days'], ['custom', 'Custom Date']].forEach(function(c) {
      h += '<button type="button" class="fin-btn ' + (state.revenue.range === c[0] ? 'fin-btn-primary' : 'fin-btn-ghost') + ' fin-btn-xs" onclick="FinanceControlCenter.revRange(\'' + c[0] + '\')">' + c[1] + '</button>';
    });
    if (state.revenue.range === 'custom') {
      h += '<input class="fin-input" type="date" id="fin-rev-from" value="' + esc(state.revenue.from) + '" style="min-width:130px">' +
        '<input class="fin-input" type="date" id="fin-rev-to" value="' + esc(state.revenue.to) + '" style="min-width:130px">' +
        '<button type="button" class="fin-btn fin-btn-primary fin-btn-xs" onclick="FinanceControlCenter.revCustom()">Apply</button>';
    }
    h += '<span style="flex:1"></span><small style="color:var(--ecc-text-dim);font-size:0.72rem;font-weight:700;">Period: ' + date(r.from) + ' → ' + date(r.to) + '</small></div>';

    // Revenue Trend Chart & Event Breakdown
    h += gridTwo(
      card('REVENUE PERFORMANCE TREND', revenueChart(r.series), '<small class="fin-chart-legend"><span><i style="background:var(--ecc-primary)"></i>Gross Sales</span><span><i style="background:var(--ecc-green)"></i>Net Revenue</span></small>'),
      card('REVENUE BY EVENT', bars(r.by_event, function(e) { return e.net; }, function(e) { return e.event; }, function(e) { return money(e.gross) + ' gross'; }))
    );

    // Ticket Type Revenue Breakdown
    h += card('REVENUE BY TICKET TYPE', bars(r.by_ticket_type, function(t) { return t.gross; }, function(t) { return t.type; }, function(t) { return t.count + ' bookings · share ' + (Number(t.share) || 0).toFixed(1) + '%'; }));

    renderContent('revenue', h);
  }

  function revenueChart(series) {
    if (!series || !series.length) return '<div class="fin-empty">No revenue recorded in this timeframe.</div>';
    var w = Math.max(640, series.length * 30);
    var hgt = 190, padL = 8, padB = 24, padT = 12;
    var maxV = Math.max.apply(null, series.map(function(s) { return s.gross; })) || 1;
    var innerW = w - padL * 2, innerH = hgt - padT - padB;
    var bw = Math.max(3, (innerW / series.length) - 2);
    var barsHtml = '', line = '';
    var step = Math.ceil(series.length / 14);

    series.forEach(function(s, i) {
      var x = padL + i * (innerW / series.length);
      var hG = s.gross / maxV * innerH;
      var hN = s.net / maxV * innerH;
      var yG = padT + innerH - hG;
      barsHtml += '<rect x="' + x.toFixed(1) + '" y="' + yG.toFixed(1) + '" width="' + bw + '" height="' + Math.max(1, hG).toFixed(1) + '" rx="2" fill="rgba(230,57,70,.8)"><title>' + s.date + ' · ' + money(s.gross) + '</title></rect>';
      if (s.net > 0) barsHtml += '<rect x="' + (x + bw + 2).toFixed(1) + '" y="' + (padT + innerH - hN).toFixed(1) + '" width="' + Math.max(1, bw - 3).toFixed(1) + '" height="' + Math.max(1, hN).toFixed(1) + '" rx="2" fill="rgba(16,185,129,.85)"><title>' + s.date + ' · ' + money(s.net) + '</title></rect>';
      if (i % step === 0 || i === series.length - 1) {
        var lbl = s.date.split('-').slice(1).reverse().join('/');
        barsHtml += '<text x="' + x.toFixed(1) + '" y="' + (hgt - 7) + '" font-size="9" fill="currentColor" opacity="0.55">' + lbl + '</text>';
      }
    });

    line = '<polyline points="' + series.map(function(s, i) {
      var x = padL + i * (innerW / series.length) + innerW / series.length / 2;
      var y = padT + innerH - (s.net / maxV * innerH);
      return x.toFixed(1) + ',' + y.toFixed(1);
    }).join(' ') + '" fill="none" stroke="#10b981" stroke-width="1.8" opacity="0.85"><title>Net Revenue</title></polyline>';

    return '<div class="fin-chart-wrap"><svg width="' + w + '" height="' + hgt + '" viewBox="0 0 ' + w + ' ' + hgt + '" style="display:block">' + barsHtml + line + '</svg></div>';
  }

  function bars(list, valFn, labelFn, subFn) {
    if (!list || !list.length) return '<div class="fin-empty">No breakdown data available.</div>';
    var maxV = Math.max.apply(null, list.map(valFn)) || 1;
    var h = '<div>';
    list.forEach(function(item) {
      var v = valFn(item);
      var pctVal = (v / maxV * 100).toFixed(1);
      h += '<div class="fin-bar-row">' +
        '<span class="fin-bar-label" title="' + esc(labelFn(item)) + '">' + esc(labelFn(item)) + '</span>' +
        '<span class="fin-bar-track"><span class="fin-bar-fill" style="width:' + pctVal + '%"></span></span>' +
        '<span class="fin-bar-val" title="' + esc(subFn(item)) + '">' + money(v, true) + '</span>' +
        '</div>';
    });
    return h + '</div>';
  }

  /* ── 4. SETTLEMENTS TAB ────────────────────────────────────────── */

  function loadSettlements() {
    busy('settlements');
    getJson('settlements').then(function(s) {
      renderSettlements(s);
      state.loaded.settlements = true;
    }).catch(function(e) { fail('settlements', e); });
  }

  function renderSettlements(s) {
    var h = '';

    // 4 Top KPI Cards
    h += '<div class="fin-kpis">';
    h += kpi('Available Balance', money(s.available_balance), 'ready for payout withdrawal', 'fas fa-piggy-bank', 'green');
    h += kpi('Pending Settlement', money(s.pending_net), s.pending_count + ' completed transactions', 'fas fa-clock', 'cyan');
    h += kpi('Paid Out Total', money(s.paid_out_total), 'total transferred to bank/mobile money', 'fas fa-university', '');
    h += kpi('Committed Withdrawals', money(s.withdrawn_total), 'withdrawal processing or completed', 'fas fa-hand-holding-usd', 'purple');
    h += '</div>';

    // Settlement Lifecycle Stepper Card
    h += '<div class="fin-card" style="padding:1.25rem;">';
    h += '<strong style="font-size:0.88rem;display:block;margin-bottom:0.2rem;">SETTLEMENT LIFECYCLE & PAYOUT FLOW</strong>';
    h += '<span style="font-size:0.72rem;color:var(--ecc-text-dim);display:block;margin-bottom:1rem;">How organizer revenue moves safely from customer payment to your bank/mobile money account</span>';

    h += '<div class="fin-lifecycle">';
    h += lcStep(1, 'Customer Payment', true);
    h += '<span class="fin-lc-arrow">→</span>';
    h += lcStep(2, 'Payment Confirmed', true);
    h += '<span class="fin-lc-arrow">→</span>';
    h += lcStep(3, 'Revenue Recorded', true);
    h += '<span class="fin-lc-arrow">→</span>';
    h += lcStep(4, 'Settlement Pending', s.pending_count > 0, true);
    h += '<span class="fin-lc-arrow">→</span>';
    h += lcStep(5, 'Eligible Balance', s.available_balance > 0);
    h += '<span class="fin-lc-arrow">→</span>';
    h += lcStep(6, 'Withdrawal Requested', (s.withdrawals || []).length > 0);
    h += '<span class="fin-lc-arrow">→</span>';
    h += lcStep(7, 'Payout Paid', s.paid_out_total > 0);
    h += '</div>';
    h += '</div>';

    // Actions Toolbar
    h += '<div style="display:flex;gap:0.6rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:center;">' +
      '<button type="button" class="fin-btn fin-btn-primary" onclick="FinanceControlCenter.batchOpen()">' + icon('fas fa-plus') + ' Create Settlement Batch</button>' +
      '<button type="button" class="fin-btn fin-btn-line" onclick="FinanceControlCenter.withdrawalOpen()">' + icon('fas fa-university') + ' Request Withdrawal</button>' +
      '<button type="button" class="fin-btn fin-btn-line" onclick="FinanceControlCenter.accountsOpen()">' + icon('fas fa-wallet') + ' Payout Accounts</button>' +
      '<span style="flex:1"></span>' +
      '<small style="color:var(--ecc-text-dim);font-size:0.72rem;font-weight:700;">Open liability buffer: ' + money(s.worst_case) + '</small>' +
      '</div>';

    // Settlement Batches Table
    h += card('SETTLEMENT BATCHES', s.batches && s.batches.length ? batchTable(s.batches) : '<div class="fin-empty">No settlement batches created yet. Click "Create Settlement Batch" above to lock in pending revenue.</div>');

    // Withdrawal Requests Table
    h += card('WITHDRAWAL REQUESTS', withdrawalTable(s.withdrawals || []));

    renderContent('settlements', h);
  }

  function lcStep(num, label, isDone, isActive) {
    var cls = isDone ? 'done' : (isActive ? 'active' : '');
    return '<div class="fin-lc-step ' + cls + '">' +
      '<div class="dot">' + (isDone ? '✓' : num) + '</div>' +
      '<span class="lbl">' + esc(label) + '</span>' +
      '</div>';
  }

  function batchTable(list) {
    var h = '<table class="fin-table"><thead><tr><th>Period</th><th>Gross Amount</th><th>Platform Fee</th><th>Refunds</th><th>Net Payout</th><th>Status</th><th>Paid Date</th><th>Action</th></tr></thead><tbody>';
    list.forEach(function(b) {
      h += '<tr><td><strong>' + date(b.period_start) + ' → ' + date(b.period_end) + '</strong></td>' +
        '<td>' + money(b.gross_amount) + '</td><td>' + money(b.platform_fee) + '</td><td>' + money(b.refunds_total) + '</td>' +
        '<td><b style="color:var(--ecc-green);font-size:0.85rem;">' + money(b.net_amount) + '</b></td><td>' + statusPill(b.status) + '</td>' +
        '<td style="font-size:0.72rem;color:var(--ecc-text-dim);">' + (b.paid_at ? date(b.paid_at) : '—') + '</td>' +
        '<td>' + (b.status === 'ELIGIBLE' ? '<button type="button" class="fin-btn fin-btn-line fin-btn-xs" onclick="FinanceControlCenter.batchPaid(\'' + esc(b.id) + '\')">Mark Paid</button>' : '') + '</td></tr>';
    });
    return h + '</tbody></table>';
  }

  function withdrawalTable(list) {
    if (!list || !list.length) return '<div class="fin-empty">No withdrawal requests submitted yet.</div>';
    var h = '<table class="fin-table"><thead><tr><th>Requested At</th><th>Amount</th><th>Method</th><th>Destination</th><th>Reference</th><th>Status</th></tr></thead><tbody>';
    list.forEach(function(w) {
      h += '<tr>' +
        '<td>' + dateTime(w.requested_at) + '</td>' +
        '<td><b style="font-size:0.85rem;color:var(--ecc-text-bright);">' + money(w.amount) + '</b></td>' +
        '<td><span class="ecc-chip">' + esc(w.method) + '</span></td>' +
        '<td>' + esc(w.destination || '—') + '</td>' +
        '<td class="fin-mono">' + esc(w.reference || '—') + '</td>' +
        '<td>' + statusPill(w.status) + '</td>' +
        '</tr>';
    });
    return h + '</tbody></table>';
  }

  /* ── Modal Dialogs: Batches & Withdrawals ─────────────────────── */

  function batchOpen() {
    var o = state.overview || {};
    var s = o.settlement || {};
    var rate = o.commission_rate || 10;
    var estGross = s.pending_net > 0 ? Math.round(s.pending_net / (1 - rate / 100)) : 0;
    var h = '<p style="font-size:0.78rem;color:var(--ecc-text-dim);line-height:1.55;margin-bottom:1rem;">Creating a settlement batch locks in your pending event ticket sales, computes the 10% platform commission and processed refunds, and adds the net funds to your Available Balance for withdrawal.</p>';
    h += '<div class="fin-doc-line"><span>Pending Completed Transactions</span><b>' + s.pending_count + '</b></div>';
    h += '<div class="fin-doc-line"><span>Estimated Pending Gross</span><b>' + money(estGross, true) + '</b></div>';
    h += '<div class="fin-doc-line total"><span>Estimated Net Settlement</span><b>' + money(s.pending_net) + '</b></div>';
    
    if (!s.pending_count) h = '<div class="ecc-tk-empty">There are no pending completed transactions to settle right now.</div>';

    modal('Create Settlement Batch', h, [
      { label: 'Cancel', cls: 'fin-btn-ghost', fn: 'FinanceControlCenter.modalClose()' },
      { label: 'Confirm & Create Batch', cls: 'fin-btn-primary', fn: 'FinanceControlCenter.batchCreate()' }
    ]);
  }

  function batchCreate() {
    postJson({ action: 'batch_create' }).then(function() {
      state.overview = null;
      state.loaded.settlements = false;
      state.loaded.overview = false;
      modalClose();
      toast('Settlement batch created! Available balance updated.');
      loadSettlements();
    }).catch(function(e) { toast(e.message, true); });
  }

  function batchPaid(id) {
    var ref = window.prompt('Enter payout confirmation reference (Bank transaction ID / Mobile Money ref):', '');
    if (ref === null) return;
    postJson({ action: 'batch_paid', batch_id: id, reference: String(ref || '').trim() }).then(function() {
      toast('Settlement batch marked as paid out.');
      loadSettlements();
    }).catch(function(e) { toast(e.message, true); });
  }

  function withdrawalOpen() {
    var s = (state.overview || {}).settlement || {};
    if (!s.available_balance || s.available_balance <= 0) {
      toast('No funds available for withdrawal. Please create a settlement batch first.', true);
      return;
    }

    getJson('accounts').then(function(acc) {
      var items = acc.items || [];
      var opts = '<option value="">' + (items.length ? 'Select saved payout account' : 'Enter account details manually') + '</option>';
      items.forEach(function(a) {
        opts += '<option value="' + esc(a.id) + '">' + esc(a.label) + ' · ' + esc(a.account_number_masked) + '</option>';
      });

      var h = '<div style="background:var(--ecc-surface-2);padding:0.75rem 1rem;border-radius:10px;border:1px solid var(--ecc-border);margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;">' +
        '<span style="font-size:0.75rem;color:var(--ecc-text-dim);">Available Balance:</span>' +
        '<strong style="font-size:1.15rem;color:var(--ecc-green);">' + money(s.available_balance) + '</strong>' +
        '</div>';

      h += '<div class="fin-form-row"><label>Withdrawal Amount (MWK)</label><input id="fin-wdl-amount" type="number" min="1" max="' + s.available_balance + '" step="500" placeholder="e.g. 500000"></div>';
      h += '<div class="fin-form-row"><label>Saved Payout Account</label><select id="fin-wdl-account">' + opts + '</select></div>';
      h += '<div class="fin-form-row"><label>Payout Method</label><select id="fin-wdl-method"><option value="BANK">Bank Transfer</option><option value="MOBILE_MONEY">Mobile Money (Airtel / Mpamba)</option></select></div>';
      h += '<div class="fin-form-row"><label>Destination Account / Name</label><input id="fin-wdl-dest" placeholder="e.g. National Bank of Malawi — John Organizer"></div>';
      h += '<div class="fin-form-row"><label>Account Number / Phone</label><input id="fin-wdl-ref" placeholder="e.g. 1002938471 or 0991234567"></div>';

      modal('Request Payout Withdrawal', h, [
        { label: 'Cancel', cls: 'fin-btn-ghost', fn: 'FinanceControlCenter.modalClose()' },
        { label: '+ Add Payout Account', cls: 'fin-btn-line', fn: 'FinanceControlCenter.accountsOpen()' },
        { label: 'Submit Withdrawal Request', cls: 'fin-btn-primary', fn: 'FinanceControlCenter.withdrawalSubmit()' }
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
    if (!amount || amount <= 0) { toast('Please enter a valid withdrawal amount.', true); return; }

    postJson(payload).then(function() {
      toast('Withdrawal request submitted for ' + money(amount) + '!');
      state.loaded.settlements = false;
      state.loaded.overview = false;
      modalClose();
      loadSettlements();
    }).catch(function(e) { toast(e.message, true); });
  }

  /* ── 5. REFUNDS TAB ───────────────────────────────────────────── */

  function loadRefunds() {
    busy('refunds');
    getJson('refunds').then(function(r) {
      var h = '';
      var sum = r.summary || {};

      h += '<div class="fin-kpis">';
      h += kpi('Pending Approval', sum.pending || 0, 'requests waiting for organizer review', 'fas fa-clock', 'amber');
      h += kpi('Processed Refunds', sum.processed || 0, 'refunded ticket orders', 'fas fa-undo', 'purple');
      h += kpi('Total Refund Value', money(sum.value || 0), 'processed + pending refund amount', 'fas fa-hand-holding-usd', '');
      h += '</div>';

      // Refund Lifecycle Stepper
      h += '<div class="fin-card" style="padding:1.25rem;">';
      h += '<strong style="font-size:0.88rem;display:block;margin-bottom:0.2rem;">REFUND LIFECYCLE WORKFLOW</strong>';
      h += '<span style="font-size:0.72rem;color:var(--ecc-text-dim);display:block;margin-bottom:1rem;">Structured authorization pipeline for customer refunds</span>';

      h += '<div class="fin-lifecycle">';
      h += lcStep(1, 'Ticket Purchase', true);
      h += '<span class="fin-lc-arrow">→</span>';
      h += lcStep(2, 'Refund Requested', (sum.pending || 0) > 0, true);
      h += '<span class="fin-lc-arrow">→</span>';
      h += lcStep(3, 'Organizer Approval', true);
      h += '<span class="fin-lc-arrow">→</span>';
      h += lcStep(4, 'Payment Processing', true);
      h += '<span class="fin-lc-arrow">→</span>';
      h += lcStep(5, 'Customer Refunded', (sum.processed || 0) > 0);
      h += '</div>';
      h += '</div>';

      if (!r.items || !r.items.length) {
        h += '<div class="fin-card"><div class="fin-empty">No refund requests or processed refunds recorded.</div></div>';
      } else {
        h += '<div class="fin-card"><div class="fin-card-bd" style="padding:0;"><table class="fin-table"><thead><tr>' +
          '<th>Refund ID</th><th>Event</th><th>Booking ID</th><th>Reason</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>';
        r.items.forEach(function(x) {
          h += '<tr>' +
            '<td class="fin-mono"><strong>' + esc(x.id) + '</strong></td>' +
            '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(x.event) + '</td>' +
            '<td class="fin-mono">' + esc(x.booking) + '</td>' +
            '<td>' + esc(x.reason) + '</td>' +
            '<td><b style="color:var(--ecc-rose);">' + money(x.amount) + '</b></td>' +
            '<td>' + statusPill(x.status) + '</td>' +
            '<td style="font-size:0.72rem;color:var(--ecc-text-dim);">' + (x.decided_at ? dateTime(x.decided_at) : '—') + '</td>' +
            '</tr>';
        });
        h += '</tbody></table></div></div>';
      }

      renderContent('refunds', h);
      state.loaded.refunds = true;
    }).catch(function(e) { fail('refunds', e); });
  }

  /* ── 6. FEES TAB ──────────────────────────────────────────────── */

  function loadFees() {
    busy('fees');
    getJson('fees').then(function(f) {
      var h = '';
      h += '<div class="fin-alert info" style="margin-bottom:1.25rem;">' +
        '<span class="fin-alert-ico">' + icon('fas fa-percent') + '</span>' +
        '<div><b>Platform Commission Transparency (' + f.rate + '%)</b>' +
        '<p>Platform fee is calculated automatically per paid ticket transaction. No hidden processing costs are charged to your customers.</p></div>' +
        '</div>';

      h += '<div class="fin-kpis">';
      h += kpi('Gross Ticket Sales', money(f.gross), 'total paid customer volume', 'fas fa-wallet', '');
      h += kpi('Platform Commission', money(f.commission), f.rate + '% of gross sales', 'fas fa-percent', 'amber');
      h += kpi('Refund Charges', money(f.refund_charges), 'processed refund adjustments', 'fas fa-undo', 'purple');
      h += kpi('Total Deductions', money(f.total), 'commission + charges', 'fas fa-minus-circle', 'rose');
      h += '</div>';

      h += card('FEE DEDUCTIONS BY EVENT', bars(f.by_event, function(e) { return e.fees; }, function(e) { return e.event; }, function(e) { return money(e.gross) + ' gross · ' + f.rate + '% fee'; }));

      renderContent('fees', h);
      state.loaded.fees = true;
    }).catch(function(e) { fail('fees', e); });
  }

  /* ── 7. INVOICES & DOCUMENTS TAB ───────────────────────────────── */

  function loadDocuments() {
    busy('documents');
    getJson('documents').then(function(d) {
      var h = '';
      h += '<div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-bottom:1.25rem;">';
      [['SETTLEMENT', 'Settlement Statement'], ['COMMISSION', 'Commission Statement'], ['REFUND', 'Refund Statement'], ['EVENT_STATEMENT', 'Event Financial Statement']].forEach(function(g) {
        h += '<button type="button" class="fin-btn fin-btn-primary" onclick="FinanceControlCenter.docGen(\'' + g[0] + '\')">' + icon('fas fa-plus') + ' Generate ' + g[1] + '</button>';
      });
      h += '</div>';

      if (!d.items || !d.items.length) {
        h += '<div class="fin-card"><div class="fin-empty">No financial documents generated yet. Click any button above to generate a statement.</div></div>';
      } else {
        h += '<div class="fin-card"><div class="fin-card-bd" style="padding:0;"><table class="fin-table"><thead><tr>' +
          '<th>Reference</th><th>Document Type</th><th>Period</th><th>Issued Date</th><th>Action</th></tr></thead><tbody>';
        d.items.forEach(function(x) {
          h += '<tr>' +
            '<td class="fin-mono"><strong>' + esc(x.reference) + '</strong></td>' +
            '<td>' + statusPill(x.doc_type) + '</td>' +
            '<td>' + (x.period_start ? date(x.period_start) + ' → ' + date(x.period_end) : '—') + '</td>' +
            '<td style="font-size:0.72rem;color:var(--ecc-text-dim);">' + dateTime(x.created_at) + '</td>' +
            '<td><button type="button" class="fin-btn fin-btn-line fin-btn-xs" onclick="FinanceControlCenter.docView(\'' + esc(x.id) + '\')">' + icon('fas fa-eye') + ' View Statement</button></td>' +
            '</tr>';
        });
        h += '</tbody></table></div></div>';
      }

      renderContent('documents', h);
      state.loaded.documents = true;
    }).catch(function(e) { fail('documents', e); });
  }

  function docGen(type) {
    var payload = { action: 'doc_generate', doc_type: type };
    if (type === 'EVENT_STATEMENT') {
      var opts = '<option value="">Select event...</option>';
      (state.events || []).forEach(function(ev) {
        opts += '<option value="' + esc(ev.event_id) + '">' + esc(ev.title) + '</option>';
      });
      var h = '<div class="fin-form-row"><label>Select Event</label><select id="fin-doc-event">' + opts + '</select></div>';
      modal('Generate Event Financial Statement', h, [
        { label: 'Cancel', cls: 'fin-btn-ghost', fn: 'FinanceControlCenter.modalClose()' },
        { label: 'Generate Statement', cls: 'fin-btn-primary', fn: 'FinanceControlCenter.docGenSubmit()' }
      ]);
      return;
    }
    postJson(payload).then(function() {
      toast('Financial statement generated!');
      loadDocuments();
    }).catch(function(e) { toast(e.message, true); });
  }

  function docGenSubmit() {
    var ev = document.getElementById('fin-doc-event').value;
    if (!ev) { toast('Please select an event first.', true); return; }
    postJson({ action: 'doc_generate', doc_type: 'EVENT_STATEMENT', event_id: ev }).then(function() {
      toast('Event financial statement generated!');
      modalClose();
      loadDocuments();
    }).catch(function(e) { toast(e.message, true); });
  }

  function docView(id) {
    getJson('document', { id: id }).then(function(d) {
      var p = d.payload || {};
      var h = '<div style="background:var(--ecc-surface-2);padding:1.25rem;border-radius:12px;border:1px solid var(--ecc-border);margin-bottom:1rem;">' +
        '<strong style="font-size:1.1rem;color:var(--ecc-text-bright);display:block;">' + esc(p.title || d.reference) + '</strong>' +
        '<span style="font-size:0.72rem;color:var(--ecc-text-dim);">Statement Ref: <strong>' + esc(d.reference) + '</strong> · Issued: ' + dateTime(d.created_at) + '</span>' +
        '</div>';

      (p.sections || []).forEach(function(sec) {
        h += '<div class="fin-doc-sec"><h4>' + esc(sec.heading) + '</h4>';
        (sec.lines || []).forEach(function(l) {
          h += '<div class="fin-doc-line' + (l.total ? ' total' : '') + '"><span>' + esc(l.label) + '</span><b>' + money(l.amount) + '</b></div>';
        });
        h += '</div>';
      });

      modal(d.doc_type + ' Statement', h, [
        { label: 'Close', cls: 'fin-btn-ghost', fn: 'FinanceControlCenter.modalClose()' },
        { label: 'Download Statement JSON', cls: 'fin-btn-line', fn: 'FinanceControlCenter.docDownload(\'' + esc(d.id) + '\',\'' + esc(d.reference) + '\')' }
      ]);
    }).catch(function(e) { toast(e.message, true); });
  }

  function docDownload(id, ref) {
    getJson('document', { id: id }).then(function(d) {
      var blob = new Blob([JSON.stringify(d.payload, null, 2)], { type: 'application/json' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'uthenga-statement-' + (ref || id) + '.json';
      document.body.appendChild(a);
      a.click();
      setTimeout(function() { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    }).catch(function(e) { toast(e.message || 'Download failed.', true); });
  }

  /* ── 8. RECONCILIATION TAB ─────────────────────────────────────── */

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

    h += '<div class="fin-alert ' + (ok ? 'info' : 'warn') + '" style="margin-bottom:1.25rem;">' +
      '<span class="fin-alert-ico">' + icon(ok ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle') + '</span>' +
      '<div><b>' + (ok ? 'Reconciliation Status: Books are Balanced ✓' : r.open_exceptions + ' Open Exception(s) Require Review') + '</b>' +
      '<p>Automated multi-way matching between ticket issuance, payment transactions, platform fees, refunds, and settlement ledgers.</p></div>' +
      '<span style="flex:1"></span>' +
      '<button type="button" class="fin-btn fin-btn-primary fin-btn-xs" onclick="FinanceControlCenter.reconRun()">' + icon('fas fa-sync-alt') + ' Run Reconciliation Check</button></div>';

    h += '<div class="fin-kpis">';
    h += kpi('Expected Gross Amount', money(r.expected_amount), 'derived from paid bookings', 'fas fa-calculator', '');
    h += kpi('Recorded Ledger Amount', money(r.recorded_amount), 'after fees & refunds', 'fas fa-book', '');
    h += kpi('Reconciliation Difference', money(r.difference), 'unmatched discrepancy space', 'fas fa-equals', r.difference > 0 ? 'amber' : 'green');
    h += kpi('Open Exceptions', r.open_exceptions || 0, 'unresolved items needing investigation', 'fas fa-flag', (r.open_exceptions || 0) > 0 ? 'rose' : 'green');
    h += '</div>';

    // 5 Matching Checks
    h += card('AUTOMATED MATCHING CHECKS', '<div class="fin-recon-list">' + (r.matches || []).map(function(m) {
      return '<div class="fin-recon-item ' + (m.ok ? 'ok' : 'bad') + '">' + icon(m.ok ? 'fas fa-check' : 'fas fa-times') + ' ' + esc(m.label) + '</div>';
    }).join('') + '</div>');

    getJson('exceptions').then(function(ex) {
      h += card('RECONCILIATION EXCEPTIONS LEDGER', ex.items && ex.items.length ? exceptionsTable(ex.items) : '<div class="fin-empty">No open reconciliation exceptions found. All transactions balance perfectly!</div>');
      renderContent('reconciliation', h);
    }).catch(function() { renderContent('reconciliation', h); });
  }

  function exceptionsTable(list) {
    var h = '<table class="fin-table"><thead><tr><th>Category</th><th>Reference</th><th>Expected Amount</th><th>Recorded Amount</th><th>Resolution Note</th><th>Action</th></tr></thead><tbody>';
    list.forEach(function(e) {
      h += '<tr>' +
        '<td>' + statusPill(e.category) + '</td>' +
        '<td class="fin-mono"><strong>' + esc(e.reference) + '</strong></td>' +
        '<td>' + money(e.expected_amount) + '</td>' +
        '<td>' + money(e.recorded_amount) + '</td>' +
        '<td style="max-width:220px;font-size:0.72rem;color:var(--ecc-text-dim);">' + esc(e.resolution_note) + '</td>' +
        '<td><button type="button" class="fin-btn fin-btn-line fin-btn-xs" onclick="FinanceControlCenter.excResolve(\'' + esc(e.id) + '\')">Resolve</button></td>' +
        '</tr>';
    });
    return h + '</tbody></table>';
  }

  function reconRun() {
    busy('reconciliation');
    postJson({ action: 'reconciliation_run' }).then(function(r) {
      toast('Reconciliation check executed successfully!');
      state.loaded.reconciliation = false;
      state.loaded.overview = false;
      loadReconciliation();
    }).catch(function(e) { toast(e.message, true); loadReconciliation(); });
  }

  function excResolve(id) {
    var note = window.prompt('Enter resolution explanation note:', 'Reviewed and verified against payment gateway statement.');
    if (note === null) return;
    postJson({ action: 'exception_resolve', id: id, note: String(note).trim() }).then(function() {
      toast('Reconciliation exception resolved.');
      state.loaded.reconciliation = false;
      loadReconciliation();
    }).catch(function(e) { toast(e.message, true); });
  }

  /* ── 9. FINANCE AI ASSISTANT ────────────────────────────────────── */

  function advisorToggle() {
    var el = document.getElementById('fin-adv');
    if (!el) return;
    var open = !el.classList.contains('open');
    el.classList.toggle('open', open);
    if (open) {
      var bodyEl = document.getElementById('fin-adv-body');
      if (!bodyEl.children.length) {
        bodyEl.innerHTML = '<div class="fin-adv-msg bot">Hi! I am your read-only Finance AI Assistant. I can analyze your revenue, platform fees, pending settlements, refunds, or reconciliation records. Ask me anything!</div>';
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
    bodyEl.insertAdjacentHTML('beforeend', '<div class="fin-adv-msg bot">Analyzing ledger evidence…</div>');
    bodyEl.scrollTop = bodyEl.scrollHeight;

    getJson('advisor', { message: msg }).then(function(a) {
      var last = bodyEl.querySelector('.fin-adv-msg.bot:last-child');
      if (last) last.textContent = String(a.message || '');
      
      var fol = document.getElementById('fin-adv-follow');
      fol.innerHTML = '';
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

  /* ── Payout Accounts Management ─────────────────────────────────── */

  function accountsOpen() {
    getJson('accounts').then(function(acc) {
      var h = '<div id="fin-acc-list">';
      (acc.items || []).forEach(function(a) {
        h += '<div class="fin-acc-item">' +
          '<span class="fin-acc-ico">' + icon(a.method === 'MOBILE_MONEY' ? 'fas fa-mobile-alt' : 'fas fa-university') + '</span>' +
          '<div style="flex:1;min-width:0"><b>' + esc(a.label) + '</b>' +
          '<small>' + esc(a.account_name) + ' · ' + esc(a.account_number_masked) + (a.provider ? ' · ' + esc(a.provider) : '') + '</small></div>' +
          (a.is_verified ? '<span class="fin-pill green">' + icon('fas fa-check') + ' Verified</span>' : '<span class="fin-pill amber">Unverified</span>') +
          '</div>';
      });
      if (!acc.items || !acc.items.length) h += '<div class="fin-empty" style="padding:0.5rem;">No payout accounts added yet.</div>';
      h += '</div>';

      h += '<div style="border-top:1px dashed var(--ecc-border);margin-top:1rem;padding-top:1rem;">';
      h += '<strong style="font-size:0.82rem;display:block;margin-bottom:0.75rem;">+ Add New Payout Account</strong>';
      h += '<div class="fin-form-row"><label>Payout Method</label><select id="fin-acc-method"><option value="BANK">Bank Transfer</option><option value="MOBILE_MONEY">Mobile Money</option></select></div>';
      h += '<div class="fin-form-row"><label>Account Label</label><input id="fin-acc-label" placeholder="e.g. Standard Bank Main Account"></div>';
      h += '<div class="fin-form-row"><label>Account Holder Name</label><input id="fin-acc-name" placeholder="Full registered account name"></div>';
      h += '<div class="fin-form-row"><label>Account / Phone Number</label><input id="fin-acc-number" placeholder="Account number or phone number"></div>';
      h += '<div class="fin-form-row"><label>Provider / Bank Name</label><input id="fin-acc-provider" placeholder="e.g. Standard Bank / Airtel Money"></div>';
      h += '</div>';

      modal('Saved Payout Accounts', h, [
        { label: 'Close', cls: 'fin-btn-ghost', fn: 'FinanceControlCenter.modalClose()' },
        { label: 'Save Account', cls: 'fin-btn-primary', fn: 'FinanceControlCenter.accountSave()' }
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
      is_default: 1
    };
    if (!payload.account_name.trim() || !payload.account_number.trim()) { toast('Account holder name and account number are required.', true); return; }

    postJson(payload).then(function() {
      toast('Payout account saved successfully!');
      accountsOpen();
    }).catch(function(e) { toast(e.message, true); });
  }

  /* ── Export & Filter Helpers ───────────────────────────────────── */

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
    if (!state.revenue.from || !state.revenue.to) { toast('Select both dates.', true); return; }
    state.loaded.revenue = false;
    loadRevenue();
  }

  /* ── Boot ──────────────────────────────────────────────────────── */

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