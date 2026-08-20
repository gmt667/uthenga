/* Uthenga — Documents Console (Events V2).
 * The organizer's document repository: uploads, blank/template documents,
 * generated reports (attendance, finance, ticket sales, customers, reviews,
 * event summaries) rendered live from operating data, plus versions, shares,
 * statuses, locks and a full activity trail. Files are served behind
 * authentication — this console only ever sees base64 bytes from the API.
 */
window.DocumentsControlCenter = (function() {
  'use strict';

  var evDoc = document.getElementById('events-workspace');
  if (!evDoc) return {};
  var base = evDoc.dataset.baseUrl ? evDoc.dataset.baseUrl : '';
  var csrf = evDoc.dataset.csrf ? evDoc.dataset.csrf : '';
  var api = base + 'api/tie/vendor/events/documents.php';

  var state = {
    tab: 'library',
    view: 'all',
    mode: 'grid',
    q: '',
    category: '',
    eventId: '',
    status: '',
    docType: '',
    creator: '',
    sort: 'updated',
    enums: null,
    events: [],
    overview: null,
    docs: null,
    templates: null,
    detail: null,
    activity: null,
    activeId: '',
    booted: false,
    loading: false
  };

  /* ── Helpers ────────────────────────────────────────────────────── */

  function esc(s) { return window.tkEsc ? tkEsc(s) : String(s == null ? '' : s); }
  function money(n) { return window.tkMoney ? tkMoney(n) : ('MK ' + (Number(n) || 0).toLocaleString()); }
  function date(s, short) {
    if (!s) return '—';
    var d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(s);
    return d.toLocaleString('en-GB', short
      ? { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }
      : { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }
  function num(n) { return Number(n) || 0; }
  function toast(m, err) {
    if (window.eccNotify) { window.eccNotify(m); return; }
    var el = document.createElement('div');
    el.textContent = m;
    el.style.cssText = 'position:fixed;bottom:' + (err ? '70px' : '20px') + ';right:20px;z-index:99999;background:' + (err ? '#e63946' : '#10b981') + ';color:#fff;padding:10px 16px;border-radius:10px;font:700 13px Inter,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.25)';
    document.body.appendChild(el);
    setTimeout(function() { el.remove(); }, 3200);
  }
  function overlay() {
    var o = document.getElementById('docs-modal');
    if (o) o.remove();
    o = document.createElement('div');
    o.id = 'docs-modal';
    o.className = 'docs-modal';
    o.innerHTML = '<div class="docs-modal-card"></div>';
    o.addEventListener('click', function(e) { if (e.target === o) o.remove(); });
    document.body.appendChild(o);
    return o.querySelector('.docs-modal-card');
  }
  function closeModal() { var o = document.getElementById('docs-modal'); if (o) o.remove(); }
  function ic(name, size) {
    var p = {
      doc: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
      search: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
      upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
      download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
      plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
      folder: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
      history: '<path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><polyline points="12 7 12 12 15 15"/>',
      lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
      unlock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
      share: '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>',
      eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
      trash: '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
      edit: '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
      grid: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
      list: '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
      refresh: '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
      layers: '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
      zap: '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
      shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
      check: '<polyline points="20 6 9 17 4 12"/>',
      x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
      back: '<polyline points="15 18 9 12 15 6"/>',
      tag: '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
      star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
      users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
      alert: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
      printer: '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
      fileText: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
      card: '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
      note: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
      bell: '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'
    }[name] || '';
    var s = size || 14;
    return '<svg viewBox="0 0 24 24" width="' + s + '" height="' + s + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-0.15em;flex:none;">' + p + '</svg>';
  }
  function badg(s) { return String(s || 'DRAFT').toLowerCase().replace(/_/g, ''); }
  function fileGlyph(dt) {
    var g = {
      PDF: 'fileText', CSV: 'list', DOC: 'edit', DOCX: 'edit', XLS: 'grid', XLSX: 'grid',
      PNG: 'star', JPG: 'star', HTM: 'doc', TXT: 'note'
    };
    return g[String(dt || 'PDF').toUpperCase()] || 'doc';
  }
  function typeColor(dt) {
    var c = { PDF: '#e5484d', CSV: '#10b981', DOC: '#3b82f6', DOCX: '#3b82f6', XLS: '#0e9f6e', XLSX: '#0e9f6e', PNG: '#8b5cf6', JPG: '#8b5cf6', HTM: '#0f6fd8', TXT: '#64748b' };
    return c[String(dt || 'PDF').toUpperCase()] || '#64748b';
  }
  function statusColor(s) {
    var c = { DRAFT: '#64748b', PENDING_REVIEW: '#f59e0b', APPROVED: '#0f6fd8', FINAL: '#10b981', ARCHIVED: '#8b5cf6' };
    return c[String(s || 'DRAFT').toUpperCase()] || '#64748b';
  }
  function readAsB64(file, cb) {
    var r = new FileReader();
    r.onload = function() {
      var s = String(r.result || '').split(',')[1] || '';
      cb(s);
    };
    r.onerror = function() { cb(''); };
    r.readAsDataURL(file);
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
        return j.documents_result !== undefined ? j.documents_result : j;
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
      return j.documents_result !== undefined ? j.documents_result : j;
    });
  }

  /* ── Shell ─────────────────────────────────────────────────────── */

  function shell() {
    var ov = state.overview || { counts: {}, storage: { label: '', pct: 0 }, recent: [] };
    var c = ov.counts || {};
    var st = ov.storage || {};
    var kpis =
      '<div class="docs-kpi"><div class="docs-kpi-t">Total documents</div><div class="docs-kpi-v">' + num(c.total) + '</div></div>' +
      '<div class="docs-kpi"><div class="docs-kpi-t">Active</div><div class="docs-kpi-v">' + num(c.active) + '</div></div>' +
      '<div class="docs-kpi"><div class="docs-kpi-t">Shared</div><div class="docs-kpi-v">' + num(c.shared) + '</div></div>' +
      '<div class="docs-kpi docs-kpi-wide"><div class="docs-kpi-t">Storage</div>' +
      '<div class="docs-storage"><div class="docs-storage-bar"><span style="width:' + Math.min(num(st.pct), 100) + '%"></span></div>' +
      '<div class="docs-storage-lbl">' + esc(st.label || '0 B of 10 GB') + '</div></div></div>' +
      '<div class="docs-kpi"><div class="docs-kpi-t">This month</div><div class="docs-kpi-v">+' + num(c.recent_30d) + '</div></div>';

    var root = document.getElementById('docs-root');
    if (!root) return;
    root.innerHTML =
      '<div class="docs-shell">' +
      '<div class="docs-head"><div class="docs-head-t"><div class="docs-title">Documents</div>' +
      '<div class="docs-sub">Controlled repository — uploads, templates and live-generated reports.</div></div>' +
      '<div class="docs-head-actions">' +
      '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.reload()">' + ic('refresh', 14) + ' Refresh</button>' +
      '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.uploadModal()">' + ic('upload', 14) + ' Upload</button>' +
      '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.newDocModal()">' + ic('doc', 14) + ' New</button>' +
      '<button class="docs-btn docs-btn-primary" onclick="DocumentsControlCenter.generateModal()">' + ic('zap', 14) + ' Generate report</button>' +
      '</div></div>' +
      '<div class="docs-kpis">' + kpis + '</div>' +
      '<div class="docs-tabs">' +
      '<button class="docs-tab' + (state.tab === 'library' ? ' active' : '') + '" data-dt="library">Library <span>' + num(c.active) + '</span></button>' +
      '<button class="docs-tab' + (state.tab === 'generate' ? ' active' : '') + '" data-dt="generate">Generate</button>' +
      '<button class="docs-tab' + (state.tab === 'templates' ? ' active' : '') + '" data-dt="templates">Templates</button>' +
      '<button class="docs-tab' + (state.tab === 'activity' ? ' active' : '') + '" data-dt="activity">Activity</button>' +
      '</div><div class="docs-content" id="docs-content"></div></div>';
    root.querySelectorAll('.docs-tab').forEach(function(b) {
      b.addEventListener('click', function() {
        state.tab = b.getAttribute('data-dt');
        state.activeId = '';
        shell();
      });
    });
    renderTab();
  }

  function renderTab() {
    var box = document.getElementById('docs-content');
    if (!box) return;
    if (state.tab === 'library') box.innerHTML = library();
    else if (state.tab === 'generate') box.innerHTML = generateTab();
    else if (state.tab === 'templates') box.innerHTML = templatesTab();
    else box.innerHTML = activityTab();
    bindTab();
  }

  function bindTab() {
    if (state.tab === 'library') {
      bindLibrary();
      bindDetailPanel();
    } else if (state.tab === 'generate') bindGenerate();
    else if (state.tab === 'templates') bindTemplatesTab();
    else bindActivity();
  }

  /* ── Library ───────────────────────────────────────────────────── */

  function viewChips() {
    var views = [['all', 'All'], ['shared', 'Shared'], ['reports', 'Reports'], ['archived', 'Archived']];
    var h = '<div class="docs-chips">';
    views.forEach(function(v) {
      h += '<button class="docs-chip' + (state.view === v[0] ? ' active' : '') + '" data-vw="' + v[0] + '">' + v[1] + '</button>';
    });
    return h + '</div>';
  }

  function library() {
    var ev = state.events || [];
    var en = state.enums || {};
    var cats = (en.categories || []).slice(0, 5);
    var folders = ((state.overview || {}).event_folders || []).slice(0, 5);
    var sidebar =
      '<div class="docs-side">' +
      '<div class="docs-side-h">Categories</div>';
    cats.forEach(function(ca) {
      var n = ((state.overview || {}).categories || []).reduce(function(a, r) { return r.category === ca ? a + num(r.n) : a; }, 0);
      sidebar += '<button class="docs-side-item' + (state.category === ca ? ' active' : '') + '" data-cat="' + ca + '">' + ic('folder', 13) + esc(ca) + '<span>' + n + '</span></button>';
    });
    sidebar += '<div class="docs-side-h" style="margin-top:14px">Event folders</div>';
    if (!folders.length) sidebar += '<div class="docs-side-empty">No event folders yet.</div>';
    folders.forEach(function(f) {
      sidebar += '<button class="docs-side-item' + (state.eventId === f.event_id ? ' active' : '') + '" data-ev="' + f.event_id + '">' + ic('zap', 13) + '<span class="docs-side-t" title="' + esc(f.title) + '">' + esc(f.title) + '</span><span>' + num(f.documents) + '</span></button>';
    });
    sidebar += '</div>';

    var fOpts = state.filters || {};
    var typeOpts = (fOpts.doc_types || []).map(function(t) { return '<option value="' + esc(t) + '"' + (state.docType === t ? ' selected' : '') + '>' + esc(t) + '</option>'; }).join('');
    var creatorOpts = (fOpts.creators || []).map(function(t) { return '<option value="' + esc(t) + '"' + (state.creator === t ? ' selected' : '') + '>' + esc(t) + '</option>'; }).join('');
    var evOpts = ev.map(function(e) { return '<option value="' + esc(e.event_id) + '"' + (state.eventId === e.event_id ? ' selected' : '') + '>' + esc(e.title) + '</option>'; }).join('');
    var statusOpts = (['DRAFT', 'PENDING_REVIEW', 'APPROVED', 'FINAL', 'ARCHIVED']).map(function(s) { return '<option value="' + s + '"' + (state.status === s ? ' selected' : '') + '>' + s.replace(/_/g, ' ') + '</option>'; }).join('');
    var catOpts = (en.categories || []).map(function(ca) { return '<option value="' + ca + '"' + (state.category === ca ? ' selected' : '') + '>' + ca[0] + ca.slice(1).toLowerCase() + '</option>'; }).join('');

    var toolbar =
      '<div class="docs-toolbar">' +
      '<div class="docs-search">' + ic('search', 14) + '<input id="docs-q" placeholder="Search documents, tags, events…" value="' + esc(state.q) + '" oninput="DocumentsControlCenter.searchInput(this.value)"></div>' +
      '<select id="docs-cat" onchange="DocumentsControlCenter.setFilter(\'category\', this.value)"><option value="">All categories</option>' + catOpts + '</select>' +
      '<select id="docs-ev" onchange="DocumentsControlCenter.setFilter(\'event_id\', this.value)"><option value="">All events</option>' + evOpts + '</select>' +
      '<select id="docs-type" onchange="DocumentsControlCenter.setFilter(\'docType\', this.value)"><option value="">All types</option>' + typeOpts + '</select>' +
      '<select id="docs-status" onchange="DocumentsControlCenter.setFilter(\'status\', this.value)"><option value="">All statuses</option>' + statusOpts + '</select>' +
      '<select id="docs-creator" onchange="DocumentsControlCenter.setFilter(\'creator\', this.value)"><option value="">All creators</option>' + creatorOpts + '</select>' +
      '<select id="docs-sort" onchange="DocumentsControlCenter.setFilter(\'sort\', this.value)">' +
      '<option value="updated"' + (state.sort === 'updated' ? ' selected' : '') + '>Recently updated</option>' +
      '<option value="created"' + (state.sort === 'created' ? ' selected' : '') + '>Recently created</option>' +
      '<option value="name"' + (state.sort === 'name' ? ' selected' : '') + '>Name A–Z</option>' +
      '<option value="size"' + (state.sort === 'size' ? ' selected' : '') + '>Largest first</option></select>' +
      '<button class="docs-ic-btn' + (state.mode === 'grid' ? ' active' : '') + '" title="Grid" onclick="DocumentsControlCenter.toggleMode(\'grid\')">' + ic('grid', 15) + '</button>' +
      '<button class="docs-ic-btn' + (state.mode === 'list' ? ' active' : '') + '" title="List" onclick="DocumentsControlCenter.toggleMode(\'list\')">' + ic('list', 15) + '</button>' +
      '</div>' + viewChips();

    var list = '';
    var docs = (state.docs || {}).items || [];
    if (!docs.length) {
      list = '<div class="docs-empty">' + ic('folder', 40) + '<div class="docs-empty-t">No documents here yet</div>' +
        '<div class="docs-empty-s">Upload files, create a document from a template, or generate a report from your live event data.</div>' +
        '<div style="display:flex;gap:8px;justify-content:center;margin-top:10px">' +
        '<button class="docs-btn docs-btn-primary" onclick="DocumentsControlCenter.uploadModal()">' + ic('upload', 14) + ' Upload files</button>' +
        '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.generateModal()">' + ic('zap', 14) + ' Generate a report</button></div></div>';
    } else if (state.mode === 'grid') {
      list = '<div class="docs-grid">' + docs.map(docCard).join('') + '</div>';
    } else {
      list = '<div class="docs-list">' + docs.map(docRow).join('') + '</div>';
    }

    var detail = state.activeId
      ? '<div class="docs-panel" id="docs-detail-panel">' + detailPanel(state.detail) + '</div>'
      : '<div class="docs-panel docs-panel-empty"><div class="docs-empty-s">Select a document to preview, edit metadata, browse versions and manage sharing.</div></div>';

    return '<div class="docs-main">' + sidebar +
      '<div class="docs-body">' + toolbar + list + '</div>' + detail + '</div>';
  }

  function docStatusChip(d) {
    return '<span class="docs-chip-st" style="background:' + statusColor(d.status) + '22;color:' + statusColor(d.status) + '">' + d.status + '</span>';
  }

  function docCard(d) {
    var extras = '';
    if (d.locked) extras += '<span class="docs-chip-st" style="background:#e5484d22;color:#e5484d">' + ic('lock', 10) + ' Locked</span>';
    if (d.legal_hold) extras += '<span class="docs-chip-st" style="background:#8b5cf622;color:#8b5cf6">' + ic('shield', 10) + ' Legal hold</span>';
    if (num(d.shared_with)) extras += '<span class="docs-chip-st" style="background:#0f6fd822;color:#0f6fd8">' + ic('users', 10) + ' ' + num(d.shared_with) + '</span>';
    var tags = '';
    (d.tags || []).slice(0, 3).forEach(function(t) { tags += '<span class="docs-tag">' + esc(t) + '</span>'; });
    return '<div class="docs-card' + (d.id === state.activeId ? ' active' : '') + '" data-id="' + esc(d.id) + '">' +
      '<div class="docs-card-ic" style="background:' + typeColor(d.doc_type) + '1a;color:' + typeColor(d.doc_type) + '">' + ic(fileGlyph(d.doc_type), 20) + '</div>' +
      '<div class="docs-card-b">' +
      '<div class="docs-card-t">' + esc(d.name) + '<span class="docs-card-ty">' + esc(d.doc_type) + ' · v' + num(d.version) + '</span></div>' +
      '<div class="docs-card-m">' + esc(d.category) + ' · ' + esc(d.size_label) + ' · ' + esc(d.created_by) + '</div>' +
      (d.event_title ? '<div class="docs-card-m">' + esc(d.event_title) + '</div>' : '') +
      '<div class="docs-card-f">' + docStatusChip(d) + extras + '<span class="docs-card-when">' + date(d.updated_at, true) + '</span></div>' + tags +
      '</div></div>';
  }

  function docRow(d) {
    return '<div class="docs-row' + (d.id === state.activeId ? ' active' : '') + '" data-id="' + esc(d.id) + '">' +
      '<div class="docs-row-ic" style="background:' + typeColor(d.doc_type) + '1a;color:' + typeColor(d.doc_type) + '">' + ic(fileGlyph(d.doc_type), 17) + '</div>' +
      '<div class="docs-row-name"><div class="docs-card-t">' + esc(d.name) + '</div>' +
      '<div class="docs-card-m">' + esc(d.event_title || d.category) + ' · ' + esc(d.size_label) + ' · ' + esc(d.created_by) + '</div></div>' +
      docStatusChip(d) +
      '<span class="docs-row-meta">' + esc(d.doc_type) + ' · v' + num(d.version) + '</span>' +
      '<span class="docs-row-meta">' + date(d.updated_at, true) + '</span>' +
      (d.locked ? '<span style="color:#e5484d">' + ic('lock', 13) + '</span>' : '') +
      '</div>';
  }

  function bindLibrary() {
    document.querySelectorAll('#docs-content .docs-card, #docs-content .docs-row').forEach(function(el) {
      el.addEventListener('click', function() {
        state.activeId = el.getAttribute('data-id');
        loadDetail();
      });
    });
    document.querySelectorAll('#docs-content .docs-chip').forEach(function(el) {
      el.addEventListener('click', function() {
        state.view = el.getAttribute('data-vw');
        state.activeId = '';
        loadList();
      });
    });
    document.querySelectorAll('#docs-content .docs-side-item').forEach(function(el) {
      el.addEventListener('click', function() {
        var ca = el.getAttribute('data-cat');
        var ev = el.getAttribute('data-ev');
        if (ca) { state.category = state.category === ca ? '' : ca; state.eventId = ''; }
        if (ev) { state.eventId = state.eventId === ev ? '' : ev; state.category = ''; }
        loadList();
      });
    });
  }

  /* ── Detail / preview panel ────────────────────────────────────── */

  function detailPanel(d) {
    if (!d) return '<div class="docs-panel-l"><div class="docs-panel-loading">' + ic('refresh', 16) + ' Loading…</div></div>';
    var doc = d.document || {};
    var meta =
      '<div class="docs-pv" id="docs-pv"><div class="docs-pv-ic" style="background:' + typeColor(doc.doc_type) + '1a;color:' + typeColor(doc.doc_type) + '">' + ic(fileGlyph(doc.doc_type), 22) + '</div>' +
      '<div style="min-width:0"><div class="docs-pv-name">' + esc(doc.name) + '</div>' +
      '<div class="docs-pv-meta">' + esc(doc.doc_type) + ' · ' + esc(doc.size_label) + ' · v' + num(doc.version) + ' · ' + esc(doc.created_by) + '</div></div></div>';

    var rules = '';
    if (doc.event_title) rules += '<div class="docs-meta"><span>Event</span><b>' + esc(doc.event_title) + '</b></div>';
    rules += '<div class="docs-meta"><span>Category</span><b>' + esc(doc.category) + '</b></div>';
    rules += '<div class="docs-meta"><span>Status</span><b style="color:' + statusColor(doc.status) + '">' + (doc.status || '') + '</b></div>';
    rules += '<div class="docs-meta"><span>Source</span><b>' + esc(doc.source_kind || 'UPLOAD') + (doc.source_label ? ' · ' + esc(doc.source_label) : '') + '</b></div>';
    rules += '<div class="docs-meta"><span>Created</span><b>' + date(doc.created_at) + '</b></div>';
    rules += '<div class="docs-meta"><span>Last viewed</span><b>' + date(doc.last_viewed_at, true) + '</b></div>';
    if (doc.legal_hold) rules += '<div class="docs-meta"><span>' + ic('shield', 12) + '</span><b style="color:#8b5cf6">Under legal hold — cannot be deleted</b></div>';

    var tags = '';
    (doc.tags || []).forEach(function(t) { tags += '<span class="docs-tag">' + esc(t) + '</span>'; });
    var shareBtn = '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.shareModal()">' + ic('share', 13) + ' Share</button>';
    var lockBtn = doc.locked
      ? '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.lock(false)">' + ic('unlock', 13) + ' Unlock</button>'
      : '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.lock(true)">' + ic('lock', 13) + ' Lock</button>';

    var html =
      '<div class="docs-panel-l">' + meta +
      '<div style="display:flex;gap:6px;flex-wrap:wrap;margin:10px 0">' +
      '<button class="docs-btn docs-btn-primary" onclick="DocumentsControlCenter.preview()">' + ic('eye', 13) + ' Preview</button>' +
      '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.download()">' + ic('download', 13) + ' Download</button>' +
      shareBtn + lockBtn +
      '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.renameModal()">' + ic('edit', 13) + ' Rename</button>' +
      '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.moveModal()">' + ic('folder', 13) + ' Move</button>' +
      '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.tagsModal()">' + ic('tag', 13) + ' Tags</button>' +
      '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.versionModal()">' + ic('history', 13) + ' New version</button>' +
      (doc.status === 'ARCHIVED'
        ? '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.unarchive()">' + ic('back', 13) + ' Unarchive</button>'
        : '<button class="docs-btn docs-btn-ghost" onclick="DocumentsControlCenter.archive()">' + ic('eye', 13) + ' Archive</button>') +
      '<button class="docs-btn docs-btn-danger" ' + (doc.legal_hold ? 'disabled' : '') + ' onclick="DocumentsControlCenter.del()">' + ic('trash', 13) + ' Delete</button>' +
      '</div>' +
      '<div class="docs-sec"><div class="docs-sec-h">Metadata</div>' + rules +
      '<div class="docs-meta"><span>Tags</span><b>' + (tags || '—') + '</b></div></div>' +
      '<div class="docs-sec"><div class="docs-sec-h">Versions (' + (d.versions || []).length + ')</div>' +
      '<div class="docs-versions">' + (d.versions || []).map(function(v) {
        var act = v.version === num(doc.version) ? ' (current)' : '';
        var rest = v.version === num(doc.version) ? '' : '<button class="docs-btn docs-btn-ghost docs-mini" onclick="DocumentsControlCenter.restore(' + v.version + ')">Restore</button>';
        return '<div class="docs-ver"><div class="docs-ver-v">v' + v.version + act + '</div>' +
          '<div class="docs-ver-m">' + esc(v.note || '') + ' · ' + esc(v.size_label) + ' · ' + esc(v.created_by) + '</div>' +
          '<div class="docs-ver-when">' + date(v.created_at, true) + '</div>' + rest + '</div>';
      }).join('') + '</div></div>' +
      '<div class="docs-sec"><div class="docs-sec-h">Shared with (' + (d.shares || []).length + ')' +
      '<button class="docs-btn docs-btn-ghost docs-mini" onclick="DocumentsControlCenter.shareModal()">' + ic('plus', 12) + '</button></div>' +
      '<div class="docs-shares">' + ((d.shares || []).length
        ? (d.shares || []).map(function(s) {
            return '<div class="docs-share"><div class="docs-share-n">' + ic('users', 12) + ' ' + esc(s.sharee_name) +
              '<span class="docs-chip-st" style="background:#0f6fd822;color:#0f6fd8">' + s.permission + '</span></div>' +
              '<button class="docs-btn docs-btn-ghost docs-mini" onclick="DocumentsControlCenter.unshare(\'' + esc(s.sharee_name).replace(/'/g, "\\'") + '\')">' + ic('x', 11) + '</button></div>';
          }).join('')
        : '<div class="docs-empty-s">Not shared.</div>') + '</div></div>' +
      '<div class="docs-sec"><div class="docs-sec-h">Activity</div><div class="docs-activity">' +
      (d.activity || []).map(function(a) {
        return '<div class="docs-act"><div class="docs-act-t">' +
          '<b>' + esc(a.actor) + '</b> ' + esc(String(a.action || '').replace(/_/g, ' ')) + '</div>' +
          '<div class="docs-act-w">' + date(a.at, true) + '</div></div>';
      }).join('') + '</div></div>' +
      '</div>';
    return html;
  }

  function bindDetailPanel() {
    var pv = document.getElementById('docs-pv');
    if (pv) pv.style.cursor = 'pointer';
  }

  function iframePreview(dataUrl, name) {
    var ov = overlay();
    ov.innerHTML =
      '<div class="docs-modal-head"><div><div class="docs-modal-title">Preview — ' + esc(name) + '</div>' +
      '<div class="docs-modal-sub">Served securely through the authenticated file endpoint.</div></div>' +
      '<button class="docs-btn docs-btn-ghost docs-mini" onclick="closeModal()">' + ic('x', 14) + '</button></div>' +
      '<div class="docs-modal-body docs-preview-body"><iframe src="' + dataUrl + '" style="width:100%;height:100%;border:0;border-radius:8px"></iframe></div>';
  }

  /* ── Actions ───────────────────────────────────────────────────── */

  function active() { return (state.detail || {}).document || {}; }
  function refreshAfter(msg) {
    toast(msg || 'Done.');
    return Promise.all([loadList(), loadDetail()]);
  }

  function loadList() {
    state.loading = true;
    var box = document.getElementById('docs-content');
    return get('documents', {
      view: state.view, q: state.q, category: state.category, event_id: state.eventId,
      doc_type: state.docType, status: state.status, creator: state.creator, sort: state.sort, limit: 120
    }).then(function(r) { state.docs = r; }).catch(function(e) { toast(e.message, true); })
      .then(function() { state.loading = false; renderTab(); });
  }

  function loadDetail() {
    if (!state.activeId) { state.detail = null; renderTab(); return Promise.resolve(); }
    return get('detail', { id: state.activeId }).then(function(r) {
      state.detail = r;
      var panel = document.getElementById('docs-detail-panel');
      if (panel) { panel.innerHTML = detailPanel(r); bindDetailPanel(); }
      else renderTab();
    }).catch(function(e) { toast(e.message, true); state.activeId = ''; });
  }

  function confirmAct(title, text, danger, okLabel, fn) {
    var ov = overlay();
    ov.innerHTML =
      '<div class="docs-modal-head"><div><div class="docs-modal-title">' + title + '</div>' +
      '<div class="docs-modal-sub">' + esc(text) + '</div></div></div>' +
      '<div class="docs-modal-body" style="text-align:center;padding:18px">' + ic(danger ? 'alert' : 'folder', 30) + '</div>' +
      '<div class="docs-modal-ft">' +
      '<button class="docs-btn docs-btn-ghost" onclick="closeModal()">Cancel</button>' +
      '<button class="docs-btn ' + (danger ? 'docs-btn-danger' : 'docs-btn-primary') + '" id="docs-confirm-ok">' + okLabel + '</button></div>';
    document.getElementById('docs-confirm-ok').addEventListener('click', function() { closeModal(); fn(); });
  }

  function pickEventSelect(current) {
    var opts = (state.events || []).map(function(e) {
      return '<option value="' + esc(e.event_id) + '"' + (current === e.event_id ? ' selected' : '') + '>' + esc(e.title) + '</option>';
    }).join('');
    return '<select id="docs-in-ev">' + (current ? '<option value="">— Not linked —</option>' : '<option value="">No event (optional)</option>') + opts + '</select>';
  }

  window.DocumentsControlCenter = {
    q: '',

    reload: function() { refreshAll(); },

    search: function() { loadList(); },

    searchInput: function(v) { state.q = v; loadList(); },

    setFilter: function(name, value) {
      state[name] = value;
      state.activeId = '';
      loadList();
    },

    toggleMode: function(mode) { state.mode = mode; shell(); },

    preview: function() {
      var d = active();
      get('file', { id: d.id, kind: 'preview' }).then(function(r) {
        iframePreview('data:' + (r.mime || 'text/html') + ';base64,' + r.contents, r.name);
      }).catch(function(e) { toast(e.message, true); });
    },

    download: function() {
      var d = active();
      get('file', { id: d.id, kind: 'download' }).then(function(r) {
        var a = document.createElement('a');
        a.href = 'data:' + (r.mime || 'application/octet-stream') + ';base64,' + r.contents;
        a.download = r.name;
        document.body.appendChild(a);
        a.click();
        a.remove();
      }).catch(function(e) { toast(e.message, true); });
    },

    lock: function(on) {
      var d = active();
      post({ action: 'lock', document_id: d.id, lock: on ? '1' : '0' })
        .then(function(r) { state.detail = r; refreshAfter(on ? 'Document locked.' : 'Document unlocked.'); })
        .catch(function(e) { toast(e.message, true); });
    },

    archive: function() {
      confirmAct('Archive document', 'The document will move to the archive. It can be brought back at any time.', false, 'Archive', function() {
        post({ action: 'archive', document_id: active().id })
          .then(function(r) { state.detail = r; refreshAfter('Document archived.'); })
          .catch(function(e) { toast(e.message, true); });
      });
    },

    unarchive: function() {
      post({ action: 'unarchive', document_id: active().id })
        .then(function() { refreshAfter('Document restored from archive.'); })
        .catch(function(e) { toast(e.message, true); });
    },

    del: function() {
      var d = active();
      confirmAct('Delete document', d.legal_hold ? 'This document is under a legal hold — the server will refuse the deletion.' : 'This permanently deletes the document and all its versions.', true, 'Delete permanently', function() {
        post({ action: 'delete', document_id: d.id })
          .then(function() { state.activeId = ''; state.detail = null; toast('Document deleted.'); refreshAll(); })
          .catch(function(e) { toast(e.message, true); });
      });
    },

    restore: function(v) {
      confirmAct('Restore version v' + v, 'A new version with the contents of v' + v + ' will be created on top of the current version.', false, 'Restore', function() {
        post({ action: 'version_restore', document_id: active().id, version: v })
          .then(function() { refreshAfter('Version v' + v + ' restored.'); })
          .catch(function(e) { toast(e.message, true); });
      });
    },

    unshare: function(name) {
      post({ action: 'unshare', document_id: active().id, sharee_name: name })
        .then(function() { refreshAfter('Share removed.'); })
        .catch(function(e) { toast(e.message, true); });
    },

    renameModal: function() {
      var d = active();
      var ov = overlay();
      ov.innerHTML =
        '<div class="docs-modal-head"><div><div class="docs-modal-title">Rename document</div>' +
        '<div class="docs-modal-sub">Names are capped at 220 characters.</div></div></div>' +
        '<div class="docs-modal-body"><label class="docs-lbl">Name</label>' +
        '<input class="docs-in" id="docs-in-name" maxlength="220" value="' + esc(d.name).replace(/"/g, '&quot;') + '"></div>' +
        '<div class="docs-modal-ft"><button class="docs-btn docs-btn-ghost" onclick="closeModal()">Cancel</button>' +
        '<button class="docs-btn docs-btn-primary" id="docs-ok1">Save</button></div>';
      document.getElementById('docs-ok1').addEventListener('click', function() {
        var name = document.getElementById('docs-in-name').value.trim();
        if (!name) { toast('A name is required.', true); return; }
        post({ action: 'rename', document_id: d.id, name: name })
          .then(function(r) { state.detail = r; closeModal(); refreshAfter('Renamed.'); })
          .catch(function(e) { toast(e.message, true); });
      });
    },

    moveModal: function() {
      var d = active();
      var ov = overlay();
      ov.innerHTML =
        '<div class="docs-modal-head"><div><div class="docs-modal-title">Move document</div>' +
        '<div class="docs-modal-sub">Pick a new category and link it to an event folder.</div></div></div>' +
        '<div class="docs-modal-body"><label class="docs-lbl">Category</label><select class="docs-in" id="docs-in-cat">' +
        (state.enums.categories || []).map(function(ca) { return '<option value="' + ca + '"' + (ca === d.category ? ' selected' : '') + '>' + ca[0] + ca.slice(1).toLowerCase() + '</option>'; }).join('') + '</select>' +
        '<label class="docs-lbl">Event</label>' + pickEventSelect(d.event_id) + '</div>' +
        '<div class="docs-modal-ft"><button class="docs-btn docs-btn-ghost" onclick="closeModal()">Cancel</button>' +
        '<button class="docs-btn docs-btn-primary" id="docs-ok2">Move</button></div>';
      document.getElementById('docs-ok2').addEventListener('click', function() {
        var ev = document.getElementById('docs-in-ev').value;
        post({ action: 'move', document_id: d.id, category: document.getElementById('docs-in-cat').value, event_id: ev })
          .then(function(r) { state.detail = r; closeModal(); refreshAfter('Document moved.'); })
          .catch(function(e) { toast(e.message, true); });
      });
    },

    tagsModal: function() {
      var d = active();
      var ov = overlay();
      ov.innerHTML =
        '<div class="docs-modal-head"><div><div class="docs-modal-title">Tags</div>' +
        '<div class="docs-modal-sub">Comma-separated, max 20 tags of 60 characters each.</div></div></div>' +
        '<div class="docs-modal-body"><label class="docs-lbl">Tags</label>' +
        '<input class="docs-in" id="docs-in-tags" value="' + esc((d.tags || []).join(', ')).replace(/"/g, '&quot;') + '"></div>' +
        '<div class="docs-modal-ft"><button class="docs-btn docs-btn-ghost" onclick="closeModal()">Cancel</button>' +
        '<button class="docs-btn docs-btn-primary" id="docs-ok3">Save tags</button></div>';
      document.getElementById('docs-ok3').addEventListener('click', function() {
        var raw = document.getElementById('docs-in-tags').value;
        var tags = raw.split(',').map(function(t) { return t.trim(); }).filter(Boolean);
        post({ action: 'tags', document_id: d.id, tags: JSON.stringify(tags) })
          .then(function(r) { state.detail = r; closeModal(); refreshAfter('Tags saved.'); })
          .catch(function(e) { toast(e.message, true); });
      });
    },

    shareModal: function() {
      var d = active();
      var ov = overlay();
      ov.innerHTML =
        '<div class="docs-modal-head"><div><div class="docs-modal-title">Share document</div>' +
        '<div class="docs-modal-sub">Shares are scoped to this business and recorded in the audit trail.</div></div></div>' +
        '<div class="docs-modal-body"><label class="docs-lbl">Share with (name or role)</label>' +
        '<input class="docs-in" id="docs-in-sharee" maxlength="120" placeholder="e.g. Finance Manager">' +
        '<label class="docs-lbl">Permission</label><select class="docs-in" id="docs-in-perm">' +
        (state.enums.share_perms || ['VIEW', 'COMMENT', 'EDIT']).map(function(p) { return '<option value="' + p + '">' + p + '</option>'; }).join('') + '</select></div>' +
        '<div class="docs-modal-ft"><button class="docs-btn docs-btn-ghost" onclick="closeModal()">Cancel</button>' +
        '<button class="docs-btn docs-btn-primary" id="docs-ok4">Share</button></div>';
      document.getElementById('docs-ok4').addEventListener('click', function() {
        var name = document.getElementById('docs-in-sharee').value.trim();
        if (!name) { toast('A sharee name is required.', true); return; }
        post({ action: 'share', document_id: d.id, sharee_name: name, permission: document.getElementById('docs-in-perm').value })
          .then(function(r) { state.detail = r; closeModal(); refreshAfter('Shared with ' + name + '.'); })
          .catch(function(e) { toast(e.message, true); });
      });
    },

    versionModal: function() {
      var d = active();
      var ov = overlay();
      ov.innerHTML =
        '<div class="docs-modal-head"><div><div class="docs-modal-title">Upload new version</div>' +
        '<div class="docs-modal-sub">v' + num(d.version) + ' is saved; this will create v' + (num(d.version) + 1) + '.</div></div></div>' +
        '<div class="docs-modal-body"><label class="docs-lbl">File</label>' +
        '<input class="docs-in" type="file" id="docs-in-verfile">' +
        '<label class="docs-lbl">Note</label><input class="docs-in" id="docs-in-vernote" maxlength="220" placeholder="What changed in this version?"></div>' +
        '<div class="docs-modal-ft"><button class="docs-btn docs-btn-ghost" onclick="closeModal()">Cancel</button>' +
        '<button class="docs-btn docs-btn-primary" id="docs-ok5">Upload version</button></div>';
      document.getElementById('docs-ok5').addEventListener('click', function() {
        var f = document.getElementById('docs-in-verfile').files[0];
        if (!f) { toast('Choose a file first.', true); return; }
        readAsB64(f, function(b64) {
          if (!b64) { toast('Could not read file.', true); return; }
          post({ action: 'version_upload', document_id: d.id, note: document.getElementById('docs-in-vernote').value.trim(), content_base64: b64 })
            .then(function(r) { state.detail = r; closeModal(); refreshAfter('Version ' + (num(d.version) + 1) + ' uploaded.'); })
            .catch(function(e) { toast(e.message, true); });
        });
      });
    },

    uploadModal: function() {
      var ov = overlay();
      ov.innerHTML =
        '<div class="docs-modal-head"><div><div class="docs-modal-title">Upload documents</div>' +
        '<div class="docs-modal-sub">PDF, DOCX, XLSX, images, CSV… up to 20 MB per file.</div></div></div>' +
        '<div class="docs-modal-body"><input class="docs-in" type="file" id="docs-in-files" multiple>' +
        '<label class="docs-lbl">Category</label><select class="docs-in" id="docs-in-ucat">' +
        (state.enums.categories || []).map(function(ca) { return '<option value="' + ca + '">' + ca[0] + ca.slice(1).toLowerCase() + '</option>'; }).join('') + '</select>' +
        '<label class="docs-lbl">Link to event (optional)</label>' + pickEventSelect('') +
        '<label class="docs-lbl">Status</label><select class="docs-in" id="docs-in-ustat">' +
        ['FINAL', 'DRAFT', 'PENDING_REVIEW', 'APPROVED'].map(function(s) { return '<option value="' + s + '"' + (s === 'FINAL' ? ' selected' : '') + '>' + s.replace(/_/g, ' ') + '</option>'; }).join('') + '</select></div>' +
        '<div class="docs-modal-ft"><button class="docs-btn docs-btn-ghost" onclick="closeModal()">Cancel</button>' +
        '<button class="docs-btn docs-btn-primary" id="docs-ok6">Upload</button></div>';
      document.getElementById('docs-ok6').addEventListener('click', function() {
        var files = document.getElementById('docs-in-files').files;
        if (!files.length) { toast('Choose at least one file.', true); return; }
        var ev = document.getElementById('docs-in-ev').value;
        var cat = document.getElementById('docs-in-ucat').value;
        var st = document.getElementById('docs-in-ustat').value;
        var done = 0;
        Array.prototype.forEach.call(files, function(f) {
          readAsB64(f, function(b64) {
            if (!b64) { toast('Could not read ' + f.name + '.', true); return; }
            post({ action: 'upload', name: f.name, content_base64: b64, category: cat, event_id: ev, status: st })
              .then(function() { done++; if (done === files.length) { closeModal(); refreshAfter('Uploaded ' + files.length + ' document(s).'); } })
              .catch(function(e) { toast(e.message, true); });
          });
        });
      });
    },

    newDocModal: function() {
      var en = state.enums || {};
      var tpls = state.templates || [];
      var varHelp = '<span style="color:#0f6fd8;font-family:monospace;font-size:11px">' +
        (en.template_vars || []).map(function(v) { return '{{' + v + '}}'; }).join(' ') + '</span>';
      var tplOpts = tpls.length
        ? '<option value="">Blank page (no template)</option>' + tpls.map(function(t) { return '<option value="' + t.id + '">' + esc(t.title) + ' (' + t.doc_type + ')</option>'; }).join('')
        : '<option value="">Blank page — no templates saved yet</option>';
      var ov = overlay();
      ov.innerHTML =
        '<div class="docs-modal-head"><div><div class="docs-modal-title">New document</div>' +
        '<div class="docs-modal-sub">A blank document or a saved template. Templates expand variables with live event facts.</div></div></div>' +
        '<div class="docs-modal-body"><label class="docs-lbl">Name</label><input class="docs-in" id="docs-in-nname" maxlength="220" placeholder="e.g. Event run sheet">' +
        '<label class="docs-lbl">Template</label><select class="docs-in" id="docs-in-ntpl">' + tplOpts + '</select>' +
        '<label class="docs-lbl">Category</label><select class="docs-in" id="docs-in-ncat">' +
        (en.categories || []).map(function(ca) { return '<option value="' + ca + '">' + ca[0] + ca.slice(1).toLowerCase() + '</option>'; }).join('') + '</select>' +
        '<label class="docs-lbl">Event (optional)</label>' + pickEventSelect('') +
        '<label class="docs-lbl">Status</label><select class="docs-in" id="docs-in-nstat">' +
        ['DRAFT', 'PENDING_REVIEW', 'APPROVED'].map(function(s) { return '<option value="' + s + '"' + (s === 'DRAFT' ? ' selected' : '') + '>' + s.replace(/_/g, ' ') + '</option>'; }).join('') + '</select>' +
        '<div class="docs-hint">Available variables: ' + varHelp + '</div></div>' +
        '<div class="docs-modal-ft"><button class="docs-btn docs-btn-ghost" onclick="closeModal()">Cancel</button>' +
        '<button class="docs-btn docs-btn-primary" id="docs-ok7">Create</button></div>';
      document.getElementById('docs-ok7').addEventListener('click', function() {
        var name = document.getElementById('docs-in-nname').value.trim();
        if (!name) { toast('A name is required.', true); return; }
        post({
          action: 'create', name: name,
          template_id: document.getElementById('docs-in-ntpl').value,
          category: document.getElementById('docs-in-ncat').value,
          event_id: document.getElementById('docs-in-ev').value,
          status: document.getElementById('docs-in-nstat').value
        }).then(function(r) { state.activeId = r.document.id; closeModal(); refreshAfter('Document created.'); })
          .catch(function(e) { toast(e.message, true); });
      });
    },

    generateModal: function() {
      var genCards = '';
      (state.enums.gen_types || []).forEach(function(g) {
        var label = g.replace(/_/g, ' ');
        genCards += '<button class="docs-gen" data-gg="' + g + '">' + ic('zap', 16) + '<span>' + label[0].toUpperCase() + label.slice(1) + '</span></button>';
      });
      var ov = overlay();
      ov.innerHTML =
        '<div class="docs-modal-head"><div><div class="docs-modal-title">Generate a report</div>' +
        '<div class="docs-modal-sub">Rendered live from your operating data and stored as a document.</div></div></div>' +
        '<div class="docs-modal-body"><label class="docs-lbl">Report</label><select class="docs-in" id="docs-in-gtype">' +
        (state.enums.gen_types || []).map(function(g) { return '<option value="' + g + '">' + g.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); }) + '</option>'; }).join('') + '</select>' +
        '<label class="docs-lbl">Event</label>' + pickEventSelect('') +
        '<label class="docs-lbl">Format</label><select class="docs-in" id="docs-in-gfmt">' +
        (state.enums.gen_formats || ['pdf', 'csv', 'html']).map(function(f) { return '<option value="' + f + '">' + f.toUpperCase() + '</option>'; }).join('') + '</select>' +
        '<div class="docs-hint">PDF is delivered as a print-ready document (no external renderer needed).</div></div>' +
        '<div class="docs-modal-ft"><button class="docs-btn docs-btn-ghost" onclick="closeModal()">Cancel</button>' +
        '<button class="docs-btn docs-btn-primary" id="docs-ok8">Generate</button></div>';
      document.getElementById('docs-ok8').addEventListener('click', function() {
        var ev = document.getElementById('docs-in-ev').value;
        var g = document.getElementById('docs-in-gtype').value;
        var needsEvent = ['attendance_report', 'ticket_sales_report', 'event_summary'].indexOf(g) !== -1;
        if (needsEvent && !ev) { toast('This report needs an event.', true); return; }
        post({ action: 'generate', gen_type: g, event_id: ev, format: document.getElementById('docs-in-gfmt').value })
          .then(function(r) { state.activeId = r.document.id; closeModal(); refreshAfter('Report generated and saved.'); })
          .catch(function(e) { toast(e.message, true); });
      });
    }
  };

  /* ── Generate tab ──────────────────────────────────────────────── */

  function generateTab() {
    var evOpts = (state.events || []).map(function(e) { return '<option value="' + esc(e.event_id) + '">' + esc(e.title) + '</option>'; }).join('');
    var cats = ['attendance_report', 'financial_report', 'ticket_sales_report', 'customer_report', 'review_report', 'event_summary'];
    var descs = {
      attendance_report: 'Check-ins and ticket status from the gate data.',
      financial_report: 'Bookings settlement: revenue, commissions, discounts and tax, plus finance documents on record.',
      ticket_sales_report: 'Ticket types, volumes and revenue for an event.',
      customer_report: 'Top customers by orders, tickets and spend.',
      review_report: 'Ratings distribution and recent feedback.',
      event_summary: 'The full picture — revenue, tickets, attendance and reviews for one event.'
    };
    var cards = cats.map(function(g) {
      var needs = ['attendance_report', 'ticket_sales_report', 'event_summary'].indexOf(g) !== -1 ? '' : ' style="opacity:1"';
      return '<div class="docs-gen-tab" data-gg="' + g + '">' +
        '<div class="docs-gen-tab-ic">' + ic(g === 'financial_report' ? 'card' : g === 'review_report' ? 'star' : g === 'customer_report' ? 'users' : g === 'event_summary' ? 'layers' : 'zap', 18) + '</div>' +
        '<div class="docs-gen-tab-t">' + g.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); }) + '</div>' +
        '<div class="docs-gen-tab-s">' + esc(descs[g] || '') + '</div>' +
        '<div class="docs-gen-tab-f">' +
        (needs ? '<select class="docs-in docs-gen-ev" aria-label="Event">' + evOpts + '</select>' : '<span class="docs-chip-st" style="background:#0f6fd822;color:#0f6fd8">All events</span>') +
        '<select class="docs-in docs-gen-fmt" aria-label="Format"><option value="pdf">PDF</option><option value="csv">CSV</option><option value="html">HTML</option></select>' +
        '<button class="docs-btn docs-btn-primary docs-gen-go">' + ic('zap', 13) + ' Generate</button>' +
        '</div></div>';
    }).join('');
    return '<div class="docs-gen-wrap"><div class="docs-gen-h">' +
      '<div class="docs-sec-h">Live reports</div>' +
      '<div class="docs-empty-s">Each report queries the operating tables at generation time — nothing is duplicated, everything here is truth.</div></div>' +
      '<div class="docs-gen-grid">' + cards + '</div></div>';
  }

  function bindGenerate() {
    document.querySelectorAll('#docs-content .docs-gen-go').forEach(function(b) {
      b.addEventListener('click', function() {
        var card = b.closest('.docs-gen-tab');
        var g = card.getAttribute('data-gg');
        var ev = (card.querySelector('.docs-gen-ev') || {}).value || '';
        var fmt = card.querySelector('.docs-gen-fmt').value;
        var needs = ['attendance_report', 'ticket_sales_report', 'event_summary'].indexOf(g) !== -1;
        if (needs && !ev) { toast('This report needs an event.', true); return; }
        b.disabled = true;
        post({ action: 'generate', gen_type: g, event_id: ev, format: fmt })
          .then(function(r) {
            state.activeId = r.document.id;
            toast('Report generated and saved to the library.');
            state.tab = 'library';
            shell();
          })
          .catch(function(e) { toast(e.message, true); })
          .then(function() { b.disabled = false; });
      });
    });
  }

  /* ── Templates tab ────────────────────────────────────────────── */

  function templatesTab() {
    var tpls = state.templates || [];
    if (!tpls.length) {
      return '<div class="docs-empty">' + ic('doc', 40) + '<div class="docs-empty-t">No templates yet</div>' +
        '<div class="docs-empty-s">Templates answer with live facts — {{event_name}}, {{revenue}}, {{attendance}} and more.</div>' +
        '<button class="docs-btn docs-btn-primary" style="margin-top:10px" onclick="DocumentsControlCenter.templateModal(null)">' + ic('plus', 14) + ' New template</button></div>';
    }
    return '<div class="docs-tpl-top"><div class="docs-sec-h">Templates</div>' +
      '<button class="docs-btn docs-btn-primary" onclick="DocumentsControlCenter.templateModal(null)">' + ic('plus', 14) + ' New template</button></div>' +
      '<div class="docs-tpl-grid">' + tpls.map(function(t) {
        var v = (t.variables || []).map(function(x) { return '{{' + x + '}}'; }).join(' ');
        return '<div class="docs-tpl" data-id="' + esc(t.id) + '">' +
          '<div class="docs-tpl-t">' + esc(t.title) + '<span class="docs-chip-st" style="background:#0f6fd822;color:#0f6fd8">' + esc(t.doc_type) + '</span></div>' +
          '<div class="docs-tpl-s">' + esc(t.description || '') + '</div>' +
          '<div class="docs-tpl-meta">' + esc(t.category) + ' · used ' + num(t.usage_count) + (t.is_active ? '' : ' · <span style="color:#e5484d">inactive</span>') + '</div>' +
          '<div class="docs-tpl-actions">' +
          '<button class="docs-btn docs-btn-ghost docs-mini" onclick="DocumentsControlCenter.templateModal(\'' + esc(t.id) + '\')">' + ic('edit', 12) + ' Edit</button>' +
          '<button class="docs-btn docs-btn-danger docs-mini" onclick="DocumentsControlCenter.templateDelete(\'' + esc(t.id) + '\')">' + ic('trash', 12) + ' Delete</button></div></div>';
      }).join('') + '</div>';
  }

  function bindTemplatesTab() {}

  function templateModal(id) {
    var tpl = id ? (state.templates || []).filter(function(t) { return t.id === id; })[0] : null;
    var en = state.enums || {};
    var ov = overlay();
    var varsBlock = '<div class="docs-hint">Insert variables anywhere — e.g. {{event_name}} will expand to the event title. Allowed: ' +
      (en.template_vars || []).map(function(v) { return '{{' + v + '}}'; }).join(' ') + '</div>';
    ov.innerHTML =
      '<div class="docs-modal-head"><div><div class="docs-modal-title">' + (tpl ? 'Edit template' : 'New template') + '</div>' +
      '<div class="docs-modal-sub">Templates become documents with live data plugged in at creation.</div></div></div>' +
      '<div class="docs-modal-body"><label class="docs-lbl">Title</label><input class="docs-in" id="docs-tpl-title" maxlength="140" value="' + esc(tpl ? tpl.title : '').replace(/"/g, '&quot;') + '">' +
      '<label class="docs-lbl">Description</label><input class="docs-in" id="docs-tpl-desc" maxlength="240" value="' + esc(tpl ? tpl.description : '').replace(/"/g, '&quot;') + '">' +
      '<label class="docs-lbl">Category</label><select class="docs-in" id="docs-tpl-cat">' +
      (en.categories || []).map(function(ca) { return '<option value="' + ca + '"' + (tpl && tpl.category === ca ? ' selected' : '') + '>' + ca[0] + ca.slice(1).toLowerCase() + '</option>'; }).join('') + '</select>' +
      '<label class="docs-lbl">Document type</label><select class="docs-in" id="docs-tpl-type">' +
      ['PDF', 'DOC', 'TXT', 'HTML', 'CSV'].map(function(dt) { return '<option value="' + dt + '"' + (tpl && tpl.doc_type === dt ? ' selected' : '') + '>' + dt + '</option>'; }).join('') + '</select>' +
      '<label class="docs-lbl">Body</label><textarea class="docs-in docs-tarea" id="docs-tpl-body" rows="10">' + esc(tpl ? tpl.body : '') + '</textarea>' + varsBlock + '</div>' +
      '<div class="docs-modal-ft"><button class="docs-btn docs-btn-ghost" onclick="closeModal()">Cancel</button>' +
      '<button class="docs-btn docs-btn-primary" id="docs-ok9">Save template</button></div>';
    document.getElementById('docs-ok9').addEventListener('click', function() {
      post({
        action: 'template_save',
        template_id: id || '',
        title: document.getElementById('docs-tpl-title').value.trim(),
        description: document.getElementById('docs-tpl-desc').value.trim(),
        category: document.getElementById('docs-tpl-cat').value,
        doc_type: document.getElementById('docs-tpl-type').value,
        body: document.getElementById('docs-tpl-body').value
      }).then(function() { closeModal(); toast('Template saved.'); loadTemplates(); })
        .catch(function(e) { toast(e.message, true); });
    });
  }

  window.DocumentsControlCenter.templateModal = templateModal;
  window.DocumentsControlCenter.templateDelete = function(id) {
    confirmAct('Delete template', 'Templates already used are kept inside the documents they created.', true, 'Delete', function() {
      post({ action: 'template_delete', template_id: id })
        .then(function() { toast('Template deleted.'); loadTemplates(); })
        .catch(function(e) { toast(e.message, true); });
    });
  };

  /* ── Activity tab ──────────────────────────────────────────────── */

  function activityTab() {
    var act = state.activity || [];
    if (!act.length) return '<div class="docs-empty">' + ic('history', 40) + '<div class="docs-empty-t">No activity yet</div></div>';
    return '<div class="docs-sec-h">Activity trail</div><div class="docs-actfeed">' +
      act.map(function(a) {
        return '<div class="docs-actfeed-row"><div class="docs-actfeed-ic">' +
          ic(a.action === 'deleted' ? 'trash' : a.action === 'shared' ? 'share' : a.action === 'locked' ? 'lock' : a.action === 'version_created' || a.action === 'version_restored' ? 'history' : a.action === 'generated' ? 'zap' : 'doc', 14) + '</div>' +
          '<div class="docs-actfeed-b"><div class="docs-actfeed-t"><b>' + esc(a.actor) + '</b> ' + esc(String(a.action || '').replace(/_/g, ' ')) +
          ' — <a href="#" data-go="' + esc(a.document_id) + '">' + esc(a.document_name) + '</a></div>' +
          (a.details && Object.keys(a.details).length ? '<div class="docs-actfeed-d">' + esc(JSON.stringify(a.details)) + '</div>' : '') +
          '<div class="docs-actfeed-w">' + date(a.at, true) + '</div></div></div>';
      }).join('') + '</div>';
  }

  function bindActivity() {
    document.querySelectorAll('#docs-content a[data-go]').forEach(function(a) {
      a.addEventListener('click', function(e) {
        e.preventDefault();
        state.activeId = a.getAttribute('data-go');
        state.tab = 'library';
        shell();
        loadDetail();
      });
    });
  }

  /* ── Boot ──────────────────────────────────────────────────────── */

  function refreshAll() {
    var box = document.getElementById('docs-content');
    return Promise.all([
      get('overview').then(function(r) { state.overview = r; }),
      get('documents', {
        view: state.view, q: state.q, category: state.category, event_id: state.eventId,
        doc_type: state.docType, status: state.status, creator: state.creator, sort: state.sort, limit: 120
      }).then(function(r) { state.docs = r; }),
      get('filters').then(function(r) { state.filters = r; }),
      state.activeId ? get('detail', { id: state.activeId }).then(function(r) { state.detail = r; }) : Promise.resolve(null)
    ]).then(function() { shell(); }).catch(function(e) { toast(e.message, true); });
  }

  function boot() {
    var baseGet = {
      enums: get('enums').then(function(r) { state.enums = r; }),
      events: get('events').then(function(r) { state.events = r; }),
      overview: get('overview').then(function(r) { state.overview = r; }),
      templates: get('templates', { active: 0 }).then(function(r) { state.templates = r; }),
      activity: get('activity', { limit: 30 }).then(function(r) { state.activity = r; }),
      filters: get('filters').then(function(r) { state.filters = r; })
    };
    Promise.all(Object.keys(baseGet).map(function(k) { return baseGet[k]; }))
      .then(function() {
        state.docs = state.docs || { items: [] };
        return get('documents', { view: state.view, sort: state.sort, limit: 120 });
      })
      .then(function(r) { state.docs = r; state.booted = true; shell(); })
      .catch(function(e) { toast(e.message, true); });
  }

  function loadTemplates() {
    return get('templates', { active: 0 }).then(function(r) { state.templates = r; renderTab(); })
      .catch(function(e) { toast(e.message, true); });
  }

  boot();
  return window.DocumentsControlCenter;
})();