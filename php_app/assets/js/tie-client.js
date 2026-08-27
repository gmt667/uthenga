/*
 * Browser boundary for TIE. It transports user input to authoritative TIE
 * contracts; it never calculates scores, availability, or booking state.
 */
(function (global) {
  'use strict';

  function cleanBaseUrl(value) {
    return String(value || '').replace(/\/?$/, '/');
  }

  function UiError(message, type, requestId, details) {
    this.name = 'UthengaTieUiError';
    this.message = message || 'Uthenga Travel Intelligence is unavailable right now.';
    this.type = type || 'request_error';
    this.requestId = requestId || null;
    this.details = details || {};
  }
  UiError.prototype = Object.create(Error.prototype);

  function create(options) {
    options = options || {};
    var baseUrl = cleanBaseUrl(options.baseUrl);
    var csrfToken = String(options.csrfToken || '');
    var features = options.features || {};
    var authenticated = Boolean(options.authenticated);

    function endpoint(path) { return baseUrl + 'api/tie/' + String(path || '').replace(/^\/+/, ''); }
    function enabled(name) { return Boolean(features[name]); }

    function request(path, method, payload) {
      method = method || 'GET';
      if (method !== 'GET' && !authenticated) return Promise.reject(new UiError('Sign in to use Uthenga Travel Intelligence.', 'authentication_error'));
      var init = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
      if (method !== 'GET') {
        init.headers['Content-Type'] = 'application/json';
        init.headers['X-CSRF-Token'] = csrfToken;
        init.body = JSON.stringify(payload || {});
      }
      return fetch(endpoint(path), init).then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (data) {
          if (!response.ok || !data.success) {
            var error = data.error || {};
            throw new UiError(error.message || data.message || 'The request could not be completed.', error.type || 'request_error', error.request_id || data.request_id, error.details);
          }
          return data;
        });
      });
    }

    return {
      enabled: enabled,
      features: features,
      authenticated: authenticated,
      health: function () { return request('health.php', 'GET'); },
      services: function (query) { return request('services.php?' + new URLSearchParams(query || {}).toString(), 'GET'); },
      categories: function () { return request('categories.php', 'GET'); },
      context: function () { return request('context.php', 'GET'); },
      buildContext: function (trip) { return request('context/build.php', 'POST', trip); },
      recommend: function (trip) { return request('recommendations.php', 'POST', trip); },
      chat: function (trip) { return request('conversation/chat.php', 'POST', trip); },
      createPlan: function (trip) { return request('plans/create.php', 'POST', trip); },
      viewPlan: function (planId) { return request('plans/view.php?plan_id=' + encodeURIComponent(planId), 'GET'); },
      updatePlan: function (payload) { return request('plans/update.php', 'POST', payload); },
      validatePlan: function (planId) { return request('plans/validate.php', 'POST', { plan_id: planId }); },
      approvePlan: function (planId) { return request('plans/approve.php', 'POST', { plan_id: planId }); },
      exportPlan: function (planId) { return request('plans/export.php', 'POST', { plan_id: planId }); },
      paymentOptions: function (planId) { return request('payments/options.php?plan_id=' + encodeURIComponent(planId), 'GET'); },
      startPayment: function (payload) { return request('payments/start.php', 'POST', payload); },
      validateAvailability: function (payload) { return request('availability/validate.php', 'POST', payload); },
      locationPermission: function (payload) { return request('location/permission.php', 'POST', payload); },
      locationContext: function (payload) { return request('location/context.php', 'POST', payload); },
      nearby: function (payload) { return request('location/nearby.php', 'POST', payload); },
      quickTravelDiscover: function (payload) { return request('coordination/discover.php', 'POST', payload); },
      quickTravelAction: function (payload) { return request('coordination/action.php', 'POST', payload); },
      quickTravelSession: function (sessionId) { return request('coordination/session.php?session_id=' + encodeURIComponent(sessionId), 'GET'); },
      quickTravelVendorQueue: function () { return request('coordination/vendor-queue.php', 'GET'); },
      vendorProfiles: function () { return request('vendor/profiles.php', 'GET'); },
      vendorProfileAction: function (payload) { return request('vendor/profiles.php', 'POST', payload); },
      validateBooking: function (payload) { return request('bookings/validate.php', 'POST', payload); },
      bookingStatus: function (executionId) { return request('bookings/status.php?execution_id=' + encodeURIComponent(executionId), 'GET'); },
      error: UiError
    };
  }

  global.UthengaTieClient = { create: create, Error: UiError };
}(window));
