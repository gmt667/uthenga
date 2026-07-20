<?php
/**
 * Uthenga - Branded page loader overlay.
 * Use on any standalone page or shared shell to show the scanning logo.
 */
require_once __DIR__ . '/../config.php';

?>
<script>
(function () {
  try {
    document.documentElement.classList.add('uthenga-app-loading');
  } catch (e) {}

  var done = false;
  function finish() {
    if (done) return;
    done = true;
    try {
      document.documentElement.classList.remove('uthenga-app-loading');
      document.documentElement.classList.add('uthenga-app-ready');
    } catch (e) {}
    var loader = document.getElementById('uthenga-page-loader');
    if (loader) loader.classList.add('is-hidden');
  }

  window.addEventListener('load', finish, { once: true });
  window.addEventListener('pageshow', finish, { once: true });
  document.addEventListener('DOMContentLoaded', function () {
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
      window.setTimeout(finish, 120);
    }
  }, { once: true });
  window.setTimeout(finish, 4000);
})();
</script>

<div class="uthenga-page-loader" id="uthenga-page-loader" role="status" aria-live="polite" aria-label="Loading Uthenga">
  <div class="uthenga-page-loader-card">
    <div class="uthenga-page-loader-logo">
      <?php $logoSize = 'lg'; $logoLink = false; require __DIR__ . '/logo.php'; ?>
    </div>
    <div class="uthenga-loader-scan" aria-hidden="true">
      <span class="uthenga-loader-scan-line"></span>
    </div>
    <div class="uthenga-loader-copy">
      <strong>Loading secure marketplace</strong>
      <span>Checking routes, syncing data, and preparing your dashboard.</span>
    </div>
  </div>
</div>
