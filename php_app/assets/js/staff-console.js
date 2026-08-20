/* Staff — access-control center for the Events Control Center.
 * User -> organization -> role -> permission -> event scope -> allow/deny.
 * Backend enforcement: every mutation re-verifies the actor holds
 * staff-management permission; this UI only reflects what the API allows. */
(function () {
  'use strict';

  var root = document.getElementById('staff-root');
  if (!root) return;

  var base = (root.dataset.baseUrl || '/uthenga/').replace(/\/$/, '') + '/';
  var api = base + 'api/tie/vendor/events/staff.php';

  var state = {
    tab: 'overview',
    q: '',
    role: '', status: '', event: '', access: '', sort: 'recent',
    activeId: '',
    profileTab: 'overview',
    inviteStep: 1,
    bulk: [],
    matrixMode: 'events',
    activityScope: 'all',
    enums: null, events: [], roles: [], overview: null,
    docs: [], invites: [], activity: [], assignments: [], matrix: null, pool: [],
    detail: null
  };

  var ICONS = {
    users: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20c1-3.5 3.4-5 6.5-5s5.5 1.5 6.5 5"></path><circle cx="17.5" cy="9" r="2.7"></circle><path d="M16 15.6c2.8.3 4.9 1.8 5.7 4.4"></path></svg>',
    shield: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 3.5v5.2c0 5-3.4 9.2-8 10.8-4.6-1.6-8-5.8-8-10.8V5.5z"></path></svg>',
    mail: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="5" width="19" height="14" rx="2"></rect><path d="M3 6.5l9 7 9-7"></path></svg>',
    calendar: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M8 3v4M16 3v4M3 10h18"></path></svg>',
    search: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.5-4.5"></path></svg>',
    plus: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"></path></svg>',
    sliders: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"></path><circle cx="9" cy="7" r="2" fill="var(--ecc-bg)"></circle><circle cx="15" cy="12" r="2" fill="var(--ecc-bg)"></circle><circle cx="8" cy="17" r="2" fill="var(--ecc-bg)"></circle></svg>',
    clock: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>',
    edit: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 20l4.5-.8L20 7.5a2.1 2.1 0 0 0-3-3L5.8 15.5z"></path></svg>',
    eye: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>',
    x: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"></path></svg>',
    chevL: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M15 5l-7 7 7 7"></path></svg>',
    grid: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"></rect><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"></rect><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"></rect><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"></rect></svg>',
    table: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"></path><path d="M3 6v12M21 6v12"></path></svg>',
    key: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="14" r="4.5"></circle><path d="M11.5 10.5L20 2M17 5l3 3M14 8l2.5 2.5"></path></svg>'
  };

  function ic(name, size) {
    var s = ICONS[name] || '';
    if (size) s = s.replace(/width="\d+"/, 'width="' + size + '"').replace(/height="\d+"/, 'height="' + size + '"');
    return s;
  }

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function qs(params) {
    var out = [];
    for (var k in params) {
      if (params[k] !== '' && params[k] != null) out.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
    }
    return out.join('&');
  }

  function get(action, params) {
    var url = api + '?' + qs(Object.assign({ action: action }, params || {}));
    return fetch(url, { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    }).then(function (j) {
      if (!j.success) throw new Error('API error');
      return j.staff_result;
    });
  }

  function post(payload) {
    return fetch(api, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': root.dataset.csrf || '' },
      body: qs(Object.assign({ action: 'x' }, payload))
    }).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    }).then(function (j) {
      if (!j.success) { var e = new Error((j.error && j.error.message) || 'Action failed'); e.apiError = j.error || {}; throw e; }
      return j.staff_result;
    });
  }

  function notify(msg, kind) {
    var el = document.getElementById('stf-toast');
    if (!el) { el = document.createElement('div'); el.id = 'stf-toast'; document.body.appendChild(el); }
    el.textContent = msg;
    el.className = 'stf-toast' + (kind === 'error' ? ' stf-toast-error' : '');
    requestAnimationFrame(function () { el.classList.add('show'); });
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.classList.remove('show'); }, kind === 'error' ? 5200 : 2600);
  }

  var STATUS_META = {
    active: ['Active', 'ok'], pending: ['Pending', 'warn'], suspended: ['Suspended', 'bad'],
    expired: ['Expired', 'muted'], removed: ['Removed', 'muted'],
    scheduled: ['Scheduled', 'warn'], accepted: ['Accepted', 'ok'], revoked: ['Revoked', 'bad']
  };
  function statusChip(s) {
    var m = STATUS_META[s] || [s, 'muted'];
    return '<span class="stf-chip stf-chip-' + m[1] + '">' + esc(m[0]) + '</span>';
  }

  function roleBadge(r) { return '<span class="stf-role-badge">' + esc(r) + '</span>'; }

  function avatar(name, url) {
    var ch = (name || '?').charAt(0).toUpperCase();
    return url ? '<span class="stf-avatar" style="background-image:url(' + esc(url) + ')"></span>'
      : '<span class="stf-avatar">' + esc(ch) + '</span>';
  }

  /* ── fetch orchestration ─────────────────────────────────────────── */

  function loadEnums() {
    return get('enums').then(function (e) { state.enums = e; return e; });
  }
  function loadEvents() {
    return get('events').then(function (e) { state.events = e; return e; });
  }
  function loadRoles() {
    return get('roles').then(function (r) { state.roles = r; return r; });
  }
  function loadOverview() {
    return get('overview').then(function (o) { state.overview = o; return o; });
  }

  /* ── shell ───────────────────────────────────────────────────────── */

  function shell() {
    root.innerHTML =
      '<div class="stf-shell">' +
      headerHtml() +
      '<div class="stf-body">' +
      '<div id="stf-content" class="stf-content"></div>' +
      '</div></div>';
    bindHeader();
    renderTab();
  }

  function headerHtml() {
    var tabs = [
      ['overview', 'Overview'], ['staff', 'All Staff'], ['invitations', 'Invitations'],
      ['roles', 'Roles & Permissions'], ['assignments', 'Assignments'], ['activity', 'Activity']
    ];
    var t = '';
    for (var i = 0; i < tabs.length; i++) {
      var cls = 'stf-tab' + (state.tab === tabs[i][0] ? ' active' : '');
      t += '<button class="' + cls + '" onclick="StaffConsole.go(\'' + tabs[i][0] + '\')">' + tabs[i][1] + '</button>';
    }
    return '<div class="stf-head">' +
      '<div class="stf-title-wrap"><h3 class="stf-title">Staff</h3>' +
      '<p class="stf-sub">Manage your event team, roles, permissions and access scope.</p></div>' +
      '<div class="stf-head-actions">' +
      '<button class="stf-btn stf-btn-ghost" onclick="StaffConsole.refresh()">' + ic('clock', 13) + ' Refresh</button>' +
      '<button class="stf-btn stf-btn-primary" onclick="StaffConsole.invite()">' + ic('plus', 14) + ' Invite Staff</button>' +
      '</div>' +
      '<div class="stf-tabs">' + t + '</div>' +
      '</div>';
  }

  function bindHeader() {
    document.querySelectorAll('#staff-root .stf-tab').forEach(function (el) {
      el.addEventListener('click', function () { go(el.getAttribute('onclick').match(/'([^']+)'/)[1]); });
    });
  }

  function go(tab) {
    state.tab = tab;
    state.activeId = '';
    state.bulk = [];
    document.querySelectorAll('#staff-root .stf-tab').forEach(function (el) {
      var t = el.getAttribute('onclick').match(/'([^']+)'/)[1];
      el.classList.toggle('active', t === tab);
    });
    renderTab();
  }

  function renderTab() {
    var el = document.getElementById('stf-content');
    if (!el) return;
    if (state.tab === 'overview') renderOverview(el);
    else if (state.tab === 'staff') renderStaff(el);
    else if (state.tab === 'invitations') renderInvitations(el);
    else if (state.tab === 'roles') renderRoles(el);
    else if (state.tab === 'assignments') renderAssignments(el);
    else if (state.tab === 'activity') renderActivity(el);
  }

  function renderOverview(el) {
    loadOverview().then(function (o) {
      el.innerHTML =
        '<div class="stf-kpis">' +
        kpi('Active Staff', o.active, 'staff', 'status=all', ic('users', 16), o.active + ' people with usable access') +
        kpi('Pending Invitations', o.invites_pending, 'invitations', '', ic('mail', 16), o.invites_total + ' total sent') +
        kpi('Event Assignments', o.assignments, 'assignments', '', ic('calendar', 16), (o.expired_now ? o.expired_now + ' expired' : 'active today')) +
        kpi('Suspended', o.suspended, 'staff', 'status=suspended', ic('shield', 16), 'prevented from accessing') +
        '</div>' +
        '<div class="stf-overview-grid">' +
        '<div class="stf-card"><div class="stf-card-h">Team status</div>' +
        '<div class="stf-team-status">' +
        teamRow('Active now', o.active) + teamRow('Pending invitations', o.invites_pending) +
        teamRow('Removed / offboarded', o.removed) + teamRow('Roles on file', o.roles) +
        '</div>' +
        '<div class="stf-card-h" style="margin-top:1rem;">Next steps</div>' +
        (o.invites_pending > 0 ? actionRow('Nudge pending invitations', 'StaffConsole.go(\'invitations\')')
          : '<div class="stf-empty sm">No pending invitations.</div>') +
        (o.expired_now > 0 ? actionRow('Review expired assignments', 'StaffConsole.go(\'assignments\')')
          : '<div class="stf-empty sm">No expired temporary access.</div>') +
        '</div>' +
        '<div class="stf-card"><div class="stf-card-h">Recent activity</div>' +
        recentRows(o.recent_activity) +
        '<button class="stf-link" onclick="StaffConsole.go(\'activity\')">View all activity</button>' +
        '</div>' +
        '</div>';
    }).catch(function (e) { el.innerHTML = errorBox(e); });
  }

  function kpi(label, value, tab, extra, icon, hint) {
    return '<button class="stf-kpi" onclick="' + (tab === 'invitations' || tab === 'assignments' ? 'StaffConsole.go(\'' + tab + '\')' : 'StaffConsole.filterKpi(\'' + extra + '\')') + '">' +
      '<span class="stf-kpi-ic">' + icon + '</span>' +
      '<span class="stf-kpi-v">' + esc(value) + '</span>' +
      '<span class="stf-kpi-l">' + esc(label) + '</span>' +
      '<span class="stf-kpi-h">' + esc(hint) + '</span></button>';
  }

  function teamRow(label, count) {
    return '<div class="stf-team-row"><span>' + esc(label) + '</span><strong>' + esc(count) + '</strong></div>';
  }
  function actionRow(label, js) {
    return '<button class="stf-action-row" onclick="' + js + '">' + esc(label) + '<span>→</span></button>';
  }
  function recentRows(rows) {
    if (!rows || !rows.length) return '<div class="stf-empty sm">No activity yet.</div>';
    var h = '';
    for (var i = 0; i < rows.length; i++) {
      var r = rows[i];
      h += '<div class="stf-recent-row' + (r.security ? ' sec' : '') + '">' +
        '<span class="stf-recent-ic">' + (r.security ? ic('shield', 13) : ic('clock', 13)) + '</span>' +
        '<div class="stf-recent-body"><div><strong>' + esc(r.actor_name || '—') + '</strong> ' + esc(r.action).replace(/_/g, ' ') +
        '</div><span class="stf-recent-time">' + esc(r.created || '') + '</span></div></div>';
    }
    return h;
  }

  function errorBox(e) {
    return '<div class="stf-empty">' + esc((e && e.message) || 'Could not load staff data.') + '</div>';
  }

  /* ── All Staff ───────────────────────────────────────────────────── */

  function renderStaff(el) {
    Promise.all([loadRoles(), loadEvents()]).then(function () {
      return get('staff', {
        q: state.q, role: state.role, status: state.status, event: state.event,
        access: state.access, sort: state.sort, limit: 200
      });
    }).then(function (res) {
      state.docs = res.items;
      el.innerHTML = staffToolbar() + bulkBar(res.items) +
        '<div id="stf-panel-wrap" class="stf-panel-wrap' + (state.activeId ? ' has-panel' : '') + '">' +
        '<div id="stf-list" class="stf-list"></div>' +
        (state.activeId ? '<div id="stf-detail-panel" class="stf-detail-panel"></div>' : '') +
        '</div>';
      bindStaffTools();
      renderStaffList(document.getElementById('stf-list'), res.items);
      if (state.activeId) openStaffPanel(state.activeId);
    }).catch(function (e) { el.innerHTML = errorBox(e); });
  }

  function staffToolbar() {
    var roleOpts = '<option value="">All roles</option>' + state.roles.map(function (r) {
      return '<option value="' + esc(r.id) + '"' + (state.role === r.id ? ' selected' : '') + '>' + esc(r.name) + '</option>';
    }).join('');
    var evOpts = '<option value="">All events</option>' + state.events.map(function (e) {
      return '<option value="' + esc(e.event_id) + '"' + (state.event === e.event_id ? ' selected' : '') + '>' + esc(e.title) + '</option>';
    }).join('');
    var stOpts = [
      ['', 'All statuses'], ['active', 'Active'], ['pending', 'Pending'], ['suspended', 'Suspended'],
      ['expired', 'Expired'], ['removed', 'Removed']
    ].map(function (s) {
      return '<option value="' + s[0] + '"' + (state.status === s[0] ? ' selected' : '') + '>' + s[1] + '</option>';
    }).join('');
    var accOpts = [
      ['', 'All access'], ['temporary', 'Temporary access only'], ['organization', 'Organization-wide']
    ].map(function (a) {
      return '<option value="' + a[0] + '"' + (state.access === a[0] ? ' selected' : '') + '>' + a[1] + '</option>';
    }).join('');
    var sortOpts = [
      ['recent', 'Recently active'], ['name', 'Name'], ['role', 'Role'], ['joined', 'Joined']
    ].map(function (s) {
      return '<option value="' + s[0] + '"' + (state.sort === s[0] ? ' selected' : '') + '>' + s[1] + '</option>';
    }).join('');
    return '<div class="stf-toolbar">' +
      '<div class="stf-search">' + ic('search', 13) +
      '<input id="stf-q" placeholder="Search name, email, staff ID…" value="' + esc(state.q) + '" oninput="StaffConsole.q(this.value)"></div>' +
      '<select onchange="StaffConsole.set(\'role\', this.value)">' + roleOpts + '</select>' +
      '<select onchange="StaffConsole.set(\'status\', this.value)">' + stOpts + '</select>' +
      '<select onchange="StaffConsole.set(\'event\', this.value)">' + evOpts + '</select>' +
      '<select onchange="StaffConsole.set(\'access\', this.value)">' + accOpts + '</select>' +
      '<select onchange="StaffConsole.set(\'sort\', this.value)">' + sortOpts + '</select>' +
      '</div>';
  }

  function bulkBar(items) {
    var n = state.bulk.length;
    return '<div id="stf-bulk" class="stf-bulk' + (n ? ' on' : '') + '">' +
      '<span><strong>' + n + '</strong> selected</span>' +
      '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.bulkAction(\'suspend\')">Suspend</button>' +
      '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.bulkRole()">Change role</button>' +
      '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.bulkAssign()">Assign event</button>' +
      '<button class="stf-btn stf-btn-danger sm" onclick="StaffConsole.bulkAction(\'remove\')">Remove</button>' +
      '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.clearBulk()">Clear</button>' +
      '</div>';
  }

  function renderStaffList(el, items) {
    if (!items.length) {
      el.innerHTML = '<div class="stf-empty">' +
        (state.q || state.status || state.event ? 'No staff match the current filters.' : 'No staff yet — invite your first team member.') + '</div>';
      return;
    }
    var h = '';
    for (var i = 0; i < items.length; i++) {
      var m = items[i];
      var checked = state.bulk.indexOf(m.staff_id) >= 0 ? ' checked' : '';
      h +=
        '<div class="stf-row' + (state.activeId === m.staff_id ? ' active' : '') + '" data-id="' + esc(m.staff_id) + '">' +
        '<label class="stf-check" onclick="event.stopPropagation()"><input type="checkbox" data-id="' + esc(m.staff_id) + '"' + checked + '></label>' +
        '<div class="stf-row-main">' + avatar(m.name, m.avatar) +
        '<div class="stf-id"><div class="stf-name">' + esc(m.name) + '</div>' +
        '<div class="stf-email">' + esc(m.email) + '</div></div></div>' +
        '<div class="stf-cell stf-cell-role">' + roleBadge(m.role_name) +
        '<div class="stf-cell-sub">' + esc(m.scope_label) + (m.next_expiry ? ' · expires ' + esc(m.next_expiry) : '') + '</div></div>' +
        '<div class="stf-cell">' + (m.assignment_count ? '<span class="stf-plain">' + ic('calendar', 12) + ' ' + m.assignment_count + ' event' + (m.assignment_count > 1 ? 's' : '') + '</span>' : '<span class="stf-muted">No events</span>') + '</div>' +
        '<div class="stf-cell"><span class="stf-plain">' + ic('key', 12) + ' ' + m.access_modules + ' modules</span></div>' +
        '<div class="stf-cell stf-cell-status">' + statusChip(m.status) +
        '<div class="stf-cell-sub">last active ' + esc(m.last_active || 'never') + '</div></div>' +
        '<div class="stf-row-actions">' +
        '<button class="stf-btn stf-btn-ghost sm" data-v="' + esc(m.staff_id) + '">' + ic('eye', 12) + ' View</button>' +
        '<button class="stf-btn stf-btn-ghost sm" data-e="' + esc(m.staff_id) + '">' + ic('edit', 12) + ' Edit</button>' +
        '<button class="stf-btn stf-btn-ghost sm" data-m="' + esc(m.staff_id) + '">•••</button>' +
        '</div></div>';
    }
    el.innerHTML = h;
    bindStaffRows(el);
  }

  function bindStaffTools() {
    document.querySelectorAll('#stf-list .stf-check input, #stf-list .stf-row [data-id]').forEach(function (c) {
      c.addEventListener('change', toggleBulk);
      c.addEventListener('click', toggleBulk);
    });
  }

  function bindStaffRows(el) {
    el.querySelectorAll('.stf-row').forEach(function (row) {
      row.addEventListener('click', function (e) {
        if (e.target.closest('.stf-check') || e.target.closest('.stf-btn')) return;
        openStaffPanel((row.dataset || {}).id || row.getAttribute('data-id'));
      });
      var v = row.querySelector('[data-v]');
      if (v) v.addEventListener('click', function (e) { e.stopPropagation(); openStaffPanel(v.getAttribute('data-v')); });
      var ed = row.querySelector('[data-e]');
      if (ed) ed.addEventListener('click', function (e) { e.stopPropagation(); editStaff(ed.getAttribute('data-e')); });
      var mm = row.querySelector('[data-m]');
      if (mm) mm.addEventListener('click', function (e) { e.stopPropagation(); staffMenu(mm.getAttribute('data-m'), mm); });
    });
    el.querySelectorAll('.stf-check input').forEach(function (c) {
      c.addEventListener('change', function () { toggleBulk.call(c); });
    });
  }

  function toggleBulk() {
    var id = this.getAttribute('data-id');
    var idx = state.bulk.indexOf(id);
    if (idx >= 0) state.bulk.splice(idx, 1); else state.bulk.push(id);
    var bar = document.getElementById('stf-bulk');
    if (bar) bar.outerHTML = bulkBar(state.docs);
    var chk = document.querySelector('#stf-list .stf-check input[data-id="' + id + '"]');
    if (chk) chk.checked = idx < 0;
  }

  function openStaffPanel(id) {
    state.activeId = id || state.activeId;
    return get('detail', { id: state.activeId }).then(function (d) {
      state.detail = d;
      var wrap = document.getElementById('stf-panel-wrap');
      if (wrap) wrap.classList.add('has-panel');
      var panel = document.getElementById('stf-detail-panel');
      if (!panel && wrap) {
        wrap.insertAdjacentHTML('beforeend', '<div id="stf-detail-panel" class="stf-detail-panel"></div>');
        wrap.querySelector('.stf-list').classList.add('has-panel');
        panel = document.getElementById('stf-detail-panel');
      }
      if (panel) { panel.innerHTML = staffPanelHtml(d); bindStaffDetailPanel(); }
    }).catch(function (e) { notify((e && e.message) || 'Could not open staff member.', 'error'); });
  }

  function staffPanelHtml(d) {
    return '<div class="stf-panel-head">' +
      '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.closePanel()">' + ic('x', 12) + ' Close</button>' +
      '</div>' +
      '<div class="stf-pprofile">' + avatar(d.name, d.avatar) +
      '<div class="stf-pname">' + esc(d.name) + '</div>' +
      '<div class="stf-ppos">' + esc(d.position_title || d.role_name) + ' · ' + roleBadge(d.role_name) + '</div>' +
      '<div style="margin-top:.55rem;">' + statusChip(d.status) + '</div>' +
      '</div>' +
      '<div class="stf-psec"><div class="stf-psec-t">Contact</div>' +
      '<div class="stf-psec-v">' + esc(d.email) + '</div>' +
      '<div class="stf-psec-v">' + esc(d.phone || d.phone_staff || 'No phone on file') + '</div></div>' +
      '<div class="stf-psec"><div class="stf-psec-t">Access</div>' +
      '<div class="stf-psec-v">' + d.assignments.length + ' events · ' + countModules(d.permissions) + ' modules</div>' +
      '<div class="stf-psec-v muted">Scope: ' + esc(d.scope_label) + '</div>' +
      (d.nextExpiry ? '' : '') +
      '</div>' +
      '<div class="stf-psec"><div class="stf-psec-t">Last active</div>' +
      '<div class="stf-psec-v">' + esc(d.last_active || d.last_login_at || 'never') + '</div></div>' +
      '<div class="stf-panel-actions">' +
      '<button class="stf-btn stf-btn-primary sm w" onclick="StaffConsole.openProfile(\'' + esc(d.staff_id) + '\')">View full profile</button>' +
      '<button class="stf-btn stf-btn-ghost sm w" onclick="StaffConsole.manageAccess(\'' + esc(d.staff_id) + '\')">Manage access</button>' +
      (d.status === 'active' ? '<button class="stf-btn stf-btn-danger sm w" onclick="StaffConsole.statusAction(\'' + esc(d.staff_id) + '\',\'suspend\')">Suspend</button>'
        : '<button class="stf-btn stf-btn-ghost sm w" onclick="StaffConsole.statusAction(\'' + esc(d.staff_id) + '\',\'active\')">Reactivate</button>') +
      '</div>';
  }

  function countModules(perms) {
    if (!perms) return 0;
    var n = 0;
    for (var k in perms) if (perms[k] && perms[k] !== 'none') n++;
    return n;
  }

  function bindStaffDetailPanel() {
    var wrap = document.getElementById('stf-panel-wrap');
    if (wrap) wrap.classList.add('has-panel');
  }

  function staffMenu(staffId, anchor) {
    state.activeId = staffId;
    var items = [
      ['View profile', 'StaffConsole.openProfile(\'' + staffId + '\')'],
      ['Manage access', 'StaffConsole.manageAccess(\'' + staffId + '\')'],
      ['Message', 'StaffConsole.msg(' + JSON.stringify(staffId) + ')'],
      ['Change role', 'StaffConsole.roleModal(' + JSON.stringify(staffId) + ')'],
      ['Remove from staff', 'StaffConsole.statusAction(\'' + staffId + '\',\'remove\')']
    ];
    popMenu(anchor, items);
  }

  function popMenu(anchor, items) {
    var old = document.getElementById('stf-pop');
    if (old) old.remove();
    var m = document.createElement('div');
    m.id = 'stf-pop';
    m.className = 'stf-pop';
    var h = '';
    for (var i = 0; i < items.length; i++) {
      h += '<button onclick="' + items[i][1] + '">' + esc(items[i][0]) + '</button>';
    }
    m.innerHTML = h;
    var r = anchor.getBoundingClientRect();
    m.style.top = (r.bottom + 6) + 'px';
    m.style.left = Math.min(r.left, window.innerWidth - 190) + 'px';
    document.body.appendChild(m);
    setTimeout(function () {
      window.addEventListener('click', function close(e) {
        if (!m.contains(e.target)) { m.remove(); window.removeEventListener('click', close); }
      }, { once: true });
    }, 0);
  }

  /* ── full profile page ───────────────────────────────────────────── */

  function openProfile(staffId) {
    state.activeId = staffId;
    state.profileTab = 'overview';
    Promise.all([loadEnums(), get('detail', { id: staffId })]).then(function (both) {
      state.detail = both[1];
      var content = document.getElementById('stf-content');
      content.innerHTML = profilePageHtml(state.detail);
      bindProfile(state.detail);
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function profilePageHtml(d) {
    var tabs = [['overview', 'Overview'], ['access', 'Access'], ['events', 'Events'], ['activity', 'Activity'], ['security', 'Security']];
    var t = '';
    for (var i = 0; i < tabs.length; i++) {
      t += '<button class="stf-p-tab' + (state.profileTab === tabs[i][0] ? ' active' : '') + '" data-p="' + tabs[i][0] + '">' + tabs[i][1] + '</button>';
    }
    return '<div class="stf-page">' +
      '<button class="stf-back" onclick="StaffConsole.back()">' + ic('chevL', 13) + ' Staff</button>' +
      '<div class="stf-page-head">' +
      avatar(d.name, d.avatar) +
      '<div class="stf-page-id"><div class="stf-pname">' + esc(d.name) + '</div>' +
      '<div class="stf-ppos">' + esc(d.position_title || d.role_name) + ' · ' + roleBadge(d.role_name) + '</div>' +
      '<div style="margin-top:.5rem;">' + statusChip(d.status) + ' <span class="stf-muted">' + esc(d.email) + '</span></div></div>' +
      '<div class="stf-page-actions">' +
      '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.manageAccess(\'' + esc(d.staff_id) + '\')">Manage access</button>' +
      '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.msg(\'' + esc(d.staff_id) + '\')">Message</button>' +
      '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.editStaff(\'' + esc(d.staff_id) + '\')">Edit</button>' +
      '<button class="stf-btn stf-btn-danger sm" onclick="StaffConsole.statusAction(\'' + esc(d.staff_id) + '\',\'' + (d.status === 'active' ? 'suspend' : 'active') + '\')">' + (d.status === 'active' ? 'Suspend' : 'Reactivate') + '</button>' +
      '</div></div>' +
      '<div class="stf-p-tabs">' + t + '</div>' +
      '<div id="stf-profile-body">' + profileBody(d) + '</div>' +
      '</div>';
  }

  function profileBody(d) {
    var tab = state.profileTab;
    if (tab === 'access') return accessTab(d);
    if (tab === 'events') return eventsTab(d);
    if (tab === 'activity') return activityTabHtml(d.activity);
    if (tab === 'security') return securityTabHtml(d);
    return overviewTab(d);
  }

  function overviewTab(d) {
    return '<div class="stf-profile-cols">' +
      '<div class="stf-card"><div class="stf-card-h">Identity</div>' +
      field('Full name', d.name) + field('Email', d.email) + field('Phone', d.phone || d.phone_staff || '—') +
      field('Staff ID', d.staff_id.split('-')[0]) + field('Account status', d.account_status) +
      field('MFA', d.two_factor_enabled ? 'Enabled' : 'Not enabled') +
      '</div>' +
      '<div class="stf-card"><div class="stf-card-h">Organization</div>' +
      field('Department', d.department || '—') + field('Position', d.position_title || '—') +
      field('Joined', d.joined_date || '—') + field('Added by', d.added_by || '—') +
      field('Last active', d.last_active || d.last_login_at || 'never') +
      field('Timezone', d.timezone || '—') +
      '</div>' +
      '<div class="stf-card"><div class="stf-card-h">Current role</div>' +
      '<div class="stf-current-role">' + roleBadge(d.role_name) + '</div>' +
      '<div class="stf-muted sm">' + esc(d.scope_label) + ' scope</div>' +
      '<div class="stf-doc-stats">' +
      '<div><strong>' + d.documents.created + '</strong><span>Documents created</span></div>' +
      '<div><strong>' + d.documents.shared + '</strong><span>Shared</span></div>' +
      '<div><strong>' + d.documents.pending_approvals + '</strong><span>Pending approvals</span></div>' +
      '</div>' +
      '<button class="stf-link" onclick="StaffConsole.docs(\'' + esc(d.documentUser || d.user_id) + '\')">View Documents</button>' +
      '</div></div>';
  }

  function field(label, value) {
    return '<div class="stf-field"><span>' + esc(label) + '</span><strong>' + esc(value == null ? '' : value) + '</strong></div>';
  }

  function accessTab(d) {
    var h = '<div class="stf-card sm"><div class="stf-card-h">Access</div>' +
      '<div class="stf-access-meta"><div><span>Role</span><strong>' + esc(d.role_name) + '</strong></div>' +
      '<div><span>Scope</span><strong>' + esc(d.scope_label) + '</strong></div></div></div>' +
      '<div class="stf-card"><div class="stf-card-h">Modules</div>';
    var groups = state.enums ? state.enums.module_groups : null;
    var labels = state.enums ? state.enums.modules : {};
    if (groups) {
      Object.keys(groups).forEach(function (g) {
        h += '<div class="stf-mat-group"><div class="stf-mat-group-t">' + esc(g) + '</div><div class="stf-mat-rows">';
        groups[g].forEach(function (mod) {
          var lvl = (d.permissions || {})[mod] || 'none';
          h += '<div class="stf-mat-row read"><span>' + esc(labels[mod] || mod) + '</span>' +
            '<span class="stf-lvl stf-lvl-' + (lvl === 'none' ? 'off' : 'on') + '">' + (lvl === 'none' ? 'No access' : esc(lvl)) + '</span></div>';
        });
        h += '</div></div>';
      });
    }
    h += '<div class="stf-muted sm" style="margin-top:.6rem;">The backend enforces the same matrix — hiding a module here never grants it.</div></div>';
    return h;
  }

  function eventsTab(d) {
    var h = '<div class="stf-card"><div class="stf-card-h">Event assignments</div>';
    if (!d.assignments.length) h += '<div class="stf-empty sm">No event assignments — access is organization-wide or unassigned.</div>';
    for (var i = 0; i < d.assignments.length; i++) {
      var a = d.assignments[i];
      h += '<div class="stf-assign-row">' +
        '<div class="stf-assign-main"><strong>' + esc(a.event_title) + '</strong>' +
        '<div class="stf-muted sm">' + roleBadge(a.role_name) + '</div>' +
        (a.access_start_at || a.access_end_at ? '<div class="stf-muted sm">' + ic('calendar', 11) + ' ' + esc(a.access_start_at || 'now') + ' → ' + esc(a.access_end_at || 'open') + (a.status === 'expired' ? ' (auto-expired)' : '') + '</div>' : '') +
        '</div>' + statusChip(a.status) +
        '<div class="stf-assign-act"><button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.editAssignment(\'' + esc(a.assignment_id) + '\')">Edit</button>' +
        '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.removeAssignment(\'' + esc(a.assignment_id) + '\')">Remove</button></div></div>';
    }
    h += '<button class="stf-btn stf-btn-ghost sm" style="margin-top:.8rem;" onclick="StaffConsole.manageAccess(\'' + esc(d.staff_id) + '\')">' + ic('plus', 12) + ' Manage assignments</button></div>';
    return h;
  }

  function activityTabHtml(rows) {
    var h = '<div class="stf-card"><div class="stf-card-h">Activity</div>';
    if (!rows || !rows.length) h += '<div class="stf-empty sm">No recorded activity.</div>';
    for (var i = 0; i < (rows || []).length; i++) {
      var r = rows[i];
      h += '<div class="stf-act-row' + (r.security ? ' sec' : '') + '"><span class="stf-recent-ic">' + (r.security ? ic('shield', 13) : ic('clock', 13)) + '</span>' +
        '<div class="stf-act-body"><strong>' + esc(r.actor_name || '—') + '</strong> ' + esc(r.action).replace(/_/g, ' ') +
        (r.module ? ' · ' + esc(r.module) : '') +
        '<div class="stf-muted sm">' + esc(r.created || '') + '</div></div></div>';
    }
    h += '</div>';
    return h;
  }

  function securityTabHtml(d) {
    var sec = (d.activity || []).filter(function (a) { return a.security; });
    var h = '<div class="stf-card"><div class="stf-card-h">Security activity</div>';
    if (!sec.length) h += '<div class="stf-empty sm">No security events recorded for this member.</div>';
    for (var i = 0; i < sec.length; i++) {
      h += '<div class="stf-act-row sec"><span class="stf-recent-ic">' + ic('shield', 13) + '</span>' +
        '<div class="stf-act-body"><strong>' + esc(sec[i].action).replace(/_/g, ' ') + '</strong> · ' + esc(sec[i].actor_name || '—') +
        '<div class="stf-muted sm">' + esc(sec[i].created || '') + '</div></div></div>';
    }
    h += '</div>';
    return h;
  }

  function bindProfile(d) {
    document.querySelectorAll('#stf-profile-body').length;
    document.querySelectorAll('.stf-p-tab').forEach(function (b) {
      b.addEventListener('click', function () {
        state.profileTab = b.getAttribute('data-p');
        document.querySelectorAll('.stf-p-tab').forEach(function (x) { x.classList.toggle('active', x === b); });
        var body = document.getElementById('stf-profile-body');
        if (body) body.innerHTML = profileBody(d);
      });
    });
  }

  /* ── Invitations ─────────────────────────────────────────────────── */

  function renderInvitations(el) {
    get('invitations', { status: '' }).then(function (rows) {
      state.invites = rows;
      if (state.activeId === 'invite') {
        el.innerHTML = '<div id="stf-modal-host"></div>';
        inviteWizard();
        return;
      }
      el.innerHTML =
        '<div class="stf-subhead"><div><strong>' + esc(rows.filter(function (i) { return i.status === 'pending'; }).length) + ' pending</strong> · ' +
        esc(rows.length) + ' total</div>' +
        '<button class="stf-btn stf-btn-primary sm" onclick="StaffConsole.go(\'invitations\');StaffConsole.invite()">' + ic('plus', 13) + ' Invite Staff</button></div>' +
        inviteTable(rows);
    }).catch(function (e) { el.innerHTML = errorBox(e); });
  }

  function inviteTable(rows) {
    if (!rows.length) return '<div class="stf-empty">No invitations yet. Invite a teammate to get them into the workspace.</div>';
    var h = '<div class="stf-table"><div class="stf-tr stf-tr-head">' +
      '<div>Person</div><div>Role</div><div>Scope</div><div>Status</div><div>Sent / expires</div><div></div></div>';
    for (var i = 0; i < rows.length; i++) {
      var inv = rows[i];
      h += '<div class="stf-tr">' +
        '<div><div class="stf-name">' + esc(inv.name) + '</div><div class="stf-email">' + esc(inv.email) + '</div></div>' +
        '<div>' + roleBadge(inv.role_name) + '</div>' +
        '<div><span class="stf-plain">' + esc(inv.scope_label) + '</span>' +
        (inv.event_titles.length ? '<div class="stf-muted sm">' + esc(inv.event_titles.join(', ').slice(0, 42)) + (inv.event_titles.join(', ').length > 42 ? '…' : '') + '</div>' : '') + '</div>' +
        '<div>' + statusChip(inv.status) + '</div>' +
        '<div><span class="stf-muted sm">' + esc(inv.sent_at) + '<br>expires ' + esc(inv.expires_at) + '</span></div>' +
        '<div class="stf-row-actions">' +
        (inv.status === 'pending' ? '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.resendInvite(\'' + esc(inv.id) + '\')">Resend</button>' +
          '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.revokeInvite(\'' + esc(inv.id) + '\')">Revoke</button>'
          : '<span class="stf-muted sm">' + esc(inv.status === 'accepted' ? (inv.accepted_at || 'accepted') : '') + '</span>') +
        '</div></div>';
    }
    return h + '</div>';
  }

  /* invite wizard: identity -> role -> scope -> review */
  function inviteWizard() {
    var host = document.getElementById('stf-modal-host') || document.body;
    host.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
      '<div class="stf-modal stf-modal-w" id="stf-invite-modal">' + inviteStepHtml() + '</div>';
    bindInviteStep();
  }

  function inviteStepHtml() {
    var s = state.inviteStep;
    var steps = [['Identity', 1], ['Role', 2], ['Scope', 3], ['Review', 4]];
    var dots = '<div class="stf-steps">';
    for (var i = 0; i < steps.length; i++) {
      dots += '<div class="stf-step' + (steps[i][1] <= s ? ' on' : '') + '"><span>' + steps[i][1] + '</span>' + esc(steps[i][0]) + '</div>';
    }
    dots += '</div>';
    var v = state.invite || {};
    var body = '';
    if (s === 1) {
      body = '<div class="stf-form"><label>Email<input id="stf-iv-email" type="email" required value="' + esc(v.email || '') + '"></label>' +
        '<div class="stf-form-row"><label>First name<input id="stf-iv-first" value="' + esc(v.first_name || '') + '"></label>' +
        '<label>Last name<input id="stf-iv-last" value="' + esc(v.last_name || '') + '"></label></div></div>';
    } else if (s === 2) {
      body = '<div class="stf-role-pick">';
      for (var i2 = 0; i2 < state.roles.length; i2++) {
        var r = state.roles[i2];
        body += '<label class="stf-role-opt' + (v.role_id === r.id ? ' on' : '') + '">' +
          '<input type="radio" name="stf-iv-role" value="' + esc(r.id) + '"' + (v.role_id === r.id ? ' checked' : '') + '>' +
          '<div><strong>' + esc(r.name) + '</strong><span>' + esc(r.description || '') + '</span></div></label>';
      }
      body += '</div>';
    } else if (s === 3) {
      var scope = v.scope_type || 'organization';
      body = '<div class="stf-form"><label class="stf-radio"><input type="radio" name="stf-iv-scope" value="organization"' + (scope === 'organization' ? ' checked' : '') + '> Entire organization</label>' +
        '<label class="stf-radio"><input type="radio" name="stf-iv-scope" value="events"' + (scope === 'events' ? ' checked' : '') + '> Selected events</label></div>' +
        '<div class="stf-ev-pick" id="stf-iv-events">' +
        state.events.map(function (e) {
          var checked = (v.event_ids || []).indexOf(e.event_id) >= 0;
          return '<label class="stf-ev-opt"><input type="checkbox" value="' + esc(e.event_id) + '"' + (checked ? ' checked' : '') + '>' +
            '<span><strong>' + esc(e.title) + '</strong><em>' + esc(e.start_date || '') + (e.status ? ' · ' + esc(e.status) : '') + '</em></span></label>';
        }).join('') + '</div>' +
        '<div class="stf-form stf-form-row"><label>Access begins<input id="stf-iv-start" type="datetime-local" value="' + esc(v.access_start_at || '') + '"></label>' +
        '<label>Access ends (optional)<input id="stf-iv-end" type="datetime-local" value="' + esc(v.access_end_at || '') + '"></label></div>' +
        '<div class="stf-muted sm">Leave both empty for permanent access. Temporary access expires automatically.</div>';
    } else {
      var role = state.roles.filter(function (r) { return r.id === v.role_id; })[0] || {};
      var evTitles = state.events.filter(function (e) { return (v.event_ids || []).indexOf(e.event_id) >= 0; })
        .map(function (e) { return e.title; });
      body = '<div class="stf-inv-sum"><div class="stf-pname">' + esc((v.first_name || '') + ' ' + (v.last_name || '')).trim() + '</div>' +
        '<div class="stf-email">' + esc(v.email || '') + '</div>' +
        '<div class="stf-sum-row"><span>Role</span><strong>' + esc(role.name || '—') + '</strong></div>' +
        '<div class="stf-sum-row"><span>Access</span><strong>' + esc(v.scope_type === 'events' ? (evTitles.length ? 'Selected events' : 'Events') : 'Entire organization') + '</strong></div>' +
        (v.scope_type === 'events' ? '<div class="stf-sum-evs">' + evTitles.map(function (t) { return '<span>' + esc(t) + '</span>'; }).join('') + '</div>' : '') +
        (v.access_end_at ? '<div class="stf-sum-row"><span>Temporary</span><strong>until ' + esc(v.access_end_at) + '</strong></div>' : '') +
        '</div>';
    }
    return '<div class="stf-modal-h"><div class="stf-pname">Invite Staff</div>' + ic('x', 14) + '<button class="stf-btn stf-btn-ghost sm absx" onclick="StaffConsole.closeModal()">' + ic('x', 13) + '</button></div>' +
      dots + '<div class="stf-modal-b">' + body + '</div>' +
      '<div class="stf-modal-f">' +
      (s > 1 ? '<button class="stf-btn stf-btn-ghost" onclick="StaffConsole.stepInvite(' + (s - 1) + ')">Back</button>' : '') +
      (s < 4 ? '<button class="stf-btn stf-btn-primary" onclick="StaffConsole.stepInvite(' + (s + 1) + ')">Continue</button>'
        : '<button class="stf-btn stf-btn-primary" onclick="StaffConsole.sendInvite()">' + ic('mail', 13) + ' Send Invitation</button>') +
      '</div>';
  }

  function bindInviteStep() {
    var set = function (id, k) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', function () { state.invite = state.invite || {}; state.invite[k] = el.value; });
    };
    set('stf-iv-email', 'email');
    set('stf-iv-first', 'first_name');
    set('stf-iv-last', 'last_name');
    set('stf-iv-start', 'access_start_at');
    set('stf-iv-end', 'access_end_at');
    document.querySelectorAll('input[name="stf-iv-role"]').forEach(function (el) {
      el.addEventListener('change', function () { state.invite = state.invite || {}; state.invite.role_id = el.value; });
    });
    document.querySelectorAll('input[name="stf-iv-scope"]').forEach(function (el) {
      el.addEventListener('change', function () { state.invite = state.invite || {}; state.invite.scope_type = el.value; });
    });
    document.querySelectorAll('#stf-iv-events input[type="checkbox"]').forEach(function (el) {
      el.addEventListener('change', function () {
        state.invite = state.invite || {};
        var ids = state.invite.event_ids || [];
        var idx = ids.indexOf(el.value);
        if (el.checked && idx < 0) ids.push(el.value);
        if (!el.checked && idx >= 0) ids.splice(idx, 1);
        state.invite.event_ids = ids;
      });
    });
  }

  /* ── Roles & Permissions ─────────────────────────────────────────── */

  function renderRoles(el) {
    loadRoles().then(function (roles) {
      return Promise.all([loadEnums()]).then(function () {
        if (state.tab !== 'roles') return;
        var h = '<div class="stf-subhead"><div><strong>' + roles.length + '</strong> roles</div>' +
          '<button class="stf-btn stf-btn-primary sm" onclick="StaffConsole.createRole()">' + ic('plus', 13) + ' Create Custom Role</button></div>' +
          '<div class="stf-roles-grid">';
        for (var i = 0; i < roles.length; i++) {
          var r = roles[i];
          h += '<div class="stf-card stf-role-card">' +
            '<div class="stf-role-top"><div>' + roleBadge(r.name) + (r.is_system ? '<span class="stf-muted sm"> system</span>' : '<span class="stf-chip stf-chip-ok sm">custom</span>') + '</div>' +
            '<span class="stf-muted sm">' + esc(r.scope_label) + '</span></div>' +
            '<p class="stf-muted sm">' + esc(r.description || '') + '</p>' +
            '<div class="stf-role-meta"><span>' + ic('users', 12) + ' ' + r.members + ' member' + (r.members === 1 ? '' : 's') + '</span>' +
            '<span>' + ic('key', 12) + ' ' + countModules(r.permissions) + ' modules</span></div>' +
            '<div class="stf-role-actions"><button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.roleMatrix(\'' + esc(r.id) + '\')">Permissions</button>' +
            (r.is_system ? '' : '<button class="stf-btn stf-btn-danger sm" onclick="StaffConsole.deleteRole(\'' + esc(r.id) + '\',\'' + esc(r.name.replace(/'/g, '')) + '\')">Delete</button>') +
            '</div></div>';
        }
        el.innerHTML = h + '</div>';
      });
    }).catch(function (e) { el.innerHTML = errorBox(e); });
  }

  function roleMatrix(roleId) {
    get('role', { id: roleId }).then(function (rd) {
      state.roleMatrix = rd;
      var host = document.getElementById('stf-content');
      host.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
        '<div class="stf-modal stf-modal-xl" id="stf-rm-modal"></div>';
      renderMatrix();
    });
  }

  function matrixGroups(perms) {
    var groups = state.enums ? state.enums.module_groups : {};
    var labels = state.enums ? state.enums.modules : {};
    var options = (state.enums ? state.enums.levels : ['none', 'view']).map(function (l) {
      return '<option value="' + l + '">' + l.charAt(0).toUpperCase() + l.slice(1) + '</option>';
    }).join('');
    var folders = [];
    Object.keys(groups).forEach(function (g) {
      var rows = '';
      groups[g].forEach(function (mod) {
        var lvl = (perms || {})[mod] || 'none';
        rows += '<div class="stf-mat-row"><span>' + esc(labels[mod] || mod) + '</span>' +
          '<select data-m="' + mod + '">' + options.replace('value="' + lvl + '"', 'value="' + lvl + '" selected') + '</select></div>';
      });
      folders.push('<div class="stf-mat-group"><div class="stf-mat-group-t">' + esc(g) + '</div>' + rows + '</div>');
    });
    return folders;
  }

  function renderMatrix() {
    var rd = state.roleMatrix;
    var el = document.getElementById('stf-rm-modal');
    if (!el) return;
    var options = (state.enums ? state.enums.levels : []).map(function (l) {
      return '<option value="' + l + '"' + '>' + l.charAt(0).toUpperCase() + l.slice(1) + '</option>';
    }).join('');
    var h = '<div class="stf-modal-h"><div class="stf-pname">' + esc(rd.name) + ' — Permissions</div>' +
      '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.closeModal()">' + ic('x', 13) + '</button></div>' +
      '<div class="stf-modal-b stf-scroll">';
    Object.keys(state.enums.module_groups).forEach(function (g) {
      h += '<div class="stf-mat-group"><div class="stf-mat-group-t">' + esc(g) + '</div>';
      state.enums.module_groups[g].forEach(function (mod) {
        var lvl = (rd.permissions || {})[mod] || 'none';
        h += '<div class="stf-mat-row"><span>' + esc(state.enums.modules[mod] || mod) + '</span>' +
          '<select class="stf-mat-sel" data-m="' + mod + '">' + options.replace('value="' + lvl + '"', 'value="' + lvl + '" selected') + '</select></div>';
      });
      h += '</div>';
    });
    h += '</div>' +
      '<div class="stf-modal-f"><span class="stf-muted sm">Levels: None < View < Create < Edit < Manage < Approve < Export < Delete</span>' +
      '<button class="stf-btn stf-btn-primary" onclick="StaffConsole.saveMatrix()">Save permissions</button></div>';
    el.innerHTML = h;
  }

  /* ── Assignments ─────────────────────────────────────────────────── */

  function renderAssignments(el) {
    Promise.all([get('assignments'), get('matrix')]).then(function (both) {
      if (state.tab !== 'assignments') return;
      state.assignments = both[0];
      state.matrix = both[1];
      renderAssignmentBody(el);
    }).catch(function (e) { if (state.tab === 'assignments') el.innerHTML = errorBox(e); });
  }

  function renderAssignmentBody(el) {
    var toggle = '<div class="stf-matrix-toggle">' +
      '<button class="stf-btn stf-btn-ghost sm' + (state.matrixMode === 'events' ? ' active' : '') + '" onclick="StaffConsole.setMatrix(1)">' + ic('grid', 13) + ' By event</button>' +
      '<button class="stf-btn stf-btn-ghost sm' + (state.matrixMode === 'matrix' ? ' active' : '') + '" onclick="StaffConsole.setMatrix(0)">' + ic('table', 13) + ' Assignment matrix</button></div>';
    if (state.matrixMode === 'matrix') {
      el.innerHTML = toggle + matrixHtml(state.matrix);
    } else {
      var h = toggle;
      if (!state.assignments.length) {
        h += '<div class="stf-empty">No event assignments yet. Assign staff to events from a staff profile or the All Staff list.</div>';
      }
      for (var i = 0; i < state.assignments.length; i++) {
        var ev = state.assignments[i];
        h += '<div class="stf-card stf-ev-card">' +
          '<div class="stf-ev-head"><div><strong>' + esc(ev.event_title) + '</strong>' +
          '<div class="stf-muted sm">' + esc(ev.event_status || '') + (ev.start_date ? ' · ' + esc(ev.start_date) : '') + ' · ' + ev.staff_count + ' staff</div></div>' +
          '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.viewTeam(\'' + esc(ev.event_id) + '\')">View team</button></div>' +
          '<div class="stf-ev-roles">' +
          ev.roles.map(function (r) {
            return '<div class="stf-ev-role"><span>' + ic('users', 12) + '</span><div><strong>' + esc(r.role_name) + '</strong><em>' + r.count + ' member' + (r.count === 1 ? '' : 's') + '</em></div></div>';
          }).join('') +
          '</div></div>';
      }
      el.innerHTML = h;
    }
  }

  function matrixHtml(mx) {
    if (!mx || !Array.isArray(mx.events)) return '<div class="stf-empty">No data.</div>';
    var h = '<div class="stf-card stf-matrix">' +
      '<div class="stf-mat-grid" style="grid-template-columns:160px repeat(' + mx.events.length + ', minmax(90px,1fr));">' +
      '<div class="stf-mat-cell head">Staff</div>';
    mx.events.forEach(function (e) {
      h += '<div class="stf-mat-cell head" title="' + esc(e.event_title) + '">' + esc(e.event_title.length > 16 ? e.event_title.slice(0, 15) + '…' : e.event_title) + '</div>';
    });
    mx.staff.forEach(function (m) {
      h += '<div class="stf-mat-cell name">' + esc(m.name) + '</div>';
      var row = (mx.matrix.filter(function (x) { return x.staff_id === m.staff_id; })[0] || {}).assignments || {};
      mx.events.forEach(function (e) {
        var st = row[e.event_id];
        var dot = st === 'active' ? '✓' : (st === 'scheduled' ? '◷' : (st === 'expired' ? '⊘' : '—'));
        var cls = st === 'active' ? 'yes' : (st === 'scheduled' ? 'soon' : (st === 'expired' ? 'gone' : 'no'));
        h += '<div class="stf-mat-cell cell ' + cls + '" title="' + esc(m.name) + ' / ' + esc(e.event_title) + ': ' + esc(st || 'no access') + '">' + dot + '</div>';
      });
    });
    h += '</div></div>';
    return h;
  }

  function viewTeam(eventId) {
    var ev = state.assignments.filter(function (a) { return a.event_id === eventId; })[0];
    if (!ev) return;
    var host = document.getElementById('stf-content');
    host.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
      '<div class="stf-modal stf-modal-l" id="stf-team-modal">' +
      '<div class="stf-modal-h"><div class="stf-pname">' + esc(ev.event_title) + ' — Team</div>' +
      '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.closeModal()">' + ic('x', 13) + '</button></div>' +
      '<div class="stf-modal-b stf-scroll">' +
      ev.team.map(function (m) {
        return '<div class="stf-team-row2">' +
          avatar(m.name, '') +
          '<div class="stf-team-m"><strong>' + esc(m.name) + '</strong>' +
          '<span class="stf-muted sm">' + esc(m.email) + ' · ' + roleBadge(m.role_name) + '</span></div>' +
          statusChip(m.assignment_status) +
          '<span class="stf-muted sm">' + ((m.access_start_at || 'now') + ' → ' + (m.access_end_at || 'open')) + '</span></div>';
      }).join('') + '</div></div>';
  }

  /* ── Activity ────────────────────────────────────────────────────── */

  function renderActivity(el) {
    get('activity', { scope: state.activityScope, limit: 120 }).then(function (rows) {
      state.activity = rows;
      var secLabel = state.activityScope === 'security' ? 'Security activity' : 'Staff activity';
      el.innerHTML =
        '<div class="stf-subhead"><div><strong>' + secLabel + '</strong> · ' + rows.length + ' events</div>' +
        '<button class="stf-btn stf-btn-ghost sm' + (state.activityScope === 'security' ? ' active' : '') + '" onclick="StaffConsole.setScope(\'security\')">' + ic('shield', 13) + ' Security only</button></div>' +
        '<div class="stf-card stf-act-list">' +
        (rows.length ? rows.map(activityRow).join('') : '<div class="stf-empty sm">No activity recorded.</div>') +
        '</div>';
    }).catch(function (e) { el.innerHTML = errorBox(e); });
  }

  function activityRow(a) {
    var detail = a.detail || {};
    var extra = '';
    if (a.action === 'role_changed' && detail.previous_role && detail.new_role) {
      extra = '<div class="stf-muted sm">' + esc(detail.previous_role) + ' → <strong>' + esc(detail.new_role) + '</strong></div>';
    }
    return '<div class="stf-act-row' + (a.security ? ' sec' : '') + '">' +
      '<span class="stf-recent-ic">' + (a.security ? ic('shield', 13) : ic('clock', 13)) + '</span>' +
      '<div class="stf-act-body"><div><strong>' + esc(a.actor_name || '—') + '</strong> ' + esc(a.action).replace(/_/g, ' ') +
      (a.module ? ' · ' + esc(a.module) : '') + '</div>' + extra +
      '<div class="stf-muted sm">' + esc(a.created || '') + '</div></div></div>';
  }

  /* ── actions ─────────────────────────────────────────────────────── */

  function refresh() {
    loadOverview().then(renderTab);
  }

  function openProfileFromMenu() { openProfile(state.activeId); }

  function modal(html, hostId) {
    var host = document.getElementById(hostId || 'stf-content');
    host.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' + html;
  }

  function invite() {
    state.invite = state.invite || {};
    state.inviteStep = 1;
    Promise.all([loadRoles(), loadEvents()]).then(function () {
      modal('<div class="stf-modal stf-modal-w" id="stf-invite-modal"></div>', 'stf-content');
      document.getElementById('stf-invite-modal').innerHTML = inviteStepHtml();
      bindInviteStep();
    });
  }

  function stepInvite(n) {
    state.inviteStep = n;
    var m = document.getElementById('stf-invite-modal');
    if (m) m.innerHTML = inviteStepHtml();
    bindInviteStep();
  }

  function sendInvite() {
    state.invite = state.invite || {};
    var v = state.invite;
    if (!v.email || !v.first_name || !v.last_name) { notify('Email and full name are required.', 'error'); return; }
    if (!v.role_id) { notify('Choose a role for the invitee.', 'error'); return; }
    if ((v.scope_type || 'organization') === 'events' && !(v.event_ids || []).length) {
      notify('Select at least one event for scoped access.', 'error');
      return;
    }
    post(Object.assign({
      action: 'invite', email: v.email, first_name: v.first_name, last_name: v.last_name,
      role_id: v.role_id, scope_type: v.scope_type || 'organization',
      event_ids: JSON.stringify(v.event_ids || []),
      access_start_at: v.access_start_at || '', access_end_at: v.access_end_at || ''
    }, { action: 'invite' })).then(function (res) {
      notify('Invitation sent to ' + v.email);
      state.activeId = '';
      state.invite = null;
      closeModal();
      go('invitations');
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function closeModal() {
    state.invite = null;
    renderTab();
  }

  function resendInvite(id) {
    post({ action: 'invitation_resend', invitation_id: id }).then(function () {
      notify('Invitation resent with a new link.');
      renderTab();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function revokeInvite(id) {
    post({ action: 'invitation_revoke', invitation_id: id }).then(function () {
      notify('Invitation revoked.');
      renderTab();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function manageAccess(staffId) {
    state.activeId = staffId;
    get('detail', { id: staffId }).then(function (d) {
      state.detail = d;
      Promise.all([loadRoles(), loadEvents()]).then(function () {
        var opts = state.roles.map(function (r) {
          return '<option value="' + esc(r.id) + '"' + (r.id === d.role_id ? ' selected' : '') + '>' + esc(r.name) + '</option>';
        }).join('');
        var evs = state.events.map(function (e) {
          return '<label class="stf-ev-opt"><input type="checkbox" value="' + esc(e.event_id) + '"' +
            (d.assignments.filter(function (a) { return a.event_id === e.event_id; }).length ? ' checked' : '') + '>' +
            '<span><strong>' + esc(e.title) + '</strong><em>' + esc(e.start_date || '') + '</em></span></label>';
        }).join('');
        var content = document.getElementById('stf-content');
        content.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
          '<div class="stf-modal stf-modal-l">' +
          '<div class="stf-modal-h"><div class="stf-pname">Manage access — ' + esc(d.name) + '</div>' +
          '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.closeModal()">' + ic('x', 13) + '</button></div>' +
          '<div class="stf-modal-b stf-scroll"><div class="stf-form"><label>Role on assignments<select id="stf-ma-role">' + opts + '</select></label></div>' +
          '<div class="stf-card-h">Event scope</div><div class="stf-ev-pick">' + evs + '</div>' +
          '<div class="stf-form stf-form-row"><label>Access begins<input id="stf-ma-start" type="datetime-local" value=""></label>' +
          '<label>Access ends (optional)<input id="stf-ma-end" type="datetime-local" value=""></label></div>' +
          '<div class="stf-muted sm">Only checked events are granted; anything else stays off-limits.</div></div>' +
          '<div class="stf-modal-f"><button class="stf-btn stf-btn-primary" onclick="StaffConsole.saveAccess()">Save access</button></div></div>';
        document.getElementById('stf-ma-role').value = d.role_id;
      });
    });
  }

  function saveAccess() {
    var d = state.detail;
    var roleId = document.getElementById('stf-ma-role').value;
    var evs = Array.from(document.querySelectorAll('.stf-ev-pick input:checked')).map(function (x) { return x.value; });
    post({
      action: 'assign', staff_id: d.staff_id, role_id: roleId, event_ids: JSON.stringify(evs),
      replace_scope: 1,
      access_start_at: document.getElementById('stf-ma-start').value,
      access_end_at: document.getElementById('stf-ma-end').value
    }).then(function () {
      notify('Access updated for ' + d.name + '.');
      closeModal();
      renderTab();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function editStaff(staffId) {
    get('detail', { id: staffId }).then(function (d) {
      state.detail = d;
      var content = document.getElementById('stf-content');
      content.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
        '<div class="stf-modal stf-modal-m">' +
        '<div class="stf-modal-h"><div class="stf-pname">Edit — ' + esc(d.name) + '</div>' +
        '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.closeModal()">' + ic('x', 13) + '</button></div>' +
        '<div class="stf-modal-b stf-scroll"><div class="stf-form">' +
        '<label>Department<input id="stf-ed-dept" value="' + esc(d.department || '') + '"></label>' +
        '<label>Position title<input id="stf-ed-pos" value="' + esc(d.position_title || '') + '"></label>' +
        '<label>Phone<input id="stf-ed-phone" value="' + esc(d.phone_staff || d.phone || '') + '"></label>' +
        '<label>Timezone<input id="stf-ed-tz" value="' + esc(d.timezone || '') + '"></label>' +
        '<label>Notes<textarea id="stf-ed-notes">' + esc(d.notes || '') + '</textarea></label>' +
        '</div></div>' +
        '<div class="stf-modal-f"><button class="stf-btn stf-btn-primary" onclick="StaffConsole.saveProfile()">Save profile</button></div></div>';
    });
  }

  function saveProfile() {
    var d = state.detail;
    post({
      action: 'update_profile', staff_id: d.staff_id,
      department: document.getElementById('stf-ed-dept').value,
      position_title: document.getElementById('stf-ed-pos').value,
      phone: document.getElementById('stf-ed-phone').value,
      timezone: document.getElementById('stf-ed-tz').value,
      notes: document.getElementById('stf-ed-notes').value
    }).then(function () {
      notify('Profile updated.');
      closeModal();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function statusAction(staffId, status) {
    get('detail', { id: staffId }).then(function (d) {
      state.detail = d;
      var v = status === 'suspended' ? 'suspend' : (status === 'removed' ? 'remove' : 'reactivate');
      var content = document.getElementById('stf-content');
      content.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
        '<div class="stf-modal stf-modal-s">' +
        '<div class="stf-modal-h"><div class="stf-pname">' + v.charAt(0).toUpperCase() + v.slice(1) + ' — ' + esc(d.name) + '</div></div>' +
        '<div class="stf-modal-b">' +
        (status === 'remove' ? '<p class="stf-muted">Offboarding revokes every assignment and removes access. This is recorded in the audit trail.</p>' : '') +
        (status === 'suspended' ? '<p class="stf-muted">The account stays intact but the workspace becomes inaccessible until reactivated.</p>' : '') +
        '<label class="stf-form"><label>Reason (optional)<textarea id="stf-st-reason" placeholder="Recorded in the audit trail"></textarea></label></div>' +
        '<div class="stf-modal-f"><button class="stf-btn stf-btn-danger" onclick="StaffConsole.confirmStatus(\'' + status + '\')">Confirm ' + v + '</button></div></div>';
    });
  }

  function confirmStatus(status) {
    var d = state.detail;
    post({
      action: 'status', staff_id: d.staff_id, status: status,
      reason: (document.getElementById('stf-st-reason') || {}).value || ''
    }).then(function () {
      notify(d.name + ' is now ' + status + '.');
      state.activeId = '';
      closeModal();
      renderTab();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function editAssignment(assignmentId) {
    get('detail', { id: state.activeId }).then(function (d) {
      state.detail = d;
      var a = d.assignments.filter(function (x) { return x.assignment_id === assignmentId; })[0];
      var content = document.getElementById('stf-content');
      content.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
        '<div class="stf-modal stf-modal-s">' +
        '<div class="stf-modal-h"><div class="stf-pname">Assignment — ' + esc(a.event_title) + '</div>' +
        '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.closeModal()">' + ic('x', 13) + '</button></div>' +
        '<div class="stf-modal-b stf-scroll"><div class="stf-form stf-form-row">' +
        '<label>Access begins<input id="stf-as-start" type="datetime-local" value="' + esc(a.access_start_at || '') + '"></label>' +
        '<label>Access ends<input id="stf-as-end" type="datetime-local" value="' + esc(a.access_end_at || '') + '"></label></div>' +
        '<label class="stf-form"><label>Status<select id="stf-as-status">' +
        ['active', 'scheduled', 'expired'].map(function (s) {
          return '<option value="' + s + '"' + (a.status === s ? ' selected' : '') + '>' + s + '</option>';
        }).join('') + '</select></label>' +
        '<div class="stf-muted sm">Current: ' + esc(a.role_name) + ' · ' + esc(a.status) + '</div></div>' +
        '<div class="stf-modal-f"><button class="stf-btn stf-btn-primary" onclick="StaffConsole.saveAssignment(\'' + esc(assignmentId) + '\')">Save</button></div></div>';
    });
  }

  function saveAssignment(assignmentId) {
    post({
      action: 'assignment_update', assignment_id: assignmentId,
      access_start_at: document.getElementById('stf-as-start').value,
      access_end_at: document.getElementById('stf-as-end').value,
      status: document.getElementById('stf-as-status').value
    }).then(function () {
      notify('Assignment updated.');
      closeModal();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function removeAssignment(assignmentId) {
    post({ action: 'assignment_remove', assignment_id: assignmentId }).then(function () {
      notify('Assignment removed (audited).');
      closeModal();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function createRole() {
    loadEnums().then(function () {
      var content = document.getElementById('stf-content');
      content.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
        '<div class="stf-modal stf-modal-l">' +
        '<div class="stf-modal-h"><div class="stf-pname">Create Custom Role</div>' +
        '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.closeModal()">' + ic('x', 13) + '</button></div>' +
        '<div class="stf-modal-b stf-scroll"><div class="stf-form">' +
        '<label>Role name<input id="stf-rc-name" placeholder="e.g. Festival Door Supervisor"></label>' +
        '<label>Description<textarea id="stf-rc-desc"></textarea></label>' +
        '<label>Scope<select id="stf-rc-scope">' +
        Object.keys(state.enums.scopes).map(function (k) {
          return '<option value="' + k + '">' + esc(state.enums.scopes[k]) + '</option>';
        }).join('') + '</select></label></div>' +
        '<div class="stf-card-h">Permissions</div><div id="stf-rc-matrix" class="stf-scroll"></div></div>' +
        '<div class="stf-modal-f"><button class="stf-btn stf-btn-primary" onclick="StaffConsole.saveRole()">Save role</button></div></div>';
      state.customPerms = {};
      document.getElementById('stf-rc-matrix').innerHTML = matrixGroups(state.customPerms).join('');
      bindMatrixEls();
    });
  }

  function bindMatrixEls() {
    document.querySelectorAll('.stf-mat-sel').forEach(function (s) {
      s.addEventListener('change', function () {
        state.customPerms = state.customPerms || {};
        state.customPerms[s.getAttribute('data-m')] = s.value;
      });
    });
  }

  function saveRole() {
    var name = document.getElementById('stf-rc-name').value.trim();
    if (!name) { notify('Role name is required.', 'error'); return; }
    var perms = {};
    document.querySelectorAll('.stf-mat-sel').forEach(function (s) {
      perms[s.getAttribute('data-m')] = s.value;
    });
    post({
      action: 'role_save', name: name,
      description: document.getElementById('stf-rc-desc').value,
      scope_type: document.getElementById('stf-rc-scope').value,
      permissions: JSON.stringify(perms)
    }).then(function (r) {
      notify('Role "' + name + '" created.');
      closeModal();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function roleMatrixEdit(roleId) {
    roleMatrix(roleId);
  }

  function saveMatrix() {
    var rd = state.roleMatrix;
    var perms = {};
    document.querySelectorAll('.stf-mat-sel').forEach(function (s) {
      perms[s.getAttribute('data-m')] = s.value;
    });
    post({
      action: 'role_save', role_id: rd.id, name: rd.name, description: rd.description || '',
      scope_type: rd.scope_type, permissions: JSON.stringify(perms)
    }).then(function () {
      notify('Permissions saved for ' + rd.name + '.');
      closeModal();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function deleteRole(roleId, roleName) {
    var content = document.getElementById('stf-content');
    content.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
      '<div class="stf-modal stf-modal-s">' +
      '<div class="stf-modal-h"><div class="stf-pname">Delete role — ' + esc(roleName) + '</div></div>' +
      '<div class="stf-modal-b"><p class="stf-muted">This removes the role permanently. Members must be reassigned first.</p></div>' +
      '<div class="stf-modal-f"><button class="stf-btn stf-btn-danger" onclick="StaffConsole.confirmDeleteRole(\'' + esc(roleId) + '\')">Delete role</button></div></div>';
  }

  function confirmDeleteRole(roleId) {
    post({ action: 'role_delete', role_id: roleId }).then(function () {
      notify('Role deleted.');
      closeModal();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function roleModal(staffId) {
    get('detail', { id: staffId }).then(function (d) {
      state.detail = d;
      loadRoles().then(function () {
        var opts = state.roles.map(function (r) {
          return '<option value="' + esc(r.id) + '"' + (r.id === d.role_id ? ' selected' : '') + '>' + esc(r.name) + '</option>';
        }).join('');
        var content = document.getElementById('stf-content');
        content.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
          '<div class="stf-modal stf-modal-s">' +
          '<div class="stf-modal-h"><div class="stf-pname">Change role — ' + esc(d.name) + '</div>' +
          '<button class="stf-btn stf-btn-ghost sm" onclick="StaffConsole.closeModal()">' + ic('x', 13) + '</button></div>' +
          '<div class="stf-modal-b"><label>New role<select id="stf-cr-role">' + opts + '</select></label>' +
          '<div class="stf-muted sm">The change is recorded with previous and new role in the audit trail.</div></div>' +
          '<div class="stf-modal-f"><button class="stf-btn stf-btn-primary" onclick="StaffConsole.confirmRole()">Change role</button></div></div>';
      });
    });
  }

  function confirmRole() {
    var d = state.detail;
    post({ action: 'role_change', staff_id: d.staff_id, role_id: document.getElementById('stf-cr-role').value })
      .then(function (r) {
        notify(d.name + ' is now ' + r.new_role + '.');
        closeModal();
      }).catch(function (e) { notify(e.message, 'error'); });
  }

  function bulkAction(action) {
    if (!state.bulk.length) return;
    var content = document.getElementById('stf-content');
    content.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
      '<div class="stf-modal stf-modal-s">' +
      '<div class="stf-modal-h"><div class="stf-pname">' + (action === 'suspend' ? 'Suspend' : 'Remove') + ' ' + state.bulk.length + ' selected</div></div>' +
      '<div class="stf-modal-b"><label>Reason<textarea id="stf-bk-reason"></textarea></label>' +
      '<div class="stf-muted sm">This is recorded in the audit trail.</div></div>' +
      '<div class="stf-modal-f"><button class="stf-btn stf-btn-danger" onclick="StaffConsole.confirmBulk(\'' + action + '\')">Confirm</button></div></div>';
  }

  function confirmBulk(action) {
    post({
      action: 'bulk', action_kind: action,
      staff_ids: JSON.stringify(state.bulk),
      reason: (document.getElementById('stf-bk-reason') || {}).value || ''
    }).then(function (r) {
      notify(r.succeeded + ' of ' + state.bulk.length + ' processed.');
      state.bulk = [];
      closeModal();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function bulkRole() {
    loadRoles().then(function () {
      var opts = state.roles.map(function (r) {
        return '<option value="' + esc(r.id) + '">' + esc(r.name) + '</option>';
      }).join('');
      var content = document.getElementById('stf-content');
      content.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
        '<div class="stf-modal stf-modal-s">' +
        '<div class="stf-modal-h"><div class="stf-pname">Change role — ' + state.bulk.length + ' selected</div></div>' +
        '<div class="stf-modal-b"><label>Role<select id="stf-bk-role">' + opts + '</select></label></div>' +
        '<div class="stf-modal-f"><button class="stf-btn stf-btn-primary" onclick="StaffConsole.confirmBulkRole()">Change</button></div></div>';
    });
  }

  function confirmBulkRole() {
    post({ action: 'bulk', action_kind: 'change_role', staff_ids: JSON.stringify(state.bulk), role_id: document.getElementById('stf-bk-role').value })
      .then(function (r) {
        notify(r.succeeded + ' of ' + state.bulk.length + ' changed.');
        state.bulk = [];
        closeModal();
      }).catch(function (e) { notify(e.message, 'error'); });
  }

  function bulkAssign() {
    loadEvents().then(function () {
      var evs = '<div class="stf-ev-pick" id="stf-bk-evs">' + state.events.map(function (e) {
        return '<label class="stf-ev-opt"><input type="checkbox" value="' + esc(e.event_id) + '">' +
          '<span><strong>' + esc(e.title) + '</strong></span></label>';
      }).join('') + '</div>';
      var content = document.getElementById('stf-content');
      content.innerHTML = '<div class="stf-modal-bg" onclick="StaffConsole.closeModal()"></div>' +
        '<div class="stf-modal stf-modal-m">' +
        '<div class="stf-modal-h"><div class="stf-pname">Assign event — ' + state.bulk.length + ' selected</div></div>' +
        '<div class="stf-modal-b stf-scroll"><label>Role<select id="stf-bk-role2">' + state.roles.map(function (r) {
          return '<option value="' + esc(r.id) + '">' + esc(r.name) + '</option>';
        }).join('') + '</select></label>' + evs +
        '<div class="stf-form stf-form-row"><label>Begins<input id="stf-bk-start" type="datetime-local"></label>' +
        '<label>Ends<input id="stf-bk-end" type="datetime-local"></label></div></div>' +
        '<div class="stf-modal-f"><button class="stf-btn stf-btn-primary" onclick="StaffConsole.confirmBulkAssign()">Assign</button></div></div>';
    });
  }

  function confirmBulkAssign() {
    var evs = Array.from(document.querySelectorAll('#stf-bk-evs input:checked')).map(function (x) { return x.value; });
    if (!evs.length) { notify('Select at least one event.', 'error'); return; }
    post({
      action: 'bulk', action_kind: 'assign_event',
      staff_ids: JSON.stringify(state.bulk), event_ids: JSON.stringify(evs),
      role_id: document.getElementById('stf-bk-role2').value,
      access_start_at: document.getElementById('stf-bk-start').value,
      access_end_at: document.getElementById('stf-bk-end').value
    }).then(function (r) {
      notify(r.succeeded + ' of ' + state.bulk.length + ' assigned.');
      state.bulk = [];
      closeModal();
    }).catch(function (e) { notify(e.message, 'error'); });
  }

  function clearBulk() {
    state.bulk = [];
    var bar = document.getElementById('stf-bulk');
    if (bar) bar.outerHTML = bulkBar(state.docs);
    document.querySelectorAll('#stf-list .stf-check input').forEach(function (c) { c.checked = false; });
  }

  /* ── navigation helpers exposed globally ─────────────────────────── */

  function closePanel() {
    state.activeId = '';
    renderTab();
  }

  function back() {
    state.activeId = '';
    renderTab();
  }

  function setMatrix(m) { state.matrixMode = m ? 'events' : 'matrix'; renderTab(); }
  function setScope(s) { state.activityScope = s; renderTab(); }
  function q(v) { state.q = v; renderTab(); }
  function set(k, v) { state[k] = v; renderTab(); }

  function filterKpi(kv) {
    var parts = (kv || '').split('&');
    for (var i = 0; i < parts.length; i++) {
      var pair = parts[i].split('=');
      if (pair[0] === 'status' && pair[1] === 'all') pair[1] = '';
      state[pair[0]] = decodeURIComponent(pair[1] || '');
    }
    go('staff');
  }

  function msg(staffId) {
    get('detail', { id: staffId }).then(function (d) {
      notify('Opening Messages with ' + d.name + '…');
      state.activeId = '';
      switchEccModule('messages');
      setTimeout(function () {
        var mm = window.MessagesControlCenter;
        if (mm && mm.openRecipient) mm.openRecipient(d.user_id);
      }, 600);
    });
  }

  function docs(userId) {
    notify('Opening Documents for this staff member…');
    state.activeId = '';
    switchEccModule('documents');
    setTimeout(function () {
      var dc = window.DocumentsControlCenter;
      if (dc && dc.searchInput) dc.searchInput('');
    }, 500);
  }

  window.StaffConsole = {
    go: go, refresh: refresh, invite: invite, stepInvite: stepInvite, sendInvite: sendInvite,
    closeModal: closeModal, resendInvite: resendInvite, revokeInvite: revokeInvite,
    openProfile: openProfile, back: back, closePanel: closePanel,
    manageAccess: manageAccess, saveAccess: saveAccess, editStaff: editStaff, saveProfile: saveProfile,
    statusAction: statusAction, confirmStatus: confirmStatus,
    editAssignment: editAssignment, saveAssignment: saveAssignment, removeAssignment: removeAssignment,
    roleMatrix: roleMatrix, saveMatrix: saveMatrix, createRole: createRole, saveRole: saveRole,
    deleteRole: deleteRole, confirmDeleteRole: confirmDeleteRole, roleModal: roleModal, confirmRole: confirmRole,
    bulkAction: bulkAction, confirmBulk: confirmBulk, bulkRole: bulkRole, confirmBulkRole: confirmBulkRole,
    bulkAssign: bulkAssign, confirmBulkAssign: confirmBulkAssign, clearBulk: clearBulk,
    setMatrix: setMatrix, setScope: setScope, q: q, set: set, filterKpi: filterKpi,
    viewTeam: viewTeam, msg: msg, docs: docs
  };

  function switchEccModule(moduleKey) {
    var els = document.querySelectorAll('.ecc-nav-item[data-mod]');
    for (var i = 0; i < els.length; i++) {
      if (els[i].getAttribute('data-mod') === moduleKey) { els[i].click(); return; }
    }
  }

  shell();
})();