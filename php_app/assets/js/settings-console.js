/* Uthenga — Settings Control Center (System -> Settings)
 * Enterprise Configuration Center for Uthenga Events.
 * Implements a clean two-column architecture for managing business profile,
 * event defaults, ticketing, check-in, communications, financial policies, security,
 * staff access, integrations, data privacy, and system advanced controls.
 */
window.SettingsControlCenter = (function () {
  'use strict';

  var rootEl = null;
  var apiEndpoint = '';

  var state = {
    booted: false,
    loading: false,
    saving: false,
    isDirty: false,
    activeSection: 'business_profile',
    searchQuery: '',
    data: {}
  };

  function esc(s) {
    return window.tkEsc ? tkEsc(s) : String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function getBaseUrl() {
    var workspace = document.getElementById('events-workspace');
    return workspace ? (workspace.dataset.baseUrl || '') : '';
  }

  function getCsrfToken() {
    var workspace = document.getElementById('events-workspace');
    return workspace ? (workspace.dataset.csrf || '') : '';
  }

  function markDirty() {
    if (!state.isDirty) {
      state.isDirty = true;
      updateHeaderButtons();
    }
  }

  function clearDirty() {
    state.isDirty = false;
    updateHeaderButtons();
  }

  function updateHeaderButtons() {
    var pill = document.getElementById('stg-unsaved-pill');
    var discardBtn = document.getElementById('stg-btn-discard');
    var saveBtn = document.getElementById('stg-btn-save');

    if (pill) pill.style.display = state.isDirty ? 'inline-flex' : 'none';
    if (discardBtn) discardBtn.disabled = !state.isDirty;
    if (saveBtn) {
      saveBtn.classList.toggle('stg-highlight-save', state.isDirty);
    }
  }

  /* ── Navigation Items Mapping ── */
  var NAV_SECTIONS = [
    {
      group: 'GENERAL',
      items: [
        { id: 'business_profile', label: 'Business Profile', icon: 'building' },
        { id: 'preferences', label: 'Preferences & Public Identity', icon: 'sliders' }
      ]
    },
    {
      group: 'OPERATIONS',
      items: [
        { id: 'event_defaults', label: 'Event Defaults & Publishing', icon: 'calendar' },
        { id: 'ticketing', label: 'Ticketing & Delivery', icon: 'ticket' },
        { id: 'checkin', label: 'Check-In & Overrides', icon: 'check-circle' },
        { id: 'customer_exp', label: 'Customer Experience', icon: 'users' }
      ]
    },
    {
      group: 'COMMUNICATIONS',
      items: [
        { id: 'notifications', label: 'Notifications & Alerts', icon: 'bell' },
        { id: 'templates', label: 'Message Templates', icon: 'mail' }
      ]
    },
    {
      group: 'FINANCIAL',
      items: [
        { id: 'payments', label: 'Payments & Settlements', icon: 'credit-card' },
        { id: 'fees_policies', label: 'Refund & Cancellation Policies', icon: 'shield' }
      ]
    },
    {
      group: 'ACCESS & SECURITY',
      items: [
        { id: 'security', label: 'Security & Authentication', icon: 'lock' },
        { id: 'staff_access', label: 'Staff Access Policies', icon: 'user-check' }
      ]
    },
    {
      group: 'INTEGRATIONS',
      items: [
        { id: 'integrations', label: 'Connected Services & Webhooks', icon: 'code' }
      ]
    },
    {
      group: 'DATA & PRIVACY',
      items: [
        { id: 'data_privacy', label: 'Documents & Privacy', icon: 'database' }
      ]
    },
    {
      group: 'ADVANCED',
      items: [
        { id: 'advanced', label: 'Advanced & Danger Zone', icon: 'alert-triangle' }
      ]
    }
  ];

  /* ── API Data Fetch & Save ── */
  function fetchSettings() {
    state.loading = true;
    render();

    var url = apiEndpoint;
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        state.loading = false;
        if (res && res.success && res.settings) {
          state.data = res.settings;
        }
        state.booted = true;
        render();
      })
      .catch(function(err) {
        state.loading = false;
        console.warn('[Settings] Error fetching settings:', err);
        render();
      });
  }

  function saveAllSettings() {
    if (state.saving) return;
    state.saving = true;
    updateHeaderButtons();

    var payload = gatherFormData();
    payload.csrf_token = getCsrfToken();

    fetch(apiEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'fetch',
        'X-CSRF-Token': getCsrfToken()
      },
      body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      state.saving = false;
      if (res && res.success && res.settings) {
        state.data = res.settings;
        clearDirty();
        if (window.eccNotify) eccNotify('Settings saved successfully!');
      } else {
        if (window.eccNotify) eccNotify('Failed to save settings: ' + (res.error ? res.error.message : 'Unknown error'), 'error');
      }
      render();
    })
    .catch(function(err) {
      state.saving = false;
      console.error('[Settings] Save error:', err);
      if (window.eccNotify) eccNotify('Failed to save settings.', 'error');
      render();
    });
  }

  /* ── Gather Data from Form Controls ── */
  function gatherFormData() {
    if (!rootEl) return state.data;

    var gen = state.data.general || {};
    var ops = state.data.operations || {};
    var com = state.data.communications || {};
    var fin = state.data.financial || {};
    var sec = state.data.security || {};
    var dat = state.data.data_privacy || {};

    // Helper to get val
    function v(id, fallback) {
      var el = rootEl.querySelector('#' + id);
      return el ? el.value : fallback;
    }
    function c(id, fallback) {
      var el = rootEl.querySelector('#' + id);
      return el ? el.checked : fallback;
    }

    gen.business_name = v('stg-biz-name', gen.business_name);
    gen.business_type = v('stg-biz-type', gen.business_type);
    gen.description = v('stg-biz-desc', gen.description);
    gen.phone = v('stg-biz-phone', gen.phone);
    gen.email = v('stg-biz-email', gen.email);
    gen.website = v('stg-biz-website', gen.website);
    gen.address = v('stg-biz-address', gen.address);
    gen.city = v('stg-biz-city', gen.city);
    gen.country = v('stg-biz-country', gen.country);
    gen.currency = v('stg-biz-currency', gen.currency);
    gen.timezone = v('stg-biz-timezone', gen.timezone);
    gen.language = v('stg-pref-lang', gen.language);
    gen.date_format = v('stg-pref-datefmt', gen.date_format);
    gen.time_format = v('stg-pref-timefmt', gen.time_format);

    gen.show_display_name = c('stg-pub-name', gen.show_display_name);
    gen.show_logo = c('stg-pub-logo', gen.show_logo);
    gen.show_description = c('stg-pub-desc', gen.show_description);
    gen.show_phone = c('stg-pub-phone', gen.show_phone);
    gen.show_email = c('stg-pub-email', gen.show_email);

    ops.default_visibility = v('stg-op-vis', ops.default_visibility);
    ops.default_status = v('stg-op-status', ops.default_status);
    ops.default_duration_hours = parseInt(v('stg-op-dur', ops.default_duration_hours), 10);
    ops.require_description = c('stg-op-reqdesc', ops.require_description);
    ops.require_cover_image = c('stg-op-reqimg', ops.require_cover_image);
    ops.require_venue = c('stg-op-reqvnu', ops.require_venue);

    ops.ticket_id_prefix = v('stg-tk-prefix', ops.ticket_id_prefix);
    ops.ticket_id_format = v('stg-tk-format', ops.ticket_id_format);
    ops.allow_ticket_transfers = c('stg-tk-transfer', ops.allow_ticket_transfers);
    ops.allow_cancellations = c('stg-tk-cancel', ops.allow_cancellations);
    ops.enable_digital_tickets = c('stg-tk-digital', ops.enable_digital_tickets);
    ops.enable_printable_tickets = c('stg-tk-printable', ops.enable_printable_tickets);

    ops.checkin_duplicate_action = v('stg-ci-dup', ops.checkin_duplicate_action);
    ops.checkin_require_verification = c('stg-ci-reqver', ops.checkin_require_verification);
    ops.checkin_manual_lookup = c('stg-ci-lookup', ops.checkin_manual_lookup);
    ops.checkin_name_lookup = c('stg-ci-namelookup', ops.checkin_name_lookup);
    ops.checkin_offline_mode = c('stg-ci-offline', ops.checkin_offline_mode);

    fin.settlement_schedule = v('stg-fin-schedule', fin.settlement_schedule);
    fin.settlement_day = v('stg-fin-day', fin.settlement_day);
    fin.allow_refunds = c('stg-fin-allowrefund', fin.allow_refunds);
    fin.refund_window_hours = parseInt(v('stg-fin-refwindow', fin.refund_window_hours), 10);
    fin.refund_approval_mode = v('stg-fin-refapproval', fin.refund_approval_mode);

    sec.session_timeout_minutes = parseInt(v('stg-sec-timeout', sec.session_timeout_minutes), 10);
    sec.mfa_mode = v('stg-sec-mfamode', sec.mfa_mode);

    dat.doc_retention_years = parseInt(v('stg-dat-retention', dat.doc_retention_years), 10);
    dat.show_cust_phone_to_staff = c('stg-dat-custphone', dat.show_cust_phone_to_staff);
    dat.show_cust_email_to_staff = c('stg-dat-custemail', dat.show_cust_email_to_staff);

    return {
      general: gen,
      operations: ops,
      communications: com,
      financial: fin,
      security: sec,
      data_privacy: dat
    };
  }

  /* ── Main Layout Render ── */
  function render() {
    if (!rootEl) rootEl = document.getElementById('mod-settings');
    if (!rootEl) return;

    var gen = state.data.general || {};
    var ops = state.data.operations || {};
    var com = state.data.communications || {};
    var fin = state.data.financial || {};
    var sec = state.data.security || {};
    var intg = state.data.integrations || {};
    var dat = state.data.data_privacy || {};
    var adv = state.data.advanced || {};

    var q = state.searchQuery.toLowerCase().trim();

    rootEl.innerHTML =
      '<div class="stg-root">' +

        /* Header Bar */
        '<div class="stg-header-bar">' +
          '<div class="stg-header-title-wrap">' +
            '<h1 class="stg-title">Settings</h1>' +
            '<p class="stg-subtitle">Configure your Events business, security, policies, and integrations.</p>' +
          '</div>' +
          '<div class="stg-header-controls">' +
            '<div class="stg-search-box">' +
              '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
              '<input type="text" id="stg-search-input" class="stg-search-input" placeholder="🔍 Search settings..." value="' + esc(state.searchQuery) + '" oninput="SettingsControlCenter.onSearch(this.value)">' +
            '</div>' +
            '<span class="stg-unsaved-pill" id="stg-unsaved-pill" style="display:' + (state.isDirty ? 'inline-flex' : 'none') + ';">● Unsaved changes</span>' +
            '<button type="button" class="stg-btn stg-btn-ghost" id="stg-btn-discard" onclick="SettingsControlCenter.discard()" ' + (!state.isDirty ? 'disabled' : '') + '>Discard</button>' +
            '<button type="button" class="stg-btn stg-btn-primary ' + (state.isDirty ? 'stg-highlight-save' : '') + '" id="stg-btn-save" onclick="SettingsControlCenter.save()">' +
              (state.saving ? 'Saving...' : 'Save Changes') +
            '</button>' +
          '</div>' +
        '</div>' +

        /* Two-Column Workspace Layout */
        '<div class="stg-layout-grid">' +

          /* Left Sidebar Sub-Nav */
          '<div class="stg-nav-col">' +
            renderNav(q) +
          '</div>' +

          /* Right Section Content */
          '<div class="stg-content-col">' +
            renderSectionContent(gen, ops, com, fin, sec, intg, dat, adv) +
          '</div>' +

        '</div>' +

      '</div>';

    // Attach form dirty tracking handlers
    var inputs = rootEl.querySelectorAll('input, select, textarea');
    inputs.forEach(function(el) {
      if (el.id !== 'stg-search-input') {
        el.addEventListener('change', markDirty);
        el.addEventListener('input', markDirty);
      }
    });
  }

  function renderNav(query) {
    return NAV_SECTIONS.map(function(grp) {
      var matchingItems = grp.items.filter(function(it) {
        if (!query) return true;
        return it.label.toLowerCase().indexOf(query) !== -1 || grp.group.toLowerCase().indexOf(query) !== -1;
      });

      if (!matchingItems.length) return '';

      var itemHtml = matchingItems.map(function(it) {
        var active = state.activeSection === it.id ? 'active' : '';
        return '<button type="button" class="stg-nav-item ' + active + '" onclick="SettingsControlCenter.selectSection(\'' + it.id + '\')">' +
          '<span>' + esc(it.label) + '</span>' +
        '</button>';
      }).join('');

      return '<div class="stg-nav-group">' +
        '<div class="stg-nav-group-label">' + esc(grp.group) + '</div>' +
        itemHtml +
      '</div>';
    }).join('');
  }

  function renderSectionContent(gen, ops, com, fin, sec, intg, dat, adv) {
    var secId = state.activeSection;

    switch (secId) {
      case 'business_profile': return renderBusinessProfile(gen);
      case 'preferences':      return renderPreferences(gen);
      case 'event_defaults':  return renderEventDefaults(ops);
      case 'ticketing':       return renderTicketing(ops);
      case 'checkin':         return renderCheckin(ops);
      case 'customer_exp':    return renderCustomerExp(ops);
      case 'notifications':   return renderNotifications(com);
      case 'templates':       return renderTemplates(com);
      case 'payments':        return renderPayments(fin);
      case 'fees_policies':   return renderFeesPolicies(fin);
      case 'security':        return renderSecurity(sec);
      case 'staff_access':    return renderStaffAccess(sec);
      case 'integrations':    return renderIntegrations(intg);
      case 'data_privacy':    return renderDataPrivacy(dat);
      case 'advanced':        return renderAdvanced(adv);
      default:                return renderBusinessProfile(gen);
    }
  }

  /* ── 1. Business Profile ── */
  function renderBusinessProfile(gen) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Business Profile</div>' +
      '<div class="stg-card-sub">Configure how your event organizer business appears inside Uthenga and on tickets.</div>' +

      '<div class="stg-form-grid">' +
        '<div class="stg-form-field full">' +
          '<label>Business Name</label>' +
          '<input type="text" class="stg-input" id="stg-biz-name" value="' + esc(gen.business_name) + '">' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Business Type</label>' +
          '<select class="stg-select" id="stg-biz-type">' +
            '<option value="Event Organizer" ' + (gen.business_type === 'Event Organizer' ? 'selected' : '') + '>Event Organizer</option>' +
            '<option value="Promoter / Producer" ' + (gen.business_type === 'Promoter / Producer' ? 'selected' : '') + '>Promoter / Producer</option>' +
            '<option value="Venue Management" ' + (gen.business_type === 'Venue Management' ? 'selected' : '') + '>Venue Management</option>' +
            '<option value="Corporate / Non-Profit" ' + (gen.business_type === 'Corporate / Non-Profit' ? 'selected' : '') + '>Corporate / Non-Profit</option>' +
          '</select>' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Official Email</label>' +
          '<input type="email" class="stg-input" id="stg-biz-email" value="' + esc(gen.email) + '">' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Phone Number</label>' +
          '<input type="text" class="stg-input" id="stg-biz-phone" value="' + esc(gen.phone) + '">' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Website</label>' +
          '<input type="url" class="stg-input" id="stg-biz-website" value="' + esc(gen.website) + '">' +
        '</div>' +

        '<div class="stg-form-field full">' +
          '<label>Physical Address</label>' +
          '<input type="text" class="stg-input" id="stg-biz-address" value="' + esc(gen.address) + '">' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>City</label>' +
          '<input type="text" class="stg-input" id="stg-biz-city" value="' + esc(gen.city || 'Lilongwe') + '">' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Country</label>' +
          '<select class="stg-select" id="stg-biz-country">' +
            '<option value="Malawi" ' + (gen.country === 'Malawi' ? 'selected' : '') + '>Malawi</option>' +
            '<option value="Zambia" ' + (gen.country === 'Zambia' ? 'selected' : '') + '>Zambia</option>' +
            '<option value="Tanzania" ' + (gen.country === 'Tanzania' ? 'selected' : '') + '>Tanzania</option>' +
            '<option value="Mozambique" ' + (gen.country === 'Mozambique' ? 'selected' : '') + '>Mozambique</option>' +
          '</select>' +
        '</div>' +

        '<div class="stg-form-field full">' +
          '<label>Organizer Overview / Description</label>' +
          '<textarea class="stg-textarea" id="stg-biz-desc" rows="3">' + esc(gen.description) + '</textarea>' +
        '</div>' +
      '</div>' +

      /* Public Profile Preview */
      '<div class="stg-divider"></div>' +
      '<div class="stg-card-title">Public Organizer Identity</div>' +
      '<div class="stg-card-sub">Choose which details are displayed on customer event pages, tickets, and receipts.</div>' +

      '<div class="stg-toggle-list">' +
        toggleRow('stg-pub-name', 'Display Business Name', 'Show your official organization name on customer tickets', gen.show_display_name) +
        toggleRow('stg-pub-logo', 'Show Business Logo', 'Embed your logo on PDF receipts and digital passes', gen.show_logo) +
        toggleRow('stg-pub-desc', 'Show Description', 'Include organizer background in public event listings', gen.show_description) +
        toggleRow('stg-pub-phone', 'Show Contact Phone', 'Allow customers to reach your support line directly', gen.show_phone) +
        toggleRow('stg-pub-email', 'Show Contact Email', 'Include support email on ticket confirmation messages', gen.show_email) +
      '</div>' +

    '</div>';
  }

  /* ── 2. Preferences ── */
  function renderPreferences(gen) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Operational Preferences</div>' +
      '<div class="stg-card-sub">Configure system defaults for timezone, currency formats, and dashboard display.</div>' +

      '<div class="stg-form-grid">' +
        '<div class="stg-form-field">' +
          '<label>Default Currency</label>' +
          '<select class="stg-select" id="stg-biz-currency">' +
            '<option value="MWK" ' + (gen.currency === 'MWK' ? 'selected' : '') + '>MWK — Malawi Kwacha</option>' +
            '<option value="USD" ' + (gen.currency === 'USD' ? 'selected' : '') + '>USD — US Dollar</option>' +
            '<option value="ZMW" ' + (gen.currency === 'ZMW' ? 'selected' : '') + '>ZMW — Zambian Kwacha</option>' +
          '</select>' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Timezone</label>' +
          '<select class="stg-select" id="stg-biz-timezone">' +
            '<option value="Africa/Blantyre" ' + (gen.timezone === 'Africa/Blantyre' ? 'selected' : '') + '>Africa/Blantyre (GMT+2)</option>' +
            '<option value="Africa/Johannesburg" ' + (gen.timezone === 'Africa/Johannesburg' ? 'selected' : '') + '>Africa/Johannesburg (GMT+2)</option>' +
            '<option value="UTC" ' + (gen.timezone === 'UTC' ? 'selected' : '') + '>UTC (GMT+0)</option>' +
          '</select>' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>System Language</label>' +
          '<select class="stg-select" id="stg-pref-lang">' +
            '<option value="English" ' + (gen.language === 'English' ? 'selected' : '') + '>English</option>' +
            '<option value="Chichewa" ' + (gen.language === 'Chichewa' ? 'selected' : '') + '>Chichewa</option>' +
          '</select>' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Date Format</label>' +
          '<select class="stg-select" id="stg-pref-datefmt">' +
            '<option value="DD/MM/YYYY" ' + (gen.date_format === 'DD/MM/YYYY' ? 'selected' : '') + '>DD/MM/YYYY (22/08/2026)</option>' +
            '<option value="YYYY-MM-DD" ' + (gen.date_format === 'YYYY-MM-DD' ? 'selected' : '') + '>YYYY-MM-DD (2026-08-22)</option>' +
            '<option value="MMM DD, YYYY" ' + (gen.date_format === 'MMM DD, YYYY' ? 'selected' : '') + '>MMM DD, YYYY (Aug 22, 2026)</option>' +
          '</select>' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Time Format</label>' +
          '<select class="stg-select" id="stg-pref-timefmt">' +
            '<option value="24-hour" ' + (gen.time_format === '24-hour' ? 'selected' : '') + '>24-hour (18:00)</option>' +
            '<option value="12-hour" ' + (gen.time_format === '12-hour' ? 'selected' : '') + '>12-hour (06:00 PM)</option>' +
          '</select>' +
        '</div>' +
      '</div>' +

    '</div>';
  }

  /* ── 3. Event Defaults ── */
  function renderEventDefaults(ops) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Event Defaults & Publishing Rules</div>' +
      '<div class="stg-card-sub">Set standard defaults for newly created events to streamline workflow.</div>' +

      '<div class="stg-form-grid">' +
        '<div class="stg-form-field">' +
          '<label>Default Event Visibility</label>' +
          '<select class="stg-select" id="stg-op-vis">' +
            '<option value="Public" ' + (ops.default_visibility === 'Public' ? 'selected' : '') + '>Public — Visible on Uthenga Marketplace</option>' +
            '<option value="Private" ' + (ops.default_visibility === 'Private' ? 'selected' : '') + '>Private — Accessible via direct link only</option>' +
            '<option value="Draft" ' + (ops.default_visibility === 'Draft' ? 'selected' : '') + '>Draft — Hidden until published</option>' +
          '</select>' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Default Status</label>' +
          '<select class="stg-select" id="stg-op-status">' +
            '<option value="Draft" ' + (ops.default_status === 'Draft' ? 'selected' : '') + '>Draft</option>' +
            '<option value="Published" ' + (ops.default_status === 'Published' ? 'selected' : '') + '>Published</option>' +
          '</select>' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Default Event Duration (Hours)</label>' +
          '<input type="number" class="stg-input" id="stg-op-dur" min="1" max="72" value="' + (ops.default_duration_hours || 4) + '">' +
        '</div>' +
      '</div>' +

      '<div class="stg-divider"></div>' +
      '<div class="stg-card-title">Publishing Quality Controls</div>' +

      '<div class="stg-toggle-list">' +
        toggleRow('stg-op-reqdesc', 'Require Event Description', 'Enforce rich description text before an event can be published', ops.require_description) +
        toggleRow('stg-op-reqimg', 'Require Cover Image', 'Require high-resolution poster or banner photo', ops.require_cover_image) +
        toggleRow('stg-op-reqvnu', 'Require Venue Information', 'Require valid physical or virtual venue details', ops.require_venue) +
      '</div>' +

    '</div>';
  }

  /* ── 4. Ticketing Defaults ── */
  function renderTicketing(ops) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Ticketing & ID Generation</div>' +
      '<div class="stg-card-sub">Configure ticket number format, digital passes, and delivery methods.</div>' +

      '<div class="stg-form-grid">' +
        '<div class="stg-form-field">' +
          '<label>Ticket ID Prefix</label>' +
          '<input type="text" class="stg-input" id="stg-tk-prefix" value="' + esc(ops.ticket_id_prefix || 'UTH') + '">' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Ticket Number Pattern</label>' +
          '<input type="text" class="stg-input" id="stg-tk-format" value="' + esc(ops.ticket_id_format || 'UTH-{YEAR}-{RANDOM}') + '">' +
        '</div>' +
      '</div>' +

      '<div class="stg-divider"></div>' +
      '<div class="stg-card-title">Ticket Operations & Delivery</div>' +

      '<div class="stg-toggle-list">' +
        toggleRow('stg-tk-transfer', 'Allow Ticket Transfers', 'Let attendees transfer tickets to secondary attendees', ops.allow_ticket_transfers) +
        toggleRow('stg-tk-cancel', 'Allow Customer Cancellations', 'Allow customer self-service cancellation within policy window', ops.allow_cancellations) +
        toggleRow('stg-tk-digital', 'Enable Digital Pass (QR Code)', 'Generate encrypted dynamic QR code on attendee mobile pass', ops.enable_digital_tickets) +
        toggleRow('stg-tk-printable', 'Enable PDF Printable Ticket', 'Provide downloadable PDF ticket with printable barcode', ops.enable_printable_tickets) +
      '</div>' +

    '</div>';
  }

  /* ── 5. Check-In Behavior ── */
  function renderCheckin(ops) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Check-In LIVE Behavior & Policies</div>' +
      '<div class="stg-card-sub">Define how gate scanners, duplicate scans, and manual overrides behave.</div>' +

      '<div class="stg-form-grid">' +
        '<div class="stg-form-field full">' +
          '<label>Duplicate Scan Action</label>' +
          '<select class="stg-select" id="stg-ci-dup">' +
            '<option value="warn" ' + (ops.checkin_duplicate_action === 'warn' ? 'selected' : '') + '>Show Warning (Alert gate staff but allow review)</option>' +
            '<option value="block" ' + (ops.checkin_duplicate_action === 'block' ? 'selected' : '') + '>Block Completely (Deny entry automatically)</option>' +
            '<option value="supervisor" ' + (ops.checkin_duplicate_action === 'supervisor' ? 'selected' : '') + '>Require Supervisor Override (PIN required)</option>' +
          '</select>' +
        '</div>' +
      '</div>' +

      '<div class="stg-toggle-list">' +
        toggleRow('stg-ci-reqver', 'Require Ticket Verification', 'Verify ticket integrity against server cryptographic keys', ops.checkin_require_verification) +
        toggleRow('stg-ci-lookup', 'Allow Manual Ticket ID Lookup', 'Let gate staff type 6-digit reference code if camera fails', ops.checkin_manual_lookup) +
        toggleRow('stg-ci-namelookup', 'Allow Attendee Name Search', 'Permit gate staff to search attendee list by name/phone', ops.checkin_name_lookup) +
        toggleRow('stg-ci-offline', 'Enable Offline Scanner Mode', 'Cache valid ticket keys locally for low-connectivity venues', ops.checkin_offline_mode) +
      '</div>' +

    '</div>';
  }

  /* ── 6. Customer Experience ── */
  function renderCustomerExp(ops) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Customer Experience</div>' +
      '<div class="stg-card-sub">Control customer-facing interactive features on event listing pages.</div>' +

      '<div class="stg-toggle-list">' +
        toggleRow('stg-cx-save', 'Allow Customers to Save Events', 'Show bookmark/favorite button on event cards', ops.cx_allow_save !== false) +
        toggleRow('stg-cx-share', 'Enable Social Sharing', 'Allow one-click WhatsApp, Facebook and X sharing links', ops.cx_allow_sharing !== false) +
        toggleRow('stg-cx-reviews', 'Enable Verified Attendee Reviews', 'Allow verified buyers to post post-event ratings and reviews', ops.cx_allow_reviews !== false) +
        toggleRow('stg-cx-avail', 'Show Remaining Ticket Count', 'Display low-stock availability indicators (e.g., "Only 15 left")', ops.cx_show_availability !== false) +
      '</div>' +

    '</div>';
  }

  /* ── 7. Notifications ── */
  function renderNotifications(com) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Customer Notification Channels</div>' +
      '<div class="stg-card-sub">Automated operational messages dispatched to attendees.</div>' +

      '<div class="stg-toggle-list">' +
        toggleRow('stg-notif-purchase', 'Ticket Purchase Confirmation', 'Send instant receipt and QR code upon successful payment', com.cust_purchase_conf !== false) +
        toggleRow('stg-notif-reminder', 'Event Reminders', 'Send automated reminder 24 hours prior to event start time', com.cust_reminder !== false) +
        toggleRow('stg-notif-changes', 'Event Schedule / Venue Changes', 'Notify ticket holders immediately if event time or venue changes', com.cust_event_changes !== false) +
        toggleRow('stg-notif-refund', 'Refund Confirmations', 'Send refund settlement receipts to customer email', com.cust_refund_conf !== false) +
      '</div>' +

      '<div class="stg-divider"></div>' +
      '<div class="stg-card-title">Internal Staff Notifications</div>' +

      '<div class="stg-toggle-list">' +
        toggleRow('stg-notif-stf-sale', 'New Ticket Sale Alert', 'Receive real-time push alert on ticket sales', com.staff_ticket_purchase !== false) +
        toggleRow('stg-notif-stf-msg', 'New Customer Message', 'Notify support team when inbound inquiry is submitted', com.staff_new_message !== false) +
        toggleRow('stg-notif-stf-anomaly', 'Check-In Anomaly Alert', 'Alert supervisor on repeated invalid or duplicate scan attempts', com.staff_checkin_anomaly !== false) +
      '</div>' +

    '</div>';
  }

  /* ── 8. Message Templates ── */
  function renderTemplates(com) {
    var tpls = com.templates || {};
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Notification Message Templates</div>' +
      '<div class="stg-card-sub">Customize automated email and messaging templates sent to attendees.</div>' +

      '<div class="stg-template-list">' +
        renderTemplateRow('ticket_purchase', 'Ticket Purchase Confirmation', tpls.ticket_purchase) +
        renderTemplateRow('event_reminder', 'Event Reminder Notice', tpls.event_reminder) +
        renderTemplateRow('refund_confirm', 'Refund Settlement Receipt', tpls.refund_confirm) +
      '</div>' +

    '</div>';
  }

  function renderTemplateRow(key, title, tpl) {
    tpl = tpl || { subject: 'Notification', body: '' };
    return '<div class="stg-tpl-item">' +
      '<div>' +
        '<strong>' + esc(title) + '</strong>' +
        '<small style="display:block;color:var(--ecc-text-dim);font-size:0.68rem;margin-top:2px;">Subject: ' + esc(tpl.subject) + '</small>' +
      '</div>' +
      '<button type="button" class="stg-btn stg-btn-ghost" onclick="SettingsControlCenter.openTemplateModal(\'' + key + '\')">Edit Template</button>' +
    '</div>';
  }

  /* ── 9. Payments & Settlements ── */
  function renderPayments(fin) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Payments & Settlement Configuration</div>' +
      '<div class="stg-card-sub">Configure customer payment channels and organizer bank settlement schedule.</div>' +

      '<div class="stg-info-banner">' +
        '<strong>Uthenga Payments Engine</strong><br>' +
        'Customer payments are securely processed through Uthenga Payment Gateway supporting Mobile Money (Airtel & Mpamba) and Bank Visa/Mastercard.' +
      '</div>' +

      '<div class="stg-form-grid" style="margin-top:1rem;">' +
        '<div class="stg-form-field full">' +
          '<label>Settlement Bank Account</label>' +
          '<input type="text" class="stg-input" value="' + esc(fin.settlement_account || '•••• 4821 (National Bank of Malawi)') + '" readonly style="background:var(--ecc-surface-2);">' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Settlement Schedule</label>' +
          '<select class="stg-select" id="stg-fin-schedule">' +
            '<option value="Daily" ' + (fin.settlement_schedule === 'Daily' ? 'selected' : '') + '>Daily Settlement</option>' +
            '<option value="Weekly" ' + (fin.settlement_schedule === 'Weekly' ? 'selected' : '') + '>Weekly Settlement</option>' +
            '<option value="Monthly" ' + (fin.settlement_schedule === 'Monthly' ? 'selected' : '') + '>Monthly Settlement</option>' +
          '</select>' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Settlement Day</label>' +
          '<select class="stg-select" id="stg-fin-day">' +
            '<option value="Friday" ' + (fin.settlement_day === 'Friday' ? 'selected' : '') + '>Every Friday</option>' +
            '<option value="Monday" ' + (fin.settlement_day === 'Monday' ? 'selected' : '') + '>Every Monday</option>' +
          '</select>' +
        '</div>' +
      '</div>' +

    '</div>';
  }

  /* ── 10. Fees & Refund Policies ── */
  function renderFeesPolicies(fin) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Refund & Cancellation Policies</div>' +
      '<div class="stg-card-sub">Define organizer-wide refund eligibility and customer cancellation windows.</div>' +

      '<div class="stg-toggle-list">' +
        toggleRow('stg-fin-allowrefund', 'Allow Refunds', 'Permit ticket refunds according to policy window', fin.allow_refunds !== false) +
      '</div>' +

      '<div class="stg-form-grid" style="margin-top:1rem;">' +
        '<div class="stg-form-field">' +
          '<label>Refund Window (Hours before Event Start)</label>' +
          '<input type="number" class="stg-input" id="stg-fin-refwindow" value="' + (fin.refund_window_hours || 48) + '" min="1" max="168">' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Refund Approval Workflow</label>' +
          '<select class="stg-select" id="stg-fin-refapproval">' +
            '<option value="manual" ' + (fin.refund_approval_mode === 'manual' ? 'selected' : '') + '>Manual Organizer Approval Required</option>' +
            '<option value="auto" ' + (fin.refund_approval_mode === 'auto' ? 'selected' : '') + '>Automatic Instant Refund (Within window)</option>' +
          '</select>' +
        '</div>' +
      '</div>' +

    '</div>';
  }

  /* ── 11. Security & Authentication ── */
  function renderSecurity(sec) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Security & Authentication</div>' +
      '<div class="stg-card-sub">Manage multi-factor authentication, active sessions, and access security policies.</div>' +

      '<div class="stg-form-grid">' +
        '<div class="stg-form-field">' +
          '<label>MFA Enforcement</label>' +
          '<select class="stg-select" id="stg-sec-mfamode">' +
            '<option value="required_admins" ' + (sec.mfa_mode === 'required_admins' ? 'selected' : '') + '>Required for Admins & Financial Staff</option>' +
            '<option value="required_all" ' + (sec.mfa_mode === 'required_all' ? 'selected' : '') + '>Required for All Staff Members</option>' +
            '<option value="optional" ' + (sec.mfa_mode === 'optional' ? 'selected' : '') + '>Optional</option>' +
          '</select>' +
        '</div>' +

        '<div class="stg-form-field">' +
          '<label>Session Inactivity Timeout (Minutes)</label>' +
          '<select class="stg-select" id="stg-sec-timeout">' +
            '<option value="15" ' + (sec.session_timeout_minutes == 15 ? 'selected' : '') + '>15 minutes</option>' +
            '<option value="30" ' + (sec.session_timeout_minutes == 30 ? 'selected' : '') + '>30 minutes</option>' +
            '<option value="60" ' + (sec.session_timeout_minutes == 60 ? 'selected' : '') + '>60 minutes</option>' +
          '</select>' +
        '</div>' +
      '</div>' +

      '<div class="stg-divider"></div>' +
      '<div class="stg-card-title">Active Login Sessions</div>' +

      '<div class="stg-session-list">' +
        '<div class="stg-session-item">' +
          '<div>' +
            '<strong>Chrome · Linux (Current Session)</strong>' +
            '<small style="display:block;color:var(--ecc-text-dim);font-size:0.68rem;">Lilongwe, Malawi · Active now</small>' +
          '</div>' +
          '<span class="stg-badge green">Active Now</span>' +
        '</div>' +

        '<div class="stg-session-item">' +
          '<div>' +
            '<strong>Uthenga Staff App · Android</strong>' +
            '<small style="display:block;color:var(--ecc-text-dim);font-size:0.68rem;">Blantyre, Malawi · Last active 2h ago</small>' +
          '</div>' +
          '<button type="button" class="stg-btn stg-btn-ghost" onclick="eccNotify(\'Session revoked!\')">Sign Out</button>' +
        '</div>' +
      '</div>' +

    '</div>';
  }

  /* ── 12. Staff Access Policies ── */
  function renderStaffAccess(sec) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Organization Staff Access Policies</div>' +
      '<div class="stg-card-sub">Configure global security rules for staff invitations and role assignments.</div>' +

      '<div class="stg-info-banner">' +
        '<strong>Staff Management Connection</strong><br>' +
        'Individual staff members, team roles, and event assignments are managed in the <strong>Staff</strong> tab. These settings control default organization policies.' +
      '</div>' +

      '<div class="stg-toggle-list" style="margin-top:1rem;">' +
        toggleRow('stg-stf-reqapp', 'Require Approval for Staff Invitations', 'Require admin review before new staff invitations are dispatched', sec.require_staff_invitation_approval !== false) +
        toggleRow('stg-stf-temp', 'Allow Temporary Event-Scoped Access', 'Automatically expire staff permissions when assigned event concludes', sec.allow_temporary_access !== false) +
      '</div>' +

    '</div>';
  }

  /* ── 13. Integrations ── */
  function renderIntegrations(intg) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Connected Platform Services</div>' +
      '<div class="stg-card-sub">Manage core Uthenga platform integrations for payments, email, SMS, and storage.</div>' +

      '<div class="stg-intg-list">' +
        renderIntgRow('Uthenga Payment Gateway', 'Active payment processing for Airtel Money, Mpamba & Cards', 'connected') +
        renderIntgRow('Uthenga Mail Gateway', 'Automated ticket confirmation & reminder email dispatch', 'connected') +
        renderIntgRow('Airtel & TNM SMS API', 'SMS ticket delivery and mobile notification service', 'not_configured') +
        renderIntgRow('OpenStreetMap & Venues API', 'Geolocation, maps, and interactive seating chart engine', 'connected') +
      '</div>' +

      '<div class="stg-divider"></div>' +
      '<div class="stg-card-title">Developer Webhooks</div>' +
      '<div class="stg-card-sub">Receive real-time HTTP POST notifications when events occur.</div>' +

      '<div class="stg-webhook-box">' +
        '<div style="font-size:0.75rem;font-weight:700;margin-bottom:0.4rem;">Active Endpoint</div>' +
        '<code style="font-family:monospace;font-size:0.75rem;color:var(--ecc-primary);background:var(--ecc-surface-2);padding:0.4rem 0.6rem;border-radius:6px;display:block;">https://api.eventsmalawi.mw/webhooks/uthenga</code>' +
        '<div style="font-size:0.68rem;color:var(--ecc-text-dim);margin-top:0.4rem;">Events: ticket.purchased, attendee.checked_in, event.updated</div>' +
      '</div>' +

    '</div>';
  }

  function renderIntgRow(title, desc, status) {
    var badge = status === 'connected'
      ? '<span class="stg-badge green">● Connected</span>'
      : '<span class="stg-badge neu">Not Configured</span>';
    return '<div class="stg-intg-row">' +
      '<div>' +
        '<strong>' + esc(title) + '</strong>' +
        '<small style="display:block;color:var(--ecc-text-dim);font-size:0.68rem;margin-top:2px;">' + esc(desc) + '</small>' +
      '</div>' +
      badge +
    '</div>';
  }

  /* ── 14. Data & Privacy ── */
  function renderDataPrivacy(dat) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Data Privacy & Retention Policies</div>' +
      '<div class="stg-card-sub">Configure customer data access boundaries, document archiving, and compliance.</div>' +

      '<div class="stg-form-grid">' +
        '<div class="stg-form-field">' +
          '<label>Financial & Document Retention (Years)</label>' +
          '<select class="stg-select" id="stg-dat-retention">' +
            '<option value="5" ' + (dat.doc_retention_years == 5 ? 'selected' : '') + '>5 Years</option>' +
            '<option value="7" ' + (dat.doc_retention_years == 7 ? 'selected' : '') + '>7 Years (Standard Compliance)</option>' +
            '<option value="10" ' + (dat.doc_retention_years == 10 ? 'selected' : '') + '>10 Years</option>' +
          '</select>' +
        '</div>' +
      '</div>' +

      '<div class="stg-divider"></div>' +
      '<div class="stg-card-title">Staff Customer Data Restrictions</div>' +

      '<div class="stg-toggle-list">' +
        toggleRow('stg-dat-custphone', 'Show Customer Phone to Gate Staff', 'Allow scanner staff to view attendee phone numbers', dat.show_cust_phone_to_staff) +
        toggleRow('stg-dat-custemail', 'Show Customer Email to Support Staff', 'Allow support staff to view customer email addresses', dat.show_cust_email_to_staff !== false) +
      '</div>' +

      '<div class="stg-divider"></div>' +
      '<div class="stg-card-title">Export Business Records</div>' +
      '<div class="stg-card-sub">Generate downloadable CSV/PDF export package of all historical event and sales data.</div>' +
      '<button type="button" class="stg-btn stg-btn-ghost" onclick="eccNotify(\'Data export package requested. Download link will be sent to your email.\')">Export All Event Data</button>' +

    '</div>';
  }

  /* ── 15. Advanced & Danger Zone ── */
  function renderAdvanced(adv) {
    return '<div class="stg-card">' +
      '<div class="stg-card-title">Advanced Configuration</div>' +
      '<div class="stg-card-sub">Developer controls and advanced system behavior.</div>' +

      '<div class="stg-toggle-list">' +
        toggleRow('stg-adv-dev', 'Developer Mode & Detailed API Logs', 'Log full JSON request payloads in audit history', adv.developer_mode) +
        toggleRow('stg-adv-maint', 'Maintenance Mode', 'Pause ticket checkout temporarily for system maintenance', adv.maintenance_mode) +
      '</div>' +

      '<div class="stg-divider"></div>' +
      '<div class="stg-danger-card">' +
        '<div class="stg-danger-title">Danger Zone</div>' +
        '<div class="stg-danger-sub">Irreversible actions regarding your event business profile.</div>' +

        '<div class="stg-danger-actions">' +
          '<div class="stg-danger-row">' +
            '<div><strong>Archive All Concluded Events</strong><br><small>Move past events to historical archive</small></div>' +
            '<button type="button" class="stg-btn stg-btn-ghost" onclick="eccNotify(\'All concluded events archived.\')">Archive Events</button>' +
          '</div>' +

          '<div class="stg-danger-row">' +
            '<div><strong>Deactivate Event Business</strong><br><small>Pause ticket purchasing for all active events</small></div>' +
            '<button type="button" class="stg-btn stg-btn-danger" onclick="SettingsControlCenter.openDangerModal(\'deactivate\')">Deactivate Business</button>' +
          '</div>' +
        '</div>' +
      '</div>' +

    '</div>';
  }

  /* Helper to render switch toggles */
  function toggleRow(id, title, desc, checked) {
    var chk = checked ? 'checked' : '';
    return '<div class="stg-toggle-row">' +
      '<div>' +
        '<strong style="font-size:0.78rem;color:var(--ecc-text);">' + esc(title) + '</strong>' +
        '<small style="display:block;color:var(--ecc-text-dim);font-size:0.68rem;margin-top:2px;">' + esc(desc) + '</small>' +
      '</div>' +
      '<label class="stg-switch">' +
        '<input type="checkbox" id="' + id + '" ' + chk + '>' +
        '<span class="stg-slider"></span>' +
      '</label>' +
    '</div>';
  }

  /* ── Template Modal ── */
  function openTemplateModal(key) {
    var tpls = (state.data.communications || {}).templates || {};
    var tpl = tpls[key] || { subject: 'Notification', body: '' };

    var html =
      '<div class="ecc-modal-backdrop open" id="stg-modal-tpl">' +
        '<div class="ecc-modal" style="max-width:550px;">' +
          '<div class="ecc-modal-head">' +
            '<h3>Edit Message Template</h3>' +
            '<button class="ecc-modal-close" onclick="SettingsControlCenter.closeModal(\'stg-modal-tpl\')">×</button>' +
          '</div>' +
          '<div class="ecc-modal-body">' +
            '<div style="margin-bottom:0.8rem;">' +
              '<label style="font-size:0.75rem;font-weight:700;">Subject Line</label>' +
              '<input type="text" class="stg-input" id="stg-modal-tpl-subj" value="' + esc(tpl.subject) + '">' +
            '</div>' +
            '<div style="margin-bottom:0.8rem;">' +
              '<label style="font-size:0.75rem;font-weight:700;">Message Body</label>' +
              '<textarea class="stg-textarea" id="stg-modal-tpl-body" rows="6">' + esc(tpl.body) + '</textarea>' +
            '</div>' +
            '<div style="font-size:0.65rem;color:var(--ecc-text-dim);">' +
              'Available Variable Tags: <code>{{customer_name}}</code>, <code>{{event_name}}</code>, <code>{{ticket_number}}</code>, <code>{{event_date}}</code>, <code>{{venue_name}}</code>' +
            '</div>' +
          '</div>' +
          '<div class="ecc-modal-foot">' +
            '<button type="button" class="stg-btn stg-btn-ghost" onclick="SettingsControlCenter.closeModal(\'stg-modal-tpl\')">Cancel</button>' +
            '<button type="button" class="stg-btn stg-btn-primary" onclick="SettingsControlCenter.saveTemplate(\'' + key + '\')">Save Template</button>' +
          '</div>' +
        '</div>' +
      '</div>';

    var old = document.getElementById('stg-modal-tpl');
    if (old) old.remove();
    document.body.insertAdjacentHTML('beforeend', html);
  }

  function saveTemplate(key) {
    var subj = document.getElementById('stg-modal-tpl-subj').value;
    var body = document.getElementById('stg-modal-tpl-body').value;

    if (!state.data.communications) state.data.communications = {};
    if (!state.data.communications.templates) state.data.communications.templates = {};

    state.data.communications.templates[key] = { subject: subj, body: body };
    markDirty();
    closeModal('stg-modal-tpl');
    render();
    if (window.eccNotify) eccNotify('Template updated! Remember to save settings.');
  }

  function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.remove();
  }

  /* ── Public API ── */
  return {
    init: function () {
      rootEl = document.getElementById('mod-settings');
      if (!rootEl) return;

      apiEndpoint = getBaseUrl() + 'api/tie/vendor/events/settings.php';
      if (!state.booted) {
        fetchSettings();
      } else {
        render();
      }
    },
    selectSection: function (secId) {
      state.activeSection = secId;
      render();
    },
    onSearch: function (val) {
      state.searchQuery = val || '';
      render();
      var input = document.getElementById('stg-search-input');
      if (input) {
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
      }
    },
    save: function () {
      saveAllSettings();
    },
    discard: function () {
      state.isDirty = false;
      fetchSettings();
      if (window.eccNotify) eccNotify('Unsaved changes discarded.');
    },
    openTemplateModal: openTemplateModal,
    saveTemplate: saveTemplate,
    closeModal: closeModal,
    openDangerModal: function (action) {
      if (window.eccNotify) eccNotify('Action disabled in demo mode.', 'error');
    }
  };
})();
