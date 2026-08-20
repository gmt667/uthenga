/* Uthenga — Messages Console (Events V2).
 * The organizer's communication workspace: a private two-way inbox for
 * customer conversations, broadcast/announcement campaigns, message templates
 * and trigger-based automations. Customer context (tickets, payments, check-in,
 * spend) is derived live from the operational tables — the console never
 * invents facts. Every action hits the messages API with CSRF + rate limits.
 */
window.MessagesControlCenter = (function() {
  'use strict';

  var evDoc = document.getElementById('events-workspace');
  if (!evDoc) return {};
  var base = evDoc.dataset.baseUrl ? evDoc.dataset.baseUrl : '';
  var csrf = evDoc.dataset.csrf ? evDoc.dataset.csrf : '';
  var api = base + 'api/tie/vendor/events/messages.php';

  var state = {
    tab: 'inbox',
    view: 'all',
    q: '',
    eventId: '',
    tag: '',
    events: [],
    inbox: null,
    activeId: '',
    thread: null,
    broadcasts: null,
    templates: null,
    automations: null,
    booted: false,
    loading: false,
    openedOnce: false
  };

  var ROOT = null;
  var BODY = null;

  /* ── Helpers ────────────────────────────────────────────────────── */

  function esc(s) { return window.tkEsc ? tkEsc(s) : String(s == null ? '' : s); }
  function money(n) { return window.tkMoney ? tkMoney(n) : ('MK ' + (Number(n) || 0).toLocaleString()); }
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
      chat: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
      send: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
      user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
      ticket: '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/><line x1="13" y1="5" x2="13" y2="7"/><line x1="13" y1="11" x2="13" y2="13"/><line x1="13" y1="17" x2="13" y2="19"/>',
      card: '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
      venue: '<path d="M3 21h18M6 17V8m4 9V8m4 9V8m4 9V4l-8-3-8 3v13"/>',
      bell: '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
      megaphone: '<path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
      doc: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
      bolt: '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
      pin: '<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1z"/>',
      note: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
      search: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
      star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
      shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
      check: '<polyline points="20 6 9 17 4 12"/>',
      x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
      back: '<polyline points="15 18 9 12 15 6"/>',
      clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
      tag: '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
      mail: '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/>',
      phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
      sparkle: '<path d="M12 3l1.9 5.7L20 10l-5.7 1.9L12 18l-1.9-6.1L4 10l6.1-1.3z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>'
    }[name] || '';
    var s = size || 14;
    return '<svg viewBox="0 0 24 24" width="' + s + '" height="' + s + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-0.15em;flex:none;">' + p + '</svg>';
  }
  function shortTime(sqlDt) {
    if (!sqlDt) return '';
    var d = new Date(String(sqlDt).replace(' ', 'T') + (String(sqlDt).length === 19 ? '' : ''));
    if (isNaN(d.getTime())) return String(sqlDt);
    var now = new Date();
    var sameDay = d.toDateString() === now.toDateString();
    var hh = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    return sameDay ? hh : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) + ' ' + hh;
  }
  function initials(name) {
    return esc(String(name || '?').split(/\s+/).map(function(w) { return w[0] || ''; }).join('').slice(0, 2).toUpperCase());
  }
  function prioCls(p) { return String(p || '').toLowerCase(); }
  function statCls(s) { return String(s || '').toLowerCase().replace(/_/g, ''); }

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
        return j.messages_result !== undefined ? j.messages_result : j;
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
      return j.messages_result !== undefined ? j.messages_result : j;
    });
  }

  /* ── Shell ─────────────────────────────────────────────────────── */

  function shell() {
    ROOT.innerHTML =
      '<div class="msgs-wrap">' +
        '<div class="msgs-topbar">' +
          '<div class="msgs-topbar-left">' + ic('chat', 18) +
            '<div><div class="msgs-topbar-title">Messages</div>' +
            '<div class="msgs-topbar-sub" id="msgs-sub">Business communication workspace</div></div>' +
          '</div>' +
          '<div class="msgs-tabs" id="msgs-tabs">' +
            '<button type="button" class="msgs-tab active" data-tab="inbox" onclick="MessagesControlCenter.setTab(\'inbox\')">Inbox</button>' +
            '<button type="button" class="msgs-tab" data-tab="broadcasts" onclick="MessagesControlCenter.setTab(\'broadcasts\')">Broadcasts</button>' +
            '<button type="button" class="msgs-tab" data-tab="templates" onclick="MessagesControlCenter.setTab(\'templates\')">Templates</button>' +
            '<button type="button" class="msgs-tab" data-tab="automations" onclick="MessagesControlCenter.setTab(\'automations\')">Automations</button>' +
          '</div>' +
          '<div class="msgs-topbar-actions">' +
            '<button type="button" class="msgs-btn msgs-btn-primary" onclick="MessagesControlCenter.openNew()">' + ic('user', 14) + '<span>New conversation</span></button>' +
          '</div>' +
        '</div>' +
        '<div id="msgs-body"></div>' +
      '</div>';
    BODY = document.getElementById('msgs-body');
    document.querySelectorAll('#msgs-tabs .msgs-tab').forEach(function(t) {
      t.addEventListener('click', function() { setTab(t.getAttribute('data-tab')); });
    });
  }

  function setTab(tab) {
    state.tab = tab;
    document.querySelectorAll('#msgs-tabs .msgs-tab').forEach(function(t) { t.classList.toggle('active', t.getAttribute('data-tab') === tab); });
    render();
  }

  function render() {
    if (!ROOT) return;
    if (state.tab === 'broadcasts') return renderBroadcasts();
    if (state.tab === 'templates') return renderTemplates();
    if (state.tab === 'automations') return renderAutomations();
    renderInbox();
  }

  /* ── Inbox ─────────────────────────────────────────────────────── */

  function renderInbox() {
    if (!state.inbox) {
      BODY.innerHTML = '<div class="msgs-loading">' + ic('chat', 22) + ' Loading your inbox…</div>';
      return refreshInbox();
    }
    var counts = state.inbox.counts || {};
    var filters = state.inbox.filters || {};
    var convs = state.inbox.conversations || [];
    var views = [
      { id: 'all', label: 'All', n: counts.active, dot: false },
      { id: 'unread', label: 'Unread', n: counts.unread, dot: counts.unread },
      { id: 'priority', label: 'Priority', n: counts.priority, dot: counts.priority },
      { id: 'assigned', label: 'Assigned', n: counts.assigned, dot: false },
      { id: 'archived', label: 'Archived', n: counts.archived, dot: false }
    ];
    var evOpts = state.events.map(function(e) {
      return '<option value="' + esc(e.event_id) + '"' + (state.eventId === e.event_id ? ' selected' : '') + '>' + esc(e.title) + '</option>';
    }).join('');

    var list = convs.length ? convs.map(function(c) {
      var active = state.activeId && (c.id === state.activeId);
      var unreadBadge = num(c.unread_count) > 0 ? '<span class="msgs-unread">' + c.unread_count + '</span>' : '';
      var prioDot = c.priority && c.priority !== 'NORMAL' ? '<span class="msgs-prio msgs-prio-' + prioCls(c.priority) + '">' + esc(c.priority) + '</span>' : '';
      var tags = (c.tags || []).map(function(t) { return '<span class="msgs-tagchip">' + esc(t) + '</span>'; }).join('');
      var when = shortTime(c.last_message_at || c.updated_at);
      return '<div class="msgs-conv-row' + (active ? ' active' : '') + '" data-id="' + esc(c.id) + '" onclick="MessagesControlCenter.openConversation(\'' + esc(c.id) + '\')">' +
        '<div class="msgs-avatar' + (num(c.unread_count) ? ' unread' : '') + '">' + initials(c.customer_name) + '</div>' +
        '<div class="msgs-conv-main">' +
          '<div class="msgs-conv-top"><span class="msgs-conv-name">' + esc(c.customer_name) + '</span>' + prioDot + unreadBadge + '<span class="msgs-conv-when">' + when + '</span></div>' +
          '<div class="msgs-conv-subject">' + esc(c.subject || 'Conversation') + '</div>' +
          '<div class="msgs-conv-preview">' + esc(c.last_message_preview || '') + '</div>' +
          (c.event_title ? '<div class="msgs-conv-event">' + ic('pin', 11) + esc(c.event_title) + '</div>' : '') +
          (tags || c.assigned_to && c.assigned_to !== 'Unassigned' ? '<div class="msgs-conv-meta">' + tags + (c.assigned_to && c.assigned_to !== 'Unassigned' ? '<span class="msgs-assign-chip">' + esc(c.assigned_to) + '</span>' : '') + '</div>' : '') +
        '</div>' +
      '</div>';
    }).join('') : '<div class="msgs-empty">' + ic('chat', 26) + '<div>No conversations here yet.</div></div>';

    BODY.innerHTML =
      '<div class="msgs-inbox">' +
        '<aside class="msgs-col msgs-col-left">' +
          '<div class="msgs-viewchips">' + views.map(function(v) {
            return '<button type="button" class="msgs-viewchip' + (state.view === v.id ? ' active' : '') + '" data-view="' + v.id + '" onclick="MessagesControlCenter.setView(\'' + v.id + '\')">' +
              esc(v.label) + (v.n ? '<span class="msgs-viewchip-n' + (v.dot ? ' dot' : '') + '">' + v.n + '</span>' : '') + '</button>';
          }).join('') + '</div>' +
          '<div class="msgs-filters">' +
            '<div class="msgs-search">' + ic('search', 13) +
              '<input type="text" id="msgs-q" placeholder="Search conversations, customers, tickets…" value="' + esc(state.q) + '" onkeydown="if(event.key===\'Enter\')MessagesControlCenter.search()">' +
            '</div>' +
            '<select id="msgs-evt" onchange="MessagesControlCenter.setEvent(this.value)"><option value="">All events</option>' + evOpts + '</select>' +
          '</div>' +
          '<div class="msgs-conv-list" id="msgs-conv-list">' + list + '</div>' +
        '</aside>' +
        '<section class="msgs-col msgs-col-mid" id="msgs-thread"></section>' +
        '<aside class="msgs-col msgs-col-right" id="msgs-context"></aside>' +
      '</div>';

    document.getElementById('msgs-sub').textContent = counts.active + ' active · ' + counts.unread + ' unread · ' + counts.priority + ' priority';
    if (state.activeId) openConversation(state.activeId, true);
    else emptyThread();
  }

  function emptyThread() {
    var t = document.getElementById('msgs-thread');
    var c = document.getElementById('msgs-context');
    if (t) t.innerHTML = '<div class="msgs-empty msgs-empty-thread">' + ic('chat', 34) + '<div><b>Select a conversation</b><span style="color:var(--ecc-text-dim);font-size:0.8rem;margin-top:0.3rem;">Conversations, customer context and AI assistance appear here.</span></div></div>';
    if (c) c.innerHTML = '<div class="msgs-empty msgs-empty-context">' + ic('user', 26) + '<div>Customer context appears here.</div></div>';
  }

  function refreshInbox() {
    return get('overview', { view: state.view, q: state.q, event_id: state.eventId, tag: state.tag })
      .then(function(r) {
        state.inbox = r;
        if (!state.events.length) {
          get('events').then(function(ev) { state.events = ev.events || ev || []; refreshInbox(); }).catch(function() {});
          return;
        }
        renderInbox();
      })
      .catch(function(e) { if (BODY) BODY.innerHTML = '<div class="msgs-error">' + esc(e.message) + '</div>'; });
  }

  function openConversation(id, silent) {
    state.activeId = id;
    var t = document.getElementById('msgs-thread');
    var c = document.getElementById('msgs-context');
    if (t) t.innerHTML = '<div class="msgs-loading">' + ic('chat', 22) + ' Opening conversation…</div>';
    return get('conversation', { id: id })
      .then(function(r) {
        if (!r.found) { toast('Conversation not found.', true); return; }
        state.thread = r;
        renderThread();
        renderContext();
        if (!silent && num(r.conversation.unread_count)) post({ action: 'mark_read', conversation_id: id }).catch(function() {});
      })
      .catch(function(e) { if (t) t.innerHTML = '<div class="msgs-error">' + esc(e.message) + '</div>'; });
  }

  function renderThread() {
    var t = document.getElementById('msgs-thread');
    if (!t || !state.thread) return;
    var cv = state.thread.conversation;
    var msgs = state.thread.messages || [];
    var assist = state.thread.assist || {};
    var self = this;

    var bubbles = msgs.map(function(m) {
      var isOut = m.sender_type === 'ORGANIZER' || m.sender_type === 'SYSTEM';
      var card = bodyCard(m.card);
      return '<div class="msgs-bubble ' + (isOut ? 'out' : 'in') + '">' +
        (isOut ? '' : '<div class="msgs-avatar sm">' + initials(m.sender_name) + '</div>') +
        '<div class="msgs-bubble-main">' +
          '<div class="msgs-bubble-head"><span>' + esc(m.sender_name) + '</span><time>' + shortTime(m.created_at) + '</time></div>' +
          (m.body ? '<div class="msgs-bubble-body">' + esc(m.body) + '</div>' : '') +
          card +
        '</div>' +
      '</div>';
    }).join('') || '<div class="msgs-empty thin">No messages yet — start the conversation.</div>';

    var reply = (assist.reply || '').trim();
    var intent = assist.intent || {};
    var assistBox = reply ? '<div class="msgs-assist" id="msgs-assist">' +
      ic('sparkle', 14) +
      '<div class="msgs-assist-text"><span class="msgs-assist-label">AI SUGGESTED REPLY · ' + esc(intent.intent || 'General enquiry') + '</span>' +
      '<span class="msgs-assist-reply">' + esc(reply) + '</span></div>' +
      '<button type="button" class="msgs-btn msgs-btn-soft" onclick="MessagesControlCenter.useSuggestion()">Use</button>' +
      '<button type="button" class="msgs-btn msgs-btn-icon" title="Dismiss" onclick="this.parentElement.remove()">' + ic('x', 13) + '</button>' +
    '</div>' : '';

    var statusOpts = ['OPEN', 'PENDING', 'RESOLVED', 'ARCHIVED'].map(function(s) {
      return '<option value="' + s + '"' + (cv.status === s ? ' selected' : '') + '>' + s.charAt(0) + s.slice(1).toLowerCase() + '</option>';
    }).join('');
    var prioOpts = ['NORMAL', 'PRIORITY', 'URGENT'].map(function(p) {
      return '<option value="' + p + '"' + (cv.priority === p ? ' selected' : '') + '>' + p.charAt(0) + p.slice(1).toLowerCase() + '</option>';
    }).join('');
    var assignOpts = (state.thread.assignment_options || ['Unassigned']).map(function(a) {
      return '<option value="' + esc(a) + '"' + ((cv.assigned_to || 'Unassigned') === a ? ' selected' : '') + '>' + esc(a) + '</option>';
    }).join('');

    var tags = (state.thread.tags || []).map(function(tag) {
      return '<span class="msgs-tagchip x" data-tag="' + esc(tag) + '" title="Remove tag" onclick="MessagesControlCenter.untag(this)">' + esc(tag) + ' ×</span>';
    }).join('');

    var cardBtns = ic('sparkle', 12) + '';
    t.innerHTML =
      '<div class="msgs-thread-head">' +
        '<div class="msgs-thread-id">' +
          '<div class="msgs-avatar">' + initials(cv ? threadCustomerName() : '') + '</div>' +
          '<div><div class="msgs-thread-name">' + esc(threadCustomerName()) + '</div>' +
          '<div class="msgs-thread-subject">' + esc(cv.subject || 'Conversation') + (cv.event_title ? ' · ' + esc(cv.event_title) : '') + '</div></div>' +
        '</div>' +
        '<div class="msgs-thread-ops">' +
          '<label class="msgs-op">Status<select id="msgs-op-status" onchange="MessagesControlCenter.op(\'status\',this.value)">' + statusOpts + '</select></label>' +
          '<label class="msgs-op">Priority<select id="msgs-op-priority" onchange="MessagesControlCenter.op(\'priority\',this.value)">' + prioOpts + '</select></label>' +
          '<label class="msgs-op">Assignee<select id="msgs-op-assign" onchange="MessagesControlCenter.op(\'assign\',this.value)">' + assignOpts + '</select></label>' +
          '<button type="button" class="msgs-btn msgs-btn-icon' + (num(cv.is_muted) ? ' on' : '') + '" title="Mute / unmute" onclick="MessagesControlCenter.op(\'mute\')">' + ic('bell', 15) + '</button>' +
        '</div>' +
      '</div>' +
      '<div class="msgs-thread-meta">' +
        (num(cv.unread_count) ? '<span class="msgs-meta-pill warn">' + cv.unread_count + ' unread</span>' : '') +
        (cv.detected_topic ? '<span class="msgs-meta-pill">Topic: ' + esc(cv.detected_topic) + '</span>' : '') +
        '<span class="msgs-meta-pill">' + esc(cv.channel || 'UTHENGA') + '</span>' +
        '<span class="msgs-meta-pill">' + esc(cv.status) + '</span>' + tags +
      '</div>' +
      (state.thread.customer ? noteChip() : '') +
      '<div class="msgs-thread-scroll" id="msgs-thread-scroll">' + bubbles + '</div>' +
      (assistBox) +
      '<div class="msgs-composer">' +
        '<div class="msgs-composer-cards">' + cardButtonsHtml() + '</div>' +
        '<div class="msgs-composer-row">' +
          '<textarea id="msgs-compose-input" placeholder="Write a reply… (Enter to send, Shift+Enter for a new line)" rows="2"></textarea>' +
          '<button type="button" class="msgs-btn msgs-btn-primary" id="msgs-send" onclick="MessagesControlCenter.sendReply()">' + ic('send', 15) + '<span>Send</span></button>' +
        '</div>' +
        '<div class="msgs-composer-hint">Responses are private to this customer — broadcasts and announcements live in the Broadcasts tab.</div>' +
      '</div>';
    var sc = document.getElementById('msgs-thread-scroll');
    if (sc) sc.scrollTop = sc.scrollHeight;
  }

  function threadCustomerName() {
    var c = state.thread && state.thread.customer;
    return (c && c.name) ? c.name : ((state.thread.conversation && state.thread.conversation.customer_id) || 'Customer');
  }
  function noteChip() {
    var n = (state.thread.notes || []).length;
    return n ? '<div class="msgs-notechip" onclick="document.getElementById(\'msgs-thread-scroll\').scrollTop=99999">' + ic('note', 12) + n + ' internal note' + (n > 1 ? 's' : '') + '</div>' : '';
  }
  function cardButtonsHtml() {
    var ctx = state.thread && state.thread.context ? state.thread.context : {};
    var btns = [];
    if (ctx.ticket) btns.push('<button type="button" class="msgs-cardbtn" onclick="MessagesControlCenter.sendCard(\'ticket\')">' + ic('ticket', 12) + ' Ticket</button>');
    if (ctx.event) btns.push('<button type="button" class="msgs-cardbtn" onclick="MessagesControlCenter.sendCard(\'event\')">' + ic('pin', 12) + ' Event</button>');
    if (ctx.event) btns.push('<button type="button" class="msgs-cardbtn" onclick="MessagesControlCenter.sendCard(\'venue\')">' + ic('venue', 12) + ' Venue</button>');
    if (ctx.ticket) btns.push('<button type="button" class="msgs-cardbtn" onclick="MessagesControlCenter.sendCard(\'payment\')">' + ic('card', 12) + ' Payment</button>');
    return btns.join('');
  }
  function bodyCard(card) {
    if (!card || !card.type) return '';
    var inner = '';
    if (card.type === 'ticket') {
      inner = '<div class="msgs-card-grid"><span>Ticket</span><b>' + esc(card.ticket_type) + '</b></div>' +
        '<div class="msgs-card-grid"><span>Reference</span><b>' + esc(card.ticket_id) + '</b></div>' +
        '<div class="msgs-card-grid"><span>Event</span><b>' + esc(card.event_title) + '</b></div>' +
        '<div class="msgs-card-grid"><span>Status</span><b class="' + statCls(card.status) + '">' + esc(card.status) + (card.checked_in ? ' · checked in' : '') + '</b></div>';
    } else if (card.type === 'event') {
      inner = '<div class="msgs-card-grid"><span>Event</span><b>' + esc(card.title) + '</b></div>' +
        '<div class="msgs-card-grid"><span>Date</span><b>' + date(card.start_date) + (card.start_time ? ' · ' + esc(String(card.start_time).slice(0, 5)) : '') + '</b></div>';
    } else if (card.type === 'venue') {
      inner = '<div class="msgs-card-grid"><span>Venue</span><b>' + esc(card.name) + '</b></div>' +
        '<div class="msgs-card-grid"><span>Location</span><b>' + esc([card.address, card.city].filter(Boolean).join(', ') || '—') + '</b></div>';
    } else if (card.type === 'payment') {
      inner = '<div class="msgs-card-grid"><span>Order</span><b>' + esc(card.booking_code) + '</b></div>' +
        '<div class="msgs-card-grid"><span>Event</span><b>' + esc(card.event_title) + '</b></div>' +
        '<div class="msgs-card-grid"><span>Amount</span><b>' + money(card.amount) + '</b></div>' +
        '<div class="msgs-card-grid"><span>Status</span><b class="' + statCls(card.payment_status) + '">' + esc(card.payment_status) + '</b></div>';
    } else {
      return '';
    }
    return '<div class="msgs-card">' + ic('sparkle', 12) + '<div class="msgs-card-in">' + inner + '</div></div>';
  }
  function renderContext() {
    var c = document.getElementById('msgs-context');
    if (!c || !state.thread) return;
    var cust = state.thread.customer || {};
    var ctx = state.thread.context || {};
    var activity = state.thread.activity || [];
    var notes = state.thread.notes || [];
    var tags = state.thread.tags || [];

    var actRows = activity.map(function(a) {
      var label = a.label || '';
      var amount = a.amount != null ? ' · ' + money(a.amount) : '';
      var iconName = a.kind === 'purchase' ? 'card' : a.kind === 'checkin' ? 'check' : 'chat';
      return '<div class="msgs-act"><span class="msgs-act-icon ' + a.kind + '">' + ic(iconName, 12) + '</span>' +
        '<div class="msgs-act-txt"><b>' + esc(label) + '</b>' + amount + '<span class="msgs-act-time">' + shortTime(a.at) + '</span></div></div>';
    }).join('');

    var noteRows = notes.map(function(n) {
      return '<div class="msgs-note">' +
        '<div class="msgs-note-head">' + ic('note', 11) + '<b>' + esc(n.author_name) + '</b><time>' + shortTime(n.created_at) + '</time></div>' +
        '<div class="msgs-note-body">' + esc(n.body) + '</div></div>';
    }).join('') || '<div class="msgs-note-empty">No internal notes.</div>';

    var tagChips = tags.map(function(t) {
      return '<span class="msgs-tagchip" data-tag="' + esc(t) + '">' + ic('tag', 10) + esc(t) + '</span>';
    }).join('') || '';

    var evBox = ctx.event ? '<div class="msgs-ctxcard">' +
      '<div class="msgs-ctxcard-h">' + ic('pin', 12) + ' Event context</div>' +
      '<div class="msgs-ctxrow"><span>Event</span><b>' + esc(ctx.event.title) + '</b></div>' +
      '<div class="msgs-ctxrow"><span>Starts</span><b>' + date(ctx.event.start_date) + (ctx.event.start_time ? ' · ' + esc(String(ctx.event.start_time).slice(0, 5)) : '') + '</b></div>' +
      (ctx.event.status ? '<div class="msgs-ctxrow"><span>Status</span><b class="' + statCls(ctx.event.status) + '">' + esc(ctx.event.status) + '</b></div>' : '') +
    '</div>' : '';

    var tkBox = ctx.ticket ? '<div class="msgs-ctxcard">' +
      '<div class="msgs-ctxcard-h">' + ic('ticket', 12) + ' Ticket</div>' +
      '<div class="msgs-ctxrow"><span>Type</span><b>' + esc(ctx.ticket.ticket_type) + '</b></div>' +
      '<div class="msgs-ctxrow"><span>Reference</span><b>' + esc(ctx.ticket.ticket_id) + '</b></div>' +
      '<div class="msgs-ctxrow"><span>Status</span><b class="' + statCls(ctx.ticket.status) + '">' + esc(ctx.ticket.status) + (ctx.ticket.checked_in ? ' · ✓ checked in' : '') + '</b></div>' +
      '<div class="msgs-ctxrow"><span>Payment</span><b class="' + statCls(ctx.ticket.payment_status) + '">' + esc(ctx.ticket.payment_status) + '</b></div>' +
      (ctx.ticket.booking_code ? '<div class="msgs-ctxrow"><span>Order</span><b>' + esc(ctx.ticket.booking_code) + '</b></div>' : '') +
      (ctx.ticket.amount != null ? '<div class="msgs-ctxrow"><span>Amount</span><b>' + money(ctx.ticket.amount) + '</b></div>' : '') +
    '</div>' : '';

    c.innerHTML =
      '<div class="msgs-ctx-profile">' +
        '<div class="msgs-avatar lg' + (cust.verified ? ' verified' : '') + '">' + initials(cust.name) + '</div>' +
        '<div class="msgs-ctx-name">' + esc(cust.name || 'Customer') + (cust.verified ? '<span title="Verified account">' + ic('shield', 13) + '</span>' : '') + '</div>' +
        '<div class="msgs-ctx-contact">' + (cust.email ? '<span>' + ic('mail', 11) + esc(cust.email) + '</span>' : '') +
                    (cust.phone ? '<span>' + ic('phone', 11) + esc(cust.phone) + '</span>' : '') +
        '</div>' +
        (cust.since ? '<div class="msgs-ctx-since">Customer since ' + date(cust.since) + '</div>' : '') +
        '<div class="msgs-ctx-stats">' +
          '<div class="msgs-stat"><b>' + num(cust.events_count) + '</b><span>events</span></div>' +
          '<div class="msgs-stat"><b>' + num(cust.orders_count) + '</b><span>orders</span></div>' +
          '<div class="msgs-stat"><b>' + num(cust.tickets_count) + '</b><span>tickets</span></div>' +
          '<div class="msgs-stat"><b>' + money(cust.total_spent) + '</b><span>lifetime spend</span></div>' +
        '</div>' +
      '</div>' +
      evBox + tkBox +
      '<div class="msgs-ctxcard">' +
        '<div class="msgs-ctxcard-h">' + ic('clock', 12) + ' Recent activity</div>' +
        (actRows || '<div class="msgs-note-empty">No recent activity.</div>') +
      '</div>' +
      '<div class="msgs-ctxcard">' +
        '<div class="msgs-ctxcard-h">' + ic('tag', 12) + ' Tags</div>' +
        '<div class="msgs-tags-row">' + tagChips + '</div>' +
        '<div class="msgs-tag-add"><input type="text" id="msgs-tag-input" placeholder="Add tag…" onkeydown="if(event.key===\'Enter\')MessagesControlCenter.addTag(this.value)">' +
        '<button type="button" class="msgs-btn msgs-btn-soft" onclick="MessagesControlCenter.addTag(document.getElementById(\'msgs-tag-input\').value)">+</button></div>' +
      '</div>' +
      '<div class="msgs-ctxcard">' +
        '<div class="msgs-ctxcard-h">' + ic('note', 12) + ' Internal notes</div>' +
        '<div class="msgs-notes-list">' + noteRows + '</div>' +
        '<div class="msgs-note-add"><input type="text" id="msgs-note-input" placeholder="Add a private note…" onkeydown="if(event.key===\'Enter\')MessagesControlCenter.addNote(this.value)">' +
        '<button type="button" class="msgs-btn msgs-btn-soft" onclick="MessagesControlCenter.addNote(document.getElementById(\'msgs-note-input\').value)">Add</button></div>' +
      '</div>';
  }

  /* ── Inbox actions ─────────────────────────────────────────────── */

  function setView(v) { state.view = v; refreshInbox(); }
  function setEvent(id) { state.eventId = id; refreshInbox(); }
  function search() {
    var q = document.getElementById('msgs-q');
    state.q = q ? q.value.trim() : '';
    refreshInbox();
  }
  function op(kind, value) {
    var payload = { action: kind, conversation_id: state.activeId };
    if (value !== undefined) payload[kind === 'status' ? 'status' : kind === 'priority' ? 'priority' : 'assigned_to'] = value;
    post(payload).then(function(r) {
      state.thread = r;
      renderThread(); renderContext(); refreshInbox();
    }).catch(function(e) { toast(e.message, true); });
  }
  function addTag(tag) {
    var v = (tag || '').trim();
    if (!v) return;
    post({ action: 'tag', conversation_id: state.activeId, tag: v })
      .then(function(r) { state.thread = r; renderThread(); renderContext(); })
      .catch(function(e) { toast(e.message, true); });
  }
  function untag(el) {
    var tag = el.getAttribute('data-tag');
    post({ action: 'untag', conversation_id: state.activeId, tag: tag })
      .then(function(r) { state.thread = r; renderThread(); renderContext(); })
      .catch(function(e) { toast(e.message, true); });
  }
  function addNote(body) {
    var v = (body || '').trim();
    if (!v) return;
    post({ action: 'note', conversation_id: state.activeId, body: v })
      .then(function(r) { state.thread = r; renderThread(); renderContext(); })
      .catch(function(e) { toast(e.message, true); });
  }
  function sendReply() {
    var input = document.getElementById('msgs-compose-input');
    if (!input) return;
    var body = input.value.trim();
    if (!body) return;
    post({ action: 'reply', conversation_id: state.activeId, body: body })
      .then(function(r) {
        state.thread = r;
        renderThread(); renderContext(); refreshInbox();
      })
      .catch(function(e) { toast(e.message, true); });
  }
  function sendCard(kind) {
    var ctx = state.thread && state.thread.context ? state.thread.context : {};
    var payload = { action: 'reply', conversation_id: state.activeId };
    if (kind === 'ticket' && ctx.ticket) payload.payload = JSON.stringify({ type: 'ticket', ticket_id: ctx.ticket.ticket_id });
    else if (kind === 'event' && ctx.event) payload.payload = JSON.stringify({ type: 'event', event_id: ctx.event.id });
    else if (kind === 'venue' && ctx.event) payload.payload = JSON.stringify({ type: 'venue', event_id: ctx.event.id });
    else if (kind === 'payment' && ctx.ticket) payload.payload = JSON.stringify({ type: 'payment', booking_id: ctx.ticket.booking_id });
    else return toast('No live context for that card.', true);
    post(payload).then(function(r) {
      state.thread = r;
      renderThread(); renderContext(); refreshInbox();
    }).catch(function(e) { toast(e.message, true); });
  }
  function useSuggestion() {
    var a = state.thread && state.thread.assist ? state.thread.assist.reply : '';
    var input = document.getElementById('msgs-compose-input');
    if (input && a) { input.value = a; input.focus(); }
  }

  /* ── New conversation modal ────────────────────────────────────── */

  var newOpen = false;
  var newSearch = [];
  function openNew() {
    newOpen = true;
    var evOpts = '<option value="">No specific event</option>' + state.events.map(function(e) {
      return '<option value="' + esc(e.event_id) + '">' + esc(e.title) + '</option>';
    }).join('');
    var ov = overlay();
    ov.innerHTML =
      '<div class="msgs-modal-head"><div><div class="msgs-modal-title">Start a conversation</div>' +
      '<div class="msgs-modal-sub">Message a customer privately — tickets, orders and context are attached live.</div></div>' +
      '<button type="button" class="msgs-btn msgs-btn-icon" onclick="MessagesControlCenter.closeNew()">' + ic('x', 15) + '</button></div>' +
      '<div class="msgs-modal-body">' +
        '<label class="msgs-field"><span>Event (optional)</span><select id="msgs-new-evt">' + evOpts + '</select></label>' +
        '<label class="msgs-field"><span>Customer</span><input type="text" id="msgs-new-cust" placeholder="Type a name or email…" oninput="MessagesControlCenter.searchCustomers(this.value)"></label>' +
        '<div class="msgs-new-results" id="msgs-new-results"></div>' +
        '<label class="msgs-field"><span>Subject</span><input type="text" id="msgs-new-subject" placeholder="e.g. Ticket delivery question"></label>' +
        '<label class="msgs-field"><span>First message (optional)</span><textarea id="msgs-new-body" rows="3" placeholder="Write the opening message…"></textarea></label>' +
      '</div>' +
      '<div class="msgs-modal-ft">' +
        '<button type="button" class="msgs-btn" onclick="MessagesControlCenter.closeNew()">Cancel</button>' +
        '<button type="button" class="msgs-btn msgs-btn-primary" onclick="MessagesControlCenter.createConversation()">' + ic('send', 14) + ' Start conversation</button>' +
      '</div>';
  }
  function searchCustomers(q) {
    var box = document.getElementById('msgs-new-results');
    if (!box) return;
    if (!q) { box.classList.remove('show'); return; }
    get('search', { q: q }).then(function(r) {
      newSearch = r.customers || [];
      if (!newSearch.length) { box.classList.remove('show'); return; }
      box.innerHTML = newSearch.map(function(cu) {
        return '<div class="msgs-new-cust" onclick="MessagesControlCenter.pickCustomer(\'' + esc(cu.customer_id) + '\',\'' + esc(cu.name) + '\',\'' + esc(cu.email) + '\')">' +
          '<div class="msgs-avatar sm">' + initials(cu.name) + '</div><div><b>' + esc(cu.name) + '</b><span>' + esc(cu.email) + ' · ' + num(cu.orders) + ' orders</span></div>' + ic('check', 12) +
        '</div>';
      }).join('');
      box.classList.add('show');
    }).catch(function() {});
  }
  function pickCustomer(id, name, email) {
    var inp = document.getElementById('msgs-new-cust');
    var box = document.getElementById('msgs-new-results');
    if (inp) inp.value = (name ? name + ' ' : '') + '<' + email + '>';
    if (box) box.classList.remove('show');
    newSearch = [{ customer_id: id }];
  }
  function createConversation() {
    var evt = document.getElementById('msgs-new-evt');
    var subject = document.getElementById('msgs-new-subject');
    var body = document.getElementById('msgs-new-body');
    var payload = {
      action: 'start',
      event_id: evt ? evt.value : '',
      subject: subject ? subject.value.trim() : '',
      body: body ? body.value.trim() : ''
    };
    if (newSearch.length && newSearch[0].customer_id) payload.customer_id = newSearch[0].customer_id;
    else {
      var raw = document.getElementById('msgs-new-cust');
      if (raw && raw.value.trim()) payload.customer_id = raw.value.trim();
      else return toast('Pick a customer to start with.', true);
    }
    post(payload).then(function(r) {
      closeNew();
      state.inbox = null;
      return refreshInbox().then(function() { openConversation(r.conversation.id); });
    }).catch(function(e) { toast(e.message, true); });
  }
  function closeNew() { newOpen = false; var o = document.getElementById('msgs-modal'); if (o) o.remove(); }

  function overlay() {
    var o = document.getElementById('msgs-modal');
    if (o) o.remove();
    o = document.createElement('div');
    o.id = 'msgs-modal';
    o.className = 'msgs-modal';
    o.innerHTML = '<div class="msgs-modal-card"></div>';
    o.addEventListener('click', function(e) { if (e.target === o) closeNew(); });
    document.body.appendChild(o);
    return o.querySelector('.msgs-modal-card');
  }

  /* ── Broadcasts ────────────────────────────────────────────────── */

  function renderBroadcasts() {
    if (!state.broadcasts) {
      BODY.innerHTML = '<div class="msgs-loading">' + ic('megaphone', 22) + ' Loading broadcasts & announcements…</div>';
      return get('broadcasts').then(function(r) { state.broadcasts = r.broadcasts || []; renderBroadcasts(); })
        .catch(function(e) { BODY.innerHTML = '<div class="msgs-error">' + esc(e.message) + '</div>'; });
    }
    var list = state.broadcasts.map(function(b) {
      var st = String(b.status || '').toLowerCase();
      var rateRow = num(b.sent_count) > 0
        ? '<div class="msgs-bcast-rates"><span title="Delivered">' + ic('check', 11) + ' ' + b.rates.delivery_rate + '% delivered</span><span title="Opened">' + ic('mail', 11) + ' ' + b.rates.open_rate + '% opened</span></div>'
        : '<div class="msgs-bcast-rates muted">Not sent yet · scheduled for ' + date(b.scheduled_at) + '</div>';
      return '<div class="msgs-bcast-row">' +
        '<div class="msgs-avatar sm ' + st + '">' + ic(b.kind === 'ANNOUNCEMENT' ? 'bell' : 'megaphone', 15) + '</div>' +
        '<div class="msgs-bcast-main">' +
          '<div class="msgs-bcast-top"><b>' + esc(b.title) + '</b><span class="msgs-bcast-kind ' + st + '">' + esc(b.kind.toLowerCase()) + '</span>' +
          '<span class="msgs-bcast-status ' + st + '">' + esc(b.status) + '</span>' + '<time>' + date(b.created_at) + '</time></div>' +
          '<div class="msgs-bcast-sub">' + (b.subject ? esc(b.subject) + ' · ' : '') + (b.event_title ? esc(b.event_title) + ' · ' : '') + esc(b.channel) + '</div>' +
          '<div class="msgs-bcast-nums">' +
            '<span><b>' + num(b.recipient_count) + '</b> recipients</span>' +
            '<span><b>' + num(b.sent_count) + '</b> sent</span>' +
            '<span><b>' + num(b.delivered_count) + '</b> delivered</span>' +
            '<span><b>' + num(b.opened_count) + '</b> opened</span>' +
            '<span class="fail"><b>' + num(b.failed_count) + '</b> failed</span>' +
          '</div>' + rateRow +
        '</div>' +
        '<button type="button" class="msgs-btn msgs-btn-icon" title="Delete" onclick="MessagesControlCenter.deleteBroadcast(\'' + esc(b.id) + '\')">' + ic('x', 14) + '</button>' +
      '</div>';
    }).join('') || '<div class="msgs-empty">' + ic('megaphone', 26) + '<div>No broadcasts yet — reach your whole audience in one click.</div></div>';

    BODY.innerHTML =
      '<div class="msgs-bcast-page">' +
        '<div class="msgs-page-head">' +
          '<div><div class="msgs-page-title">Broadcasts & Announcements</div>' +
          '<div class="msgs-page-sub">Mass communication to customers — one-way, scheduled or sent now. Announcements are official event information.</div></div>' +
          '<button type="button" class="msgs-btn msgs-btn-primary" onclick="MessagesControlCenter.openBroadcast()">' + ic('megaphone', 14) + '<span>New broadcast</span></button>' +
        '</div>' +
        '<div class="msgs-bcast-list">' + list + '</div>' +
      '</div>';
    document.getElementById('msgs-sub').textContent = state.broadcasts.length + ' campaigns';
  }

  function openBroadcast() {
    var evOpts = '<option value="">Whole account audience</option>' + state.events.map(function(e) {
      return '<option value="' + esc(e.event_id) + '">' + esc(e.title) + '</option>';
    }).join('');
    var ov = overlay();
    ov.innerHTML =
      '<div class="msgs-modal-head"><div><div class="msgs-modal-title">New broadcast / announcement</div>' +
      '<div class="msgs-modal-sub">The recipient count is computed live from your real orders and tickets.</div></div>' +
      '<button type="button" class="msgs-btn msgs-btn-icon" onclick="MessagesControlCenter.closeNew()">' + ic('x', 15) + '</button></div>' +
      '<div class="msgs-modal-body">' +
        '<label class="msgs-field"><span>Kind</span><select id="msgs-bc-kind"><option value="BROADCAST">Broadcast</option><option value="ANNOUNCEMENT">Announcement</option></select></label>' +
        '<label class="msgs-field"><span>Title</span><input type="text" id="msgs-bc-title" placeholder="e.g. Gate & parking update"></label>' +
        '<label class="msgs-field"><span>Subject line</span><input type="text" id="msgs-bc-subject" placeholder="Subject for email / banner"></label>' +
        '<label class="msgs-field"><span>Message</span><textarea id="msgs-bc-body" rows="5" placeholder="Body — you can use {{customer_name}}, {{event_name}}, {{ticket_number}}…"></textarea></label>' +
        '<div class="msgs-bc-aud">' +
          '<label class="msgs-field grow"><span>Event (audience filter)</span><select id="msgs-bc-evt" onchange="MessagesControlCenter.estimate()">' + evOpts + '</select></label>' +
          '<label class="msgs-field shrink"><span>Audience</span><select id="msgs-bc-audience" onchange="MessagesControlCenter.estimate()">' +
            '<option value="ALL_CUSTOMERS">All customers</option>' +
            '<option value="EVENT_ATTENDEES">Event attendees</option>' +
            '<option value="TICKET_HOLDERS">Ticket holders</option>' +
            '<option value="VIP_CUSTOMERS">VIP customers</option>' +
            '<option value="NOT_CHECKED_IN">Not checked in yet</option>' +
          '</select></label>' +
        '</div>' +
        '<div class="msgs-bc-est" id="msgs-bc-est">Computing audience…</div>' +
        '<div class="msgs-bc-sendrow">' +
          '<label class="msgs-check"><input type="checkbox" id="msgs-bc-now"> Send immediately</label>' +
          '<label class="msgs-field grow" id="msgs-bc-sched-wrap" style="display:none"><input type="datetime-local" id="msgs-bc-when"></label>' +
        '</div>' +
      '</div>' +
      '<div class="msgs-modal-ft">' +
        '<button type="button" class="msgs-btn" onclick="MessagesControlCenter.closeNew()">Cancel</button>' +
        '<button type="button" class="msgs-btn msgs-btn-primary" onclick="MessagesControlCenter.createBroadcast()">' + ic('send', 14) + ' Create campaign</button>' +
      '</div>';
    var chk = document.getElementById('msgs-bc-now');
    var wrap = document.getElementById('msgs-bc-sched-wrap');
    chk.addEventListener('change', function() { wrap.style.display = chk.checked ? 'none' : 'block'; });
    estimate();
  }
  function estimate() {
    var box = document.getElementById('msgs-bc-est');
    if (!box) return;
    var evt = document.getElementById('msgs-bc-evt');
    var aud = document.getElementById('msgs-bc-audience');
    var q = { audience: aud ? aud.value : 'ALL_CUSTOMERS', event_id: evt ? evt.value : '' };
    get('estimate_audience', q).then(function(r) {
      box.innerHTML = 'Audience: <b>' + esc(r.label) + '</b>' + (r.event_id ? ' · ' + esc((state.events.find(function(e) { return e.event_id === r.event_id; }) || {}).title || '') : '') +
        ' — reaches <b>' + num(r.recipient_count) + ' customers</b>' +
        (r.recipients && r.recipients.length ? '<div class="msgs-bc-recip-preview">' + r.recipients.slice(0, 6).map(function(x) { return '<span>' + esc(x.name) + '</span>'; }).join('') + (r.recipients.length > 6 ? '<span>+ ' + (r.recipients.length - 6) + ' more…</span>' : '') + '</div>' : '');
    }).catch(function() { box.innerHTML = 'Audience: —'; });
  }
  function createBroadcast() {
    var payload = {
      action: 'broadcast_create',
      kind: document.getElementById('msgs-bc-kind').value,
      title: document.getElementById('msgs-bc-title').value.trim(),
      subject: document.getElementById('msgs-bc-subject').value.trim(),
      body: document.getElementById('msgs-bc-body').value.trim(),
      event_id: document.getElementById('msgs-bc-evt').value,
      audience_config: JSON.stringify({ audience: document.getElementById('msgs-bc-audience').value, event_id: document.getElementById('msgs-bc-evt').value }),
      send_now: document.getElementById('msgs-bc-now').checked ? '1' : '',
      scheduled_at: document.getElementById('msgs-bc-now').checked ? '' : (document.getElementById('msgs-bc-when').value || '')
    };
    if (!payload.title || !payload.body) return toast('Title and message are required.', true);
    post(payload).then(function() {
      closeNew();
      state.broadcasts = null;
      renderBroadcasts();
      toast('Campaign created.');
    }).catch(function(e) { toast(e.message, true); });
  }
  function deleteBroadcast(id) {
    if (!window.confirm('Delete this campaign?')) return;
    post({ action: 'broadcast_delete', id: id }).then(function(r) {
      state.broadcasts = r.broadcasts || [];
      renderBroadcasts();
    }).catch(function(e) { toast(e.message, true); });
  }

  /* ── Templates ─────────────────────────────────────────────────── */

  function renderTemplates() {
    if (!state.templates) {
      BODY.innerHTML = '<div class="msgs-loading">' + ic('doc', 22) + ' Loading templates…</div>';
      return get('templates').then(function(r) { state.templates = r.templates || []; renderTemplates(); })
        .catch(function(e) { BODY.innerHTML = '<div class="msgs-error">' + esc(e.message) + '</div>'; });
    }
    var grid = state.templates.map(function(t) {
      var vars = (t.variables || []).map(function(v) { return '<span class="msgs-tpl-var">{{' + esc(v) + '}}</span>'; }).join('');
      return '<div class="msgs-tpl-card">' +
        '<div class="msgs-tpl-top"><div class="msgs-tpl-cat">' + esc(t.category) + '</div>' +
        '<button type="button" class="msgs-btn msgs-btn-icon" title="Edit" onclick="MessagesControlCenter.editTemplate(\'' + esc(t.id) + '\')">' + ic('doc', 14) + '</button></div>' +
        '<div class="msgs-tpl-title">' + esc(t.title) + '</div>' +
        '<div class="msgs-tpl-subject">' + (t.subject ? esc(t.subject) : 'No subject') + '</div>' +
        '<div class="msgs-tpl-body">' + esc(t.body) + '</div>' +
        '<div class="msgs-tpl-meta">' + '</div>' +
        '<div class="msgs-tpl-vars">' + (vars || '<span class="msgs-tpl-var none">no variables</span>') + '</div>' +
        '<div class="msgs-tpl-ft"><span>' + num(t.usage_count) + ' uses</span><button type="button" class="msgs-btn msgs-btn-soft" onclick="MessagesControlCenter.editTemplate(\'' + esc(t.id) + '\')">Edit</button></div>' +
      '</div>';
    }).join('') || '<div class="msgs-empty">' + ic('doc', 26) + '<div>No templates yet.</div></div>';

    BODY.innerHTML =
      '<div class="msgs-tpl-page">' +
        '<div class="msgs-page-head">' +
          '<div><div class="msgs-page-title">Message templates</div>' +
          '<div class="msgs-page-sub">Reusable messages with live variables — {{customer_name}}, {{event_name}}, {{ticket_number}}, {{amount}} and more.</div></div>' +
          '<button type="button" class="msgs-btn msgs-btn-primary" onclick="MessagesControlCenter.editTemplate()">' + ic('doc', 14) + '<span>New template</span></button>' +
        '</div>' +
        '<div class="msgs-tpl-grid">' + grid + '</div>' +
      '</div>';
    document.getElementById('msgs-sub').textContent = state.templates.length + ' templates';
  }

  function editTemplate(id) {
    var tpl = id ? (state.templates.filter(function(t) { return t.id === id; })[0] || null) : null;
    var ov = overlay();
    ov.innerHTML =
      '<div class="msgs-modal-head"><div><div class="msgs-modal-title">' + (tpl ? 'Edit template' : 'New template') + '</div>' +
      '<div class="msgs-modal-sub">Variables in {{curly braces}} are filled with live order data when used.</div></div>' +
      '<button type="button" class="msgs-btn msgs-btn-icon" onclick="MessagesControlCenter.closeNew()">' + ic('x', 15) + '</button></div>' +
      '<div class="msgs-modal-body">' +
        (tpl ? '<input type="hidden" id="msgs-tpl-id" value="' + esc(tpl.id) + '">' : '') +
        '<div class="msgs-bc-aud">' +
          '<label class="msgs-field grow"><span>Title</span><input type="text" id="msgs-tpl-title" value="' + esc(tpl ? tpl.title : '') + '"></label>' +
          '<label class="msgs-field shrink"><span>Category</span><input type="text" id="msgs-tpl-cat" value="' + esc(tpl ? tpl.category : 'General') + '"></label>' +
        '</div>' +
        '<label class="msgs-field"><span>Subject</span><input type="text" id="msgs-tpl-subject" value="' + esc(tpl ? tpl.subject : '') + '"></label>' +
        '<label class="msgs-field"><span>Body</span><textarea id="msgs-tpl-body" rows="7">' + esc(tpl ? tpl.body : '') + '</textarea></label>' +
        '<div class="msgs-tpl-var-hint">Available variables: ' + ['customer_name', 'event_name', 'event_date', 'ticket_type', 'ticket_number', 'venue_name', 'order_id', 'amount'].map(function(v) { return '{{' + v + '}}'; }).join(' ') + '</div>' +
      '</div>' +
      '<div class="msgs-modal-ft">' +
        (tpl ? '<button type="button" class="msgs-btn msgs-btn-danger" onclick="MessagesControlCenter.deleteTemplate(\'' + esc(tpl.id) + '\')">Delete</button>' : '') +
        '<button type="button" class="msgs-btn" onclick="MessagesControlCenter.closeNew()">Cancel</button>' +
        '<button type="button" class="msgs-btn msgs-btn-primary" onclick="MessagesControlCenter.saveTemplate()">' + ic('check', 14) + ' Save template</button>' +
      '</div>';
  }
  function saveTemplate() {
    var payload = {
      action: 'template_save',
      id: (document.getElementById('msgs-tpl-id') || {}).value || '',
      title: document.getElementById('msgs-tpl-title').value.trim(),
      category: document.getElementById('msgs-tpl-cat').value.trim() || 'General',
      subject: document.getElementById('msgs-tpl-subject').value.trim(),
      body: document.getElementById('msgs-tpl-body').value.trim()
    };
    if (!payload.title || !payload.body) return toast('Title and body are required.', true);
    post(payload).then(function(r) {
      closeNew();
      state.templates = r.templates || [];
      renderTemplates();
      toast('Template saved.');
    }).catch(function(e) { toast(e.message, true); });
  }
  function deleteTemplate(id) {
    if (!window.confirm('Delete this template?')) return;
    post({ action: 'template_delete', id: id }).then(function(r) {
      closeNew();
      state.templates = r.templates || [];
      renderTemplates();
    }).catch(function(e) { toast(e.message, true); });
  }

  /* ── Automations ───────────────────────────────────────────────── */

  function renderAutomations() {
    if (!state.automations) {
      BODY.innerHTML = '<div class="msgs-loading">' + ic('bolt', 22) + ' Loading automations…</div>';
      return get('automations').then(function(r) { state.automations = r; renderAutomations(); })
        .catch(function(e) { BODY.innerHTML = '<div class="msgs-error">' + esc(e.message) + '</div>'; });
    }
    var auto = state.automations.automations || [];
    var triggers = state.automations.triggers || [];
    var trigLabel = {};
    triggers.forEach(function(t) { trigLabel[String(t).toUpperCase()] = String(t).replace(/_/g, ' '); });
    var list = auto.map(function(a) {
      var label = trigLabel[a.trigger_type] || String(a.trigger_type || '').replace(/_/g, ' ');
      return '<div class="msgs-auto-row' + (num(a.is_active) ? '' : ' off') + '">' +
        '<div class="msgs-avatar sm">' + ic('bolt', 15) + '</div>' +
        '<div class="msgs-auto-main">' +
          '<div class="msgs-auto-top"><b>' + esc(label) + '</b>' +
          '<span class="msgs-auto-toggle' + (num(a.is_active) ? ' on' : ' off') + '" onclick="MessagesControlCenter.toggleAutomation(\'' + esc(a.id) + '\')">' + (num(a.is_active) ? 'ACTIVE' : 'PAUSED') + '</span>' +
          '<time>' + date(a.updated_at) + '</time></div>' +
          '<div class="msgs-auto-sub">' + esc(a.event_title || 'All events') + ' · ' + esc(String(a.audience || '').replace(/_/g, ' ')) + (a.offset_hours ? ' · ' + a.offset_hours + 'h offset' : '') + '</div>' +
          '<div class="msgs-auto-subject">' + (a.subject ? esc(a.subject) : 'No subject') + '</div>' +
          '<div class="msgs-auto-body">' + esc(a.body) + '</div>' +
          '<div class="msgs-auto-meta"><span><b>' + num(a.run_count) + '</b> runs</span>' + (a.last_run_at ? '<span>last run ' + date(a.last_run_at) + '</span>' : '') + '</div>' +
        '</div>' +
        '<div class="msgs-auto-ops">' +
          '<button type="button" class="msgs-btn msgs-btn-icon" title="Edit" onclick="MessagesControlCenter.editAutomation(\'' + esc(a.id) + '\')">' + ic('doc', 14) + '</button>' +
          '<button type="button" class="msgs-btn msgs-btn-icon" title="Delete" onclick="MessagesControlCenter.deleteAutomation(\'' + esc(a.id) + '\')">' + ic('x', 14) + '</button>' +
        '</div>' +
      '</div>';
    }).join('') || '<div class="msgs-empty">' + ic('bolt', 26) + '<div>No automations — trigger transactional messages from real events.</div></div>';

    BODY.innerHTML =
      '<div class="msgs-auto-page">' +
        '<div class="msgs-page-head">' +
          '<div><div class="msgs-page-title">Automated messages</div>' +
          '<div class="msgs-page-sub">Trigger-based transactional messages: confirmations, reminders, refunds and follow-ups that fire from ticket, payment and event activity.</div></div>' +
          '<button type="button" class="msgs-btn msgs-btn-primary" onclick="MessagesControlCenter.editAutomation()">' + ic('bolt', 14) + '<span>New automation</span></button>' +
        '</div>' +
        '<div class="msgs-auto-list">' + list + '</div>' +
      '</div>';
    document.getElementById('msgs-sub').textContent = auto.filter(function(a) { return num(a.is_active); }).length + ' active automations';
  }

  function editAutomation(id) {
    var a = id ? ((state.automations.automations || []).filter(function(x) { return x.id === id; })[0] || null) : null;
    var trigOpts = (state.automations.triggers || []).map(function(t) {
      return '<option value="' + esc(t) + '"' + (a && a.trigger_type === String(t).toUpperCase() ? ' selected' : '') + '>' + esc(String(t).toUpperCase().replace(/_/g, ' ')) + '</option>';
    }).join('');
    var evOpts = '<option value="">All events</option>' + state.events.map(function(e) {
      return '<option value="' + esc(e.event_id) + '"' + (a && a.event_id === e.event_id ? ' selected' : '') + '>' + esc(e.title) + '</option>';
    }).join('');
    var audOpts = (state.automations.audiences || []).map(function(x) {
      return '<option value="' + esc(x) + '"' + (a && a.audience === x ? ' selected' : '') + '>' + esc(x.replace(/_/g, ' ')) + '</option>';
    }).join('');
    var ov = overlay();
    ov.innerHTML =
      '<div class="msgs-modal-head"><div><div class="msgs-modal-title">' + (a ? 'Edit automation' : 'New automation') + '</div>' +
      '<div class="msgs-modal-sub">Fires when the trigger happens — the message uses live order data.</div></div>' +
      '<button type="button" class="msgs-btn msgs-btn-icon" onclick="MessagesControlCenter.closeNew()">' + ic('x', 15) + '</button></div>' +
      '<div class="msgs-modal-body">' +
        (a ? '<input type="hidden" id="msgs-auto-id" value="' + esc(a.id) + '">' : '') +
        '<div class="msgs-bc-aud">' +
          '<label class="msgs-field grow"><span>Trigger</span><select id="msgs-auto-trigger">' + trigOpts + '</select></label>' +
          '<label class="msgs-field shrink"><span>Offset (hours)</span><input type="number" id="msgs-auto-offset" min="0" value="' + (a ? num(a.offset_hours) : 0) + '"></label>' +
        '</div>' +
        '<div class="msgs-bc-aud">' +
          '<label class="msgs-field grow"><span>Event scope</span><select id="msgs-auto-evt">' + evOpts + '</select></label>' +
          '<label class="msgs-field shrink"><span>Audience</span><select id="msgs-auto-aud">' + audOpts + '</select></label>' +
        '</div>' +
        '<label class="msgs-field"><span>Subject</span><input type="text" id="msgs-auto-subject" value="' + esc(a ? a.subject : '') + '"></label>' +
        '<label class="msgs-field"><span>Body</span><textarea id="msgs-auto-body" rows="6">' + esc(a ? a.body : '') + '</textarea></label>' +
        '<label class="msgs-check"><input type="checkbox" id="msgs-auto-active"' + (a && num(a.is_active) ? ' checked' : '') + '> Active — send when the trigger fires</label>' +
      '</div>' +
      '<div class="msgs-modal-ft">' +
        (a ? '<button type="button" class="msgs-btn msgs-btn-danger" onclick="MessagesControlCenter.deleteAutomation(\'' + esc(a.id) + '\')">Delete</button>' : '') +
        '<button type="button" class="msgs-btn" onclick="MessagesControlCenter.closeNew()">Cancel</button>' +
        '<button type="button" class="msgs-btn msgs-btn-primary" onclick="MessagesControlCenter.saveAutomation()">' + ic('check', 14) + ' Save automation</button>' +
      '</div>';
  }
  function saveAutomation() {
    var payload = {
      action: 'automation_save',
      id: (document.getElementById('msgs-auto-id') || {}).value || '',
      trigger_type: document.getElementById('msgs-auto-trigger').value,
      offset_hours: document.getElementById('msgs-auto-offset').value,
      event_id: document.getElementById('msgs-auto-evt').value,
      audience: document.getElementById('msgs-auto-aud').value,
      subject: document.getElementById('msgs-auto-subject').value.trim(),
      body: document.getElementById('msgs-auto-body').value.trim(),
      is_active: document.getElementById('msgs-auto-active').checked ? '1' : ''
    };
    if (!payload.body) return toast('Message body is required.', true);
    post(payload).then(function(r) {
      closeNew();
      state.automations = r;
      renderAutomations();
      toast('Automation saved.');
    }).catch(function(e) { toast(e.message, true); });
  }
  function toggleAutomation(id) {
    var a = (state.automations.automations || []).filter(function(x) { return x.id === id; })[0];
    if (!a) return;
    var payload = {
      action: 'automation_save', id: id,
      trigger_type: a.trigger_type, offset_hours: a.offset_hours, event_id: a.event_id || '',
      audience: a.audience, subject: a.subject || '', body: a.body, is_active: num(a.is_active) ? '' : '1'
    };
    post(payload).then(function(r) { state.automations = r; renderAutomations(); })
      .catch(function(e) { toast(e.message, true); });
  }
  function deleteAutomation(id) {
    if (!window.confirm('Delete this automation?')) return;
    post({ action: 'automation_delete', id: id }).then(function(r) {
      closeNew();
      state.automations = r;
      renderAutomations();
    }).catch(function(e) { toast(e.message, true); });
  }

  /* ── init ──────────────────────────────────────────────────────── */

  function init() {
    if (!document.getElementById('msgs-root')) return;
    if (state.booted) return render();
    state.booted = true;
    ROOT = document.getElementById('msgs-root');
    shell();
    if (!state.events.length) {
      get('events').then(function(ev) { state.events = ev.events || ev || []; refreshInbox(); })
        .catch(function() { refreshInbox(); });
    }
  }

  return {
    init: init,
    render: render,
    setTab: setTab,
    setView: setView,
    setEvent: setEvent,
    search: search,
    refreshInbox: refreshInbox,
    openConversation: openConversation,
    op: op,
    addTag: addTag,
    untag: untag,
    addNote: addNote,
    sendReply: sendReply,
    sendCard: sendCard,
    useSuggestion: useSuggestion,
    openNew: openNew,
    searchCustomers: searchCustomers,
    pickCustomer: pickCustomer,
    createConversation: createConversation,
    closeNew: closeNew,
    openBroadcast: openBroadcast,
    estimate: estimate,
    createBroadcast: createBroadcast,
    deleteBroadcast: deleteBroadcast,
    renderTemplates: renderTemplates,
    editTemplate: editTemplate,
    saveTemplate: saveTemplate,
    deleteTemplate: deleteTemplate,
    editAutomation: editAutomation,
    saveAutomation: saveAutomation,
    toggleAutomation: toggleAutomation,
    deleteAutomation: deleteAutomation
  };
})();

/* Auto-engage when the user opens the Messages tab. */
(function() {
  var prev = window.onEccModuleShow;
  window.onEccModuleShow = function(modId) {
    if (typeof prev === 'function') { try { prev(modId); } catch (e) {} }
    if (modId === 'messages' && window.MessagesControlCenter) {
      window.MessagesControlCenter.init();
    }
  };
})();