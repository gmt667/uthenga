/**
 * Uthenga Marketplace — Main JavaScript
 * Vanilla JS — no frameworks, no dependencies
 */

'use strict';

function createInlineSvgIcon(name) {
  const icons = {
    search: '<path d="M10.5 4a6.5 6.5 0 1 0 4.1 11.5l4.7 4.7 1.4-1.4-4.7-4.7A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Z" fill="currentColor"/>',
    calendar: '<path d="M7 2v2H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2zM3 12v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8Z" fill="currentColor"/>',
    map: '<path d="M15 3l-6 2.2L3 3v16l6 2.2L15 19l6 2V5zm-2 14.4-4-1.5V6.2l4 1.5v9.7z" fill="currentColor"/>',
    pin: '<path d="M12 22s6-5.3 6-11a6 6 0 1 0-12 0c0 5.7 6 11 6 11zm0-8.2A2.8 2.8 0 1 1 12 8.2a2.8 2.8 0 0 1 0 5.6z" fill="currentColor"/>',
    bus: '<path d="M4 16c0 .88.39 1.67 1 2.22V20a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1h8v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1.78A3 3 0 0 0 20 16V6c0-3.5-3.58-4-8-4S4 2.5 4 6v10zm3.5 1a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm9 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM6 10V6h12v4H6z" fill="currentColor"/>',
    car: '<path d="M3 12.5 5 8h14l2 4.5V18h-2a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H3zM7 10 6 12h12l-1-2z" fill="currentColor"/>',
    hotel: '<path d="M4 21V3h10v18h-3v-3H7v3zm3-5h4v-2H7zm0-4h4v-2H7zm0-4h4V6H7zm12-2h-4v18h4V8h1V6a2 2 0 0 0-2-2z" fill="currentColor"/>',
    searchPlus: '<path d="M10.5 4a6.5 6.5 0 1 0 4.1 11.5l4.7 4.7 1.4-1.4-4.7-4.7A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Zm-1 1.5h2v2h2v2h-2v2h-2v-2h-2v-2h2z" fill="currentColor"/>',
    ticket: '<path d="M3 7a2 2 0 0 1 2-2h14v3a2 2 0 1 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 1 0 0-4z" fill="currentColor"/>',
    star: '<path d="m12 2.5 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.2 6.2 20.4l1.1-6.5L2.6 9.3l6.5-.9z" fill="currentColor"/>',
    heart: '<path d="M12 21s-7.5-4.5-9.5-9A5.7 5.7 0 0 1 12 5.5 5.7 5.7 0 0 1 21.5 12c-2 4.5-9.5 9-9.5 9z" fill="currentColor"/>',
    wallet: '<path d="M4 6a3 3 0 0 1 3-3h12v3H7a1 1 0 0 0 0 2h13v10H7a3 3 0 0 1-3-3zm14 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z" fill="currentColor"/>',
    link: '<path d="M10.5 13.5a1 1 0 0 1 0-1.4l2.6-2.6a4 4 0 0 1 5.7 5.7l-1.7 1.7a4 4 0 0 1-5.7 0 1 1 0 1 1 1.4-1.4 2 2 0 0 0 2.8 0l1.7-1.7a2 2 0 1 0-2.8-2.8l-2.6 2.6a1 1 0 0 1-1.4 0Zm-1 1a1 1 0 0 1 0 1.4l-2.6 2.6a4 4 0 1 1-5.7-5.7l1.7-1.7a4 4 0 0 1 5.7 0 1 1 0 0 1-1.4 1.4 2 2 0 0 0-2.8 0L2.7 14.2a2 2 0 1 0 2.8 2.8l2.6-2.6a1 1 0 0 1 1.4 0Z" fill="currentColor"/>',
    cart: '<path d="M4 4h2l1.5 8h9.8l1.3-5H8.3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="19" r="1.5" fill="currentColor"/><circle cx="17" cy="19" r="1.5" fill="currentColor"/>',
    chat: '<path d="M4 4h16v11H7l-3 3V4Z" fill="currentColor"/>',
    mail: '<path d="M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1zm0 2v.3l8 5.2 8-5.2V8z" fill="currentColor"/>',
    phone: '<path d="M6 3h4l2 5-2 2c1.4 2.8 3.5 4.9 6.3 6.3l2-2 5 2v4c0 1.1-.9 2-2 2C10 22 2 14 2 5c0-1.1.9-2 2-2z" fill="currentColor"/>',
    megaphone: '<path d="M4 13v-2a2 2 0 0 1 2-2h2l8-4v14l-8-4H6a2 2 0 0 1-2-2Zm14-5.5a4.5 4.5 0 0 1 0 9v-2a2.5 2.5 0 0 0 0-5z" fill="currentColor"/>',
    shield: '<path d="M12 2 4 5v6c0 5 3.5 8.8 8 11 4.5-2.2 8-6 8-11V5z" fill="currentColor"/>',
    check: '<path d="m9.2 16.2-4.1-4.1 1.4-1.4 2.7 2.7L17.5 5l1.4 1.4z" fill="currentColor"/>',
    x: '<path d="M6 6 18 18M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    info: '<path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm0 4a1.3 1.3 0 1 1 0 2.6A1.3 1.3 0 0 1 12 6zm-1.1 4h2.2v8h-2.2z" fill="currentColor"/>',
    warning: '<path d="M12 3 1.8 20h20.4L12 3zm0 5.7 1 5.7h-2zM12 16.6a1.3 1.3 0 1 1 0 2.6 1.3 1.3 0 0 1 0-2.6z" fill="currentColor"/>',
    sparkles: '<path d="M12 2 13.8 8.2 20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8z" fill="currentColor"/>',
    plane: '<path d="M2 12l20-7-7 20-4-8-9-5z" fill="currentColor"/>',
    bot: '<path d="M9 3h6v2h4a2 2 0 0 1 2 2v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a2 2 0 0 1 2-2h4zm3 6a4 4 0 1 0 0 8 4 4 0 0 0 0-8z" fill="currentColor"/>',
    wave: '<path d="M5 13c2.2-2.8 4.1-4.2 5.8-4.2 1.4 0 2.2.7 3.2 1.7l1.4 1.4c.7.7 1.3 1 2.1 1 1.1 0 2.2-.8 3.5-2.4l1.5 1.3c-1.8 2.5-3.5 3.7-5 3.7-1.4 0-2.4-.7-3.4-1.7l-1.4-1.4c-.7-.7-1.3-1-2-1-1.3 0-3 1.5-4.8 3.9z" fill="currentColor"/>',
    compass: '<path d="m12 2 3.8 7.8L22 12l-6.2 2.2L12 22l-3.8-7.8L2 12l6.2-2.2z" fill="currentColor"/>',
    sun: '<path d="M12 4.5a1 1 0 0 1 1 1V7a1 1 0 1 1-2 0V5.5a1 1 0 0 1 1-1Zm0 10.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM5.2 6.6a1 1 0 0 1 1.4 0l1 1A1 1 0 0 1 6.2 9l-1-1a1 1 0 0 1 0-1.4Zm11.2 1a1 1 0 1 1 1.4 1.4l-1 1A1 1 0 0 1 16.4 8l1-1ZM4.5 11a1 1 0 0 1 1 1 1 1 0 1 1-1 1 1 1 0 0 1 0-2Zm14 0a1 1 0 0 1 1 1 1 1 0 1 1-1 1 1 1 0 0 1 0-2ZM6.6 17.6a1 1 0 0 1 1.4 0l1-1A1 1 0 1 1 7.6 18l-1-1a1 1 0 0 1 0-1.4Zm10.8-1a1 1 0 0 1 1.4 1.4l-1 1a1 1 0 1 1-1.4-1.4l1-1ZM12 16.5a1 1 0 0 1 1 1V19a1 1 0 1 1-2 0v-1.5a1 1 0 0 1 1-1Z" fill="currentColor"/>',
    moon: '<path d="M15.5 4.5a7.7 7.7 0 1 0 4 14.2A9 9 0 1 1 15.5 4.5Z" fill="currentColor"/>'
  };
  return `<svg class="ui-emoji-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${icons[name] || icons.info}</svg>`;
}

function replaceEmojiWithIcons(root) {
  if (!root || !root.ownerDocument) return;

  const replacements = [
    ['ðŸ¤–', 'bot'],
    ['🤖', 'bot'],
    ['ðŸ‘‹', 'wave'],
    ['👋', 'wave'],
    ['ðŸŒŠ', 'compass'],
    ['🌊', 'compass'],
    ['ðŸŽ«', 'ticket'],
    ['🎫', 'ticket'],
    ['ðŸ›ï¸', 'map'],
    ['🏛️', 'map'],
    ['ðŸŽ¤', 'ticket'],
    ['🎤', 'ticket'],
    ['ðŸ’°', 'wallet'],
    ['💰', 'wallet'],
    ['ðŸ“…', 'calendar'],
    ['📅', 'calendar'],
    ['ðŸ¨', 'hotel'],
    ['🏨', 'hotel'],
    ['ðŸšŒ', 'bus'],
    ['🚌', 'bus'],
    ['ðŸš—', 'car'],
    ['🚗', 'car'],
    ['ðŸ”', 'search'],
    ['🔍', 'search'],
    ['ðŸŽ‰', 'sparkles'],
    ['🎉', 'sparkles'],
    ['ðŸ’³', 'wallet'],
    ['💳', 'wallet'],
    ['ðŸ“…', 'calendar'],
    ['ðŸ“', 'pin'],
    ['📍', 'pin'],
    ['ðŸ”—', 'link'],
    ['🔗', 'link'],
    ['ðŸ’¬', 'chat'],
    ['💬', 'chat'],
    ['ðŸ”—', 'link'],
    ['ðŸ”’', 'shield'],
    ['🔒', 'shield'],
    ['ðŸŸ¦', 'check'],
    ['🟦', 'check'],
    ['ðŸŸ¥', 'x'],
    ['🟥', 'x'],
    ['ðŸŸ©', 'check'],
    ['🟩', 'check'],
    ['ðŸ’¡', 'info'],
    ['💡', 'info'],
    ['ðŸš', 'car'],
    ['🚍', 'bus'],
    ['ðŸšº', 'bus'],
    ['🛺', 'car'],
    ['ðŸ›º', 'car'],
    ['🛡️', 'shield'],
    ['ðŸ›¡ï¸', 'shield'],
    ['🛍️', 'cart'],
    ['ðŸ›ï¸', 'cart'],
    ['⭐', 'star'],
    ['✨', 'sparkles'],
    ['📣', 'megaphone'],
    ['📧', 'mail'],
    ['📞', 'phone'],
    ['⚠️', 'warning'],
    ['⚠', 'warning'],
    ['❌', 'x'],
    ['✕', 'x'],
    ['✓', 'check'],
    ['✅', 'check'],
    ['🚶', 'wave'],
    ['✈️', 'plane']
  ];

  const skipTags = new Set(['SCRIPT', 'STYLE', 'TEXTAREA', 'INPUT', 'SELECT', 'OPTION', 'CODE', 'PRE', 'NOSCRIPT', 'SVG']);
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      if (!node || !node.parentElement || skipTags.has(node.parentElement.tagName)) return NodeFilter.FILTER_REJECT;
      const value = node.nodeValue || '';
      return replacements.some(([needle]) => value.includes(needle)) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
    }
  });

  const nodes = [];
  while (walker.nextNode()) nodes.push(walker.currentNode);

  nodes.forEach((node) => {
    const text = node.nodeValue || '';
    const frag = document.createDocumentFragment();
    let cursor = 0;
    let changed = false;

    while (cursor < text.length) {
      let bestIndex = -1;
      let bestNeedle = '';
      let bestIcon = '';

      for (const [needle, icon] of replacements) {
        const idx = text.indexOf(needle, cursor);
        if (idx !== -1 && (bestIndex === -1 || idx < bestIndex)) {
          bestIndex = idx;
          bestNeedle = needle;
          bestIcon = icon;
        }
      }

      if (bestIndex === -1) break;

      if (bestIndex > cursor) {
        frag.appendChild(document.createTextNode(text.slice(cursor, bestIndex)));
      }

      const span = document.createElement('span');
      span.className = `ui-emoji-icon-wrap ui-emoji-icon-${bestIcon}`;
      span.innerHTML = createInlineSvgIcon(bestIcon);
      frag.appendChild(span);
      cursor = bestIndex + bestNeedle.length;
      changed = true;
    }

    if (!changed) return;
    if (cursor < text.length) {
      frag.appendChild(document.createTextNode(text.slice(cursor)));
    }
    node.parentNode.replaceChild(frag, node);
  });
}

// Preserve scroll position across PHP page reloads so sidebar navigation feels stable.
(function () {
  try {
    if ('scrollRestoration' in history) {
      history.scrollRestoration = 'manual';
    }
  } catch (e) {}

  const key = () => `uthenga-scroll:${location.pathname}${location.search}`;

  const restore = () => {
    try {
      if (location.hash) return;
      const saved = sessionStorage.getItem(key());
      if (!saved) return;
      const y = parseInt(saved, 10);
      if (!Number.isNaN(y)) window.scrollTo(0, y);
    } catch (e) {}
  };

  const save = () => {
    try {
      sessionStorage.setItem(key(), String(window.scrollY || window.pageYOffset || 0));
    } catch (e) {}
  };

  window.addEventListener('beforeunload', save);
  window.addEventListener('pagehide', save);
  window.addEventListener('load', restore);
  window.addEventListener('pageshow', restore);
})();

// Replace legacy emoji markers with flat inline icons across the rendered UI.
(function () {
  const run = () => {
    try {
      replaceEmojiWithIcons(document.body);
    } catch (e) {}
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }

  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      for (const node of mutation.addedNodes || []) {
        if (node && node.nodeType === Node.ELEMENT_NODE) {
          replaceEmojiWithIcons(node);
        }
      }
    }
  });

  if (document.body) {
    observer.observe(document.body, { childList: true, subtree: true });
  } else {
    window.addEventListener('DOMContentLoaded', () => {
      if (document.body) observer.observe(document.body, { childList: true, subtree: true });
    }, { once: true });
  }
})();

// Lightweight virtual presentation panels for dashboard summaries.
(function () {
  const panels = document.querySelectorAll('[data-virtual-presentation]');
  if (!panels.length) return;

  panels.forEach((panel) => {
    const slides = Array.from(panel.querySelectorAll('[data-virtual-presentation-slide], [data-presentation-slide]'));
    const tabs = Array.from(panel.querySelectorAll('[data-virtual-presentation-tab], [data-presentation-tab]'));
    if (!slides.length) return;

    const interval = Math.max(parseInt(panel.dataset.interval || '6500', 10), 2500);
    let index = Math.max(
      slides.findIndex((slide) => slide.classList.contains('active') || slide.classList.contains('is-active')),
      0
    );
    let timer = null;

    const activate = (next) => {
      index = (next + slides.length) % slides.length;
      slides.forEach((slide, i) => {
        const active = i === index;
        slide.classList.toggle('active', active);
        slide.classList.toggle('is-active', active);
      });
      tabs.forEach((tab, i) => {
        const active = i === index;
        tab.classList.toggle('active', active);
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });
    };

    const stop = () => {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    };

    const start = () => {
      if (slides.length < 2) return;
      stop();
      timer = window.setInterval(() => activate(index + 1), interval);
    };

    tabs.forEach((tab, i) => {
      tab.addEventListener('click', () => {
        activate(i);
        start();
      });
    });

    panel.addEventListener('mouseenter', stop);
    panel.addEventListener('mouseleave', start);
    panel.addEventListener('focusin', stop);
    panel.addEventListener('focusout', start);
    activate(index);
    start();
  });
})();

// Profile dropdown toggle for touch devices and consistent behavior
(function () {
  const dropdown = document.querySelector('.profile-dropdown');
  const trigger = document.getElementById('profile-dropdown-trigger');
  if (!dropdown || !trigger) return;

  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = dropdown.classList.toggle('open');
    trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.profile-dropdown')) {
      dropdown.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
    }
  });
})();

// Admin sidebar toggle for mobile/tablet layouts
(function () {
  const toggle = document.querySelector('[data-dashboard-sidebar-toggle]');
  const shell = document.querySelector('.dashboard-shell');
  const sidebar = document.querySelector('.admin-sidebar');
  if (!toggle || !shell || !sidebar) return;

  const isDesktop = () => window.matchMedia('(min-width: 1025px)').matches;

  function syncSidebarMode() {
    if (isDesktop()) {
      shell.classList.remove('admin-sidebar-open');
      toggle.setAttribute('aria-expanded', shell.classList.contains('sidebar-collapsed') ? 'false' : 'true');
    } else {
      shell.classList.remove('sidebar-collapsed');
      toggle.setAttribute('aria-expanded', shell.classList.contains('admin-sidebar-open') ? 'true' : 'false');
    }
  }

  syncSidebarMode();

  toggle.addEventListener('click', (e) => {
    e.stopPropagation();
    if (isDesktop()) {
      shell.classList.toggle('sidebar-collapsed');
      toggle.setAttribute('aria-expanded', shell.classList.contains('sidebar-collapsed') ? 'false' : 'true');
    } else {
      const open = shell.classList.toggle('admin-sidebar-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
  });

  document.addEventListener('click', (e) => {
    if (!shell.classList.contains('admin-sidebar-open')) return;
    if (e.target.closest('.admin-sidebar') || e.target.closest('[data-dashboard-sidebar-toggle]')) return;
    shell.classList.remove('admin-sidebar-open');
    toggle.setAttribute('aria-expanded', 'false');
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    shell.classList.remove('admin-sidebar-open');
    toggle.setAttribute('aria-expanded', 'false');
  });

  window.addEventListener('resize', syncSidebarMode);
})();

// ─── Active nav link auto-detect ──────────────────────────────────────────────
(function () {
  const path = window.location.pathname;
  document.querySelectorAll('.navbar-links a').forEach(a => {
    if (a.href && new URL(a.href).pathname === path) a.classList.add('active');
  });
})();


const BASE = document.querySelector('meta[name="base-url"]')?.content || '';

function utPwToggle(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;

  const nextType = input.type === 'password' ? 'text' : 'password';
  input.type = nextType;

  if (btn) {
    const eyeOff = btn.querySelector('.pw-eye-off');
    const eyeOn = btn.querySelector('.pw-eye-on');
    const showingText = nextType === 'text';
    if (eyeOff) eyeOff.style.display = showingText ? 'none' : '';
    if (eyeOn) eyeOn.style.display = showingText ? '' : 'none';
    btn.setAttribute('aria-pressed', showingText ? 'true' : 'false');
    btn.setAttribute('aria-label', showingText ? 'Hide password' : 'Show password');
  }
}

window.utPwToggle = window.utPwToggle || utPwToggle;

async function api(action, data = {}) {
  const form = new FormData();
  form.append('action', action);
  // Include CSRF token from meta tag
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  if (csrf) form.append('csrf_token', csrf);
  for (const [k, v] of Object.entries(data)) form.append(k, v);

  const res = await fetch(BASE + 'request_api.php', { method: 'POST', body: form });
  return res.json();
}

function trackEventMetric(eventId, metric = 'click') {
  if (!eventId) return;
  const payload = new URLSearchParams();
  payload.set('event_id', eventId);
  payload.set('metric', metric || 'click');
  const url = BASE + 'api/track_event_view.php';
  try {
    if (navigator.sendBeacon) {
      navigator.sendBeacon(url, new Blob([payload.toString()], { type: 'application/x-www-form-urlencoded' }));
      return;
    }
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: payload.toString(),
      keepalive: true
    }).catch(() => {});
  } catch (e) {}
}

window.trackEventMetric = trackEventMetric;

document.addEventListener('click', (e) => {
  const trigger = e.target.closest('[data-track-event-click]');
  if (!trigger) return;
  trackEventMetric(trigger.dataset.trackEventClick, trigger.dataset.trackEventMetric || 'click');
});

// ─── Alert Utility ────────────────────────────────────────────────────────────
function showAlert(container, message, type = 'error') {
  const icons = { error: createInlineSvgIcon('x'), success: createInlineSvgIcon('check'), info: createInlineSvgIcon('info'), warning: createInlineSvgIcon('warning') };
  const el = document.createElement('div');
  el.className = `alert alert-${type} animate-in`;
  el.innerHTML = `<span class="ui-emoji-icon-wrap">${icons[type] || createInlineSvgIcon('info')}</span><span>${message}</span>`;
  container.insertBefore(el, container.firstChild);
  setTimeout(() => el.remove(), 5000);
}

// ─── Modal Control ───────────────────────────────────────────────────────────
function openModal(id) {
  const overlay = document.getElementById(id);
  if (!overlay) return;
  overlay.classList.add('open');
  overlay.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  const overlay = document.getElementById(id);
  if (!overlay) return;
  overlay.classList.remove('open');
  overlay.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
}

// Close modal when clicking overlay background
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// Close modal with Escape key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => {
      m.classList.remove('open');
      document.body.style.overflow = '';
    });
  }
});

// ─── Filter Tabs ─────────────────────────────────────────────────────────────
document.querySelectorAll('.filter-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    const group = tab.closest('.filter-tabs');
    group.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');

    // If filter tabs control card visibility
    const targetType = tab.dataset.filter;
    const cards = document.querySelectorAll('[data-type]');
    cards.forEach(card => {
      const show = targetType === 'all' || card.dataset.type === targetType;
      card.closest('.listing-card-wrap').style.display = show ? '' : 'none';
    });
  });
});

// ─── Search Bar ───────────────────────────────────────────────────────────────
const searchInput = document.getElementById('listing-search');
if (searchInput) {
  searchInput.addEventListener('input', () => {
    const q = searchInput.value.toLowerCase().trim();
    document.querySelectorAll('.listing-card-wrap').forEach(wrap => {
      const title = wrap.querySelector('.card-title')?.textContent?.toLowerCase() || '';
      const loc   = wrap.querySelector('.card-loc')?.textContent?.toLowerCase() || '';
      wrap.style.display = (!q || title.includes(q) || loc.includes(q)) ? '' : 'none';
    });
  });
}

// ─── Booking Modal ───────────────────────────────────────────────────────────
function openBookingModal(listingId, listingType, listingTitle, price, vipPrice = 0) {
  document.getElementById('bk-listing-id').value     = listingId;
  document.getElementById('bk-listing-type').value   = listingType;
  document.getElementById('bk-listing-title').value  = listingTitle;
  document.getElementById('bk-base-price').value     = price;
  document.getElementById('bk-modal-title').textContent = `Book: ${listingTitle}`;

  // Store standard/VIP prices inside dataset on the select element if present
  const ticketTypeSelect = document.getElementById('bk-ticket-type');
  if (ticketTypeSelect) {
    ticketTypeSelect.dataset.standardPrice = price;
    ticketTypeSelect.dataset.vipPrice = vipPrice;
    ticketTypeSelect.value = 'Standard'; // reset default

    const stdOption = ticketTypeSelect.querySelector('option[value="Standard"]');
    const vipOption = ticketTypeSelect.querySelector('option[value="VIP"]');
    if (stdOption) stdOption.textContent = `Standard — MK ${parseFloat(price).toLocaleString('en-MW')}`;
    if (vipOption) {
      if (parseFloat(vipPrice) > 0) {
        vipOption.style.display = '';
        vipOption.textContent = `VIP — MK ${parseFloat(vipPrice).toLocaleString('en-MW')}`;
      } else {
        vipOption.style.display = 'none';
      }
    }
  }

  // Show/hide type-specific fields
  const eventFields    = document.getElementById('bk-event-fields');
  const accomFields    = document.getElementById('bk-accom-fields');
  const tourFields     = document.getElementById('bk-tour-fields');
  const transportFields= document.getElementById('bk-transport-fields');

  [eventFields, accomFields, tourFields, transportFields].forEach(f => {
    if (f) f.style.display = 'none';
  });
  if (listingType === 'event' && eventFields)      eventFields.style.display = '';
  if (listingType === 'accommodation' && accomFields) accomFields.style.display = '';
  if (listingType === 'tour' && tourFields)         tourFields.style.display = '';
  if (listingType === 'transport' && transportFields) transportFields.style.display = '';

  updateBookingTotal();
  openModal('booking-modal');
}

function updateBookingTotal() {
  const basePrice = parseFloat(document.getElementById('bk-base-price')?.value || 0);
  const qty = parseInt(document.getElementById('bk-quantity')?.value || 1);
  const total = basePrice * qty;
  const totalEl = document.getElementById('bk-total');
  if (totalEl) {
    totalEl.textContent = 'MK ' + total.toLocaleString('en-MW');
  }
  const hiddenTotal = document.getElementById('bk-total-price');
  if (hiddenTotal) hiddenTotal.value = total;
}

// ─── Booking Form Submit ──────────────────────────────────────────────────────
const bookingForm = document.getElementById('booking-form');
if (bookingForm) {
  bookingForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = bookingForm.querySelector('[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Processing…';

    const formData = new FormData(bookingForm);
    formData.append('action', 'create_booking');

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) formData.append('csrf_token', csrf);

    try {
      const res = await fetch(BASE + 'request_api.php', { method: 'POST', body: formData });
      const data = await res.json();
      if (data.success) {
        closeModal('booking-modal');
        closeModal('payment-modal');
        showSuccessBooking(data.booking);
        // Reload page after 3s
        setTimeout(() => location.reload(), 3000);
      } else {
        showAlert(bookingForm, data.message || 'Booking failed. Please try again.');
      }
    } catch (err) {
      showAlert(bookingForm, 'Network error. Please try again.');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Confirm Booking';
    }
  });
}

// ─── Booking Success Flash ────────────────────────────────────────────────────
function showSuccessBooking(booking) {
  const el = document.getElementById('booking-success');
  if (!el) return;
  const ticketFormat = (booking.ticket_format || 'qr').toLowerCase();
  const baseUrl = document.querySelector('meta[name="base-url"]')?.content || '';
  const ticketUrl = `${baseUrl}ticket.php?id=${encodeURIComponent(booking.id || '')}`;
  el.querySelector('#success-booking-id').textContent  = booking.id || '';
  const formatEl = el.querySelector('#success-ticket-format');
  if (formatEl) formatEl.textContent = ticketFormat;
  el.querySelector('#success-total').textContent       = 'MK ' + parseFloat(booking.total_price || 0).toLocaleString('en-MW');

  const previewEl = el.querySelector('#success-ticket-preview');
  const thumbEl = el.querySelector('#success-ticket-thumb');
  const miniEl = el.querySelector('#success-ticket-mini');
  const codeEl = el.querySelector('#success-qr-code');
  const code = booking.qr_code || '';
  if (previewEl) {
    if (miniEl) {
      miniEl.textContent = `${ticketFormat.toUpperCase()} ticket ready`;
    }
    if (ticketFormat === 'barcode' && window.JsBarcode) {
      if (thumbEl) {
        thumbEl.innerHTML = '<svg id="success-barcode" style="width:100%;height:100%;background:#fff;"></svg>';
      }
      try {
        window.JsBarcode('#success-barcode', code, {
          format: 'CODE128',
          lineColor: '#111827',
          background: '#ffffff',
          width: 1.1,
          height: 42,
          margin: 4,
          displayValue: true,
          fontSize: 10
        });
      } catch (err) {
        if (thumbEl) thumbEl.innerHTML = '<div style="font-size:0.7rem;padding:0.25rem;text-align:center;">Barcode unavailable</div>';
      }
    } else if (ticketFormat === 'code') {
      if (thumbEl) thumbEl.innerHTML = '<div style="padding:0.25rem 0.4rem;font-family:monospace;font-size:0.8rem;font-weight:800;letter-spacing:0.14em;word-break:break-all;text-align:center;">' + code + '</div>';
    } else {
      if (thumbEl) thumbEl.innerHTML = `<img src="https://chart.googleapis.com/chart?chs=100x100&cht=qr&chl=${encodeURIComponent(code)}&choe=UTF-8" alt="Ticket QR" style="width:100%;height:100%;object-fit:contain;background:#fff;">`;
    }
  }

  if (codeEl) {
    codeEl.textContent = code;
  }
  if (codeEl && ticketFormat !== 'qr') {
    codeEl.textContent = code;
  }

  const printBtn = el.querySelector('#success-ticket-print');
  const shareBtn = el.querySelector('#success-ticket-share');
  const copyBtn = el.querySelector('#success-ticket-copy');

  if (printBtn) {
    printBtn.onclick = () => window.open(ticketUrl, '_blank', 'noopener');
  }
  if (shareBtn) {
    shareBtn.onclick = async () => {
      const shareData = {
        title: 'Uthenga Ticket',
        text: `Your ${ticketFormat.toUpperCase()} ticket is ready. Code: ${code}`,
        url: ticketUrl
      };
      try {
        if (navigator.share) {
          await navigator.share(shareData);
        } else if (navigator.clipboard) {
          await navigator.clipboard.writeText(`${shareData.text} ${ticketUrl}`);
        }
      } catch (err) {
        console.log(err);
      }
    };
  }
  if (copyBtn) {
    copyBtn.onclick = async () => {
      try {
        if (navigator.clipboard) {
          await navigator.clipboard.writeText(code || booking.id || '');
        }
      } catch (err) {
        console.log(err);
      }
    };
  }
  el.style.display = 'block';
  el.scrollIntoView({ behavior: 'smooth' });
}

// ─── Cancel Booking ───────────────────────────────────────────────────────────
document.querySelectorAll('.btn-cancel-booking').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.bookingId;
    if (!confirm(`Cancel booking ${id}? This cannot be undone.`)) return;
    const data = await api('cancel_booking', { booking_id: id });
    if (data.success) {
      btn.closest('tr')?.remove();
      alert('Booking cancelled successfully.');
    } else {
      alert(data.message || 'Could not cancel booking.');
    }
  });
});

// ─── Admin: Status Update ─────────────────────────────────────────────────────
document.querySelectorAll('.admin-status-select').forEach(sel => {
  sel.addEventListener('change', async () => {
    const { bookingId, field } = sel.dataset;
    const data = await api('admin_update_booking', {
      booking_id: bookingId,
      field: field,
      value: sel.value
    });
    if (!data.success) {
      alert(data.message || 'Update failed.');
      sel.value = sel.dataset.original || '';
    }
  });
});

// ─── Admin: Toggle User Status ────────────────────────────────────────────────
document.querySelectorAll('.btn-toggle-user').forEach(btn => {
  btn.addEventListener('click', async () => {
    const userId = btn.dataset.userId;
    const data = await api('toggle_user_status', { user_id: userId });
    if (data.success) {
      location.reload();
    } else {
      alert(data.message || 'Action failed.');
    }
  });
});

// ─── Admin: Refund ────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-refund').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.bookingId;
    if (!confirm(`Issue full refund for booking ${id}?`)) return;
    const data = await api('refund_booking', { booking_id: id });
    if (data.success) {
      location.reload();
    } else {
      alert(data.message || 'Refund failed.');
    }
  });
});

// ─── Coupon Validation ────────────────────────────────────────────────────────
const couponInput = document.getElementById('coupon-code');
const couponBtn   = document.getElementById('apply-coupon');
if (couponBtn && couponInput) {
  couponBtn.addEventListener('click', async () => {
    const code = couponInput.value.trim().toUpperCase();
    if (!code) return;
    const data = await api('validate_coupon', { code, spend: document.getElementById('bk-total-price')?.value || 0 });
    const msg = document.getElementById('coupon-msg');
    if (data.valid) {
      if (msg) { msg.textContent = `✓ Coupon applied! ${data.description}`; msg.className = 'text-sm text-green'; }
      const el = document.getElementById('bk-discount');
      if (el) el.value = data.discount;
      updateBookingTotal();
    } else {
      if (msg) { msg.textContent = data.message || 'Invalid coupon.'; msg.className = 'text-sm text-red'; }
    }
  });
}

// ─── Quantity change ──────────────────────────────────────────────────────────
const qtyInput = document.getElementById('bk-quantity');
if (qtyInput) qtyInput.addEventListener('input', updateBookingTotal);

// ─── Ticket Type change (Standard vs VIP) ──────────────────────────────────────
const ticketTypeSelect = document.getElementById('bk-ticket-type');
if (ticketTypeSelect) {
  ticketTypeSelect.addEventListener('change', () => {
    const type = ticketTypeSelect.value;
    const stdPrice = parseFloat(ticketTypeSelect.dataset.standardPrice || 0);
    const vipPrice = parseFloat(ticketTypeSelect.dataset.vipPrice || 0);
    const basePrice = (type === 'VIP') ? vipPrice : stdPrice;
    
    const basePriceInput = document.getElementById('bk-base-price');
    if (basePriceInput) basePriceInput.value = basePrice;
    updateBookingTotal();
  });
}

// ─── Gateway Selection ────────────────────────────────────────────────────────
document.querySelectorAll('.gateway-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.gateway-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    const gw = document.getElementById('bk-gateway');
    if (gw) gw.value = btn.dataset.gateway;
  });
});

// ─── Confirm Payment Step ─────────────────────────────────────────────────────
const proceedPayBtn = document.getElementById('proceed-to-payment');
if (proceedPayBtn) {
  proceedPayBtn.addEventListener('click', () => {
    const gw = document.getElementById('bk-gateway')?.value;
    if (!gw) { alert('Please select a payment method.'); return; }
    closeModal('booking-modal');
    openModal('payment-modal');
    // Update summary in payment modal
    const title = document.getElementById('bk-listing-title')?.value;
    const total = document.getElementById('bk-total-price')?.value;
    const pmTitle = document.getElementById('pm-title');
    const pmTotal = document.getElementById('pm-total');
    const pmGw    = document.getElementById('pm-gateway');
    if (pmTitle) pmTitle.textContent = title;
    if (pmTotal) pmTotal.textContent = 'MK ' + parseFloat(total || 0).toLocaleString('en-MW');
    if (pmGw)    pmGw.textContent    = gw;
  });
}

// ─── Table sort (basic) ───────────────────────────────────────────────────────
document.querySelectorAll('th[data-sort]').forEach(th => {
  th.style.cursor = 'pointer';
  th.addEventListener('click', () => {
    const table = th.closest('table');
    const tbody = table.querySelector('tbody');
    const col = Array.from(th.parentElement.children).indexOf(th);
    const asc = th.dataset.sortDir !== 'asc';
    th.dataset.sortDir = asc ? 'asc' : 'desc';
    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
      const va = a.children[col]?.textContent?.trim() || '';
      const vb = b.children[col]?.textContent?.trim() || '';
      return asc ? va.localeCompare(vb, undefined, { numeric: true }) : vb.localeCompare(va, undefined, { numeric: true });
    });
    rows.forEach(r => tbody.appendChild(r));
  });
});

// ─── Animate cards on scroll ──────────────────────────────────────────────────
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('animate-in');
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.card, .stat-card').forEach(el => observer.observe(el));

console.log('%cUthenga Marketplace', 'color:#f59e0b;font-size:1.5rem;font-weight:bold;');
console.log('%cMalawi\'s Premier Marketplace Platform', 'color:#7c7c9a;font-size:0.9rem;');

// â”€â”€â”€ Theme Persistence â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
(function () {
  const button = document.querySelector('[data-theme-toggle]');
  const root = document.documentElement;
  const metaTheme = document.querySelector('meta[name="theme-color"]');
  if (!button || !root) return;

  const palette = {
    dark: '#0a0a0f',
    light: '#f7f9fc',
  };

  function applyTheme(theme) {
    const next = theme === 'light' ? 'light' : 'dark';
    root.dataset.theme = next;
    root.style.colorScheme = next;
    const label = button.querySelector('.theme-toggle-label');
    if (label) label.textContent = next === 'dark' ? 'Light' : 'Dark';
    button.setAttribute('aria-pressed', next === 'light' ? 'true' : 'false');
    button.setAttribute('aria-label', next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    button.title = next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
    if (metaTheme) metaTheme.setAttribute('content', palette[next]);
  }

  let stored = null;
  try {
    stored = localStorage.getItem('uthenga-theme');
  } catch (e) {}

  const initial = root.dataset.theme || stored || 'light';
  applyTheme(initial);

  button.addEventListener('click', () => {
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try {
      localStorage.setItem('uthenga-theme', next);
    } catch (e) {}
    applyTheme(next);
  });
})();

// ─── IntersectionObserver for Progressive Image Lazy Loading ─────────────────
(function () {
  'use strict';
  const lazyImages = document.querySelectorAll('img[data-src]');
  if (!lazyImages.length) return;

  if ('IntersectionObserver' in window) {
    const imgObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          img.src = img.dataset.src;
          img.removeAttribute('data-src');
          imgObserver.unobserve(img);
        }
      });
    });
    lazyImages.forEach(img => imgObserver.observe(img));
  } else {
    lazyImages.forEach(img => {
      img.src = img.dataset.src;
      img.removeAttribute('data-src');
    });
  }
})();
