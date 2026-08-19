/*
 * Explicit, foreground-only browser location helper for Phase 5.
 * It never runs on page load, watches position, or persists coordinates.
 */
(function (global) {
  'use strict';

  function permissionState() {
    if (!navigator.permissions || !navigator.permissions.query) return Promise.resolve('requested');
    return navigator.permissions.query({ name: 'geolocation' })
      .then(function (result) { return result.state; })
      .catch(function () { return 'requested'; });
  }

  function currentPosition(options) {
    if (!navigator.geolocation) return Promise.reject(locationError('UNAVAILABLE', 'Location is unavailable in this browser.'));
    return permissionState().then(function (state) {
      if (state === 'denied') throw locationError('DENIED', 'Location permission was denied.');
      return new Promise(function (resolve, reject) {
        navigator.geolocation.getCurrentPosition(function (position) {
          resolve({
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy_m: position.coords.accuracy,
            captured_at: new Date(position.timestamp).toISOString(),
            source: 'browser_geolocation',
            platform: 'browser',
            permission_state: 'granted'
          });
        }, function (error) {
          var code = error && error.code === 1 ? 'DENIED' : (error && error.code === 2 ? 'UNAVAILABLE' : 'EXPIRED');
          reject(locationError(code, error.message || 'Current location could not be obtained.'));
        }, Object.assign({ enableHighAccuracy: false, timeout: 10000, maximumAge: 0 }, options || {}));
      });
    });
  }

  function locationError(code, message) {
    var error = new Error(message);
    error.code = code;
    error.fallbacks = ['search_by_city', 'search_by_district', 'search_by_destination', 'manual_map_selection'];
    return error;
  }

  function postJson(url, payload, csrfToken) {
    return fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken || '' },
      body: JSON.stringify(payload)
    }).then(function (response) { return response.json(); });
  }

  global.UthengaTieLocation = { permissionState: permissionState, currentPosition: currentPosition, postJson: postJson };
}(window));
