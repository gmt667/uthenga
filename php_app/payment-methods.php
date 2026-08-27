<?php
/**
 * Uthenga — Customer Payment Methods
 * Real, PayChangu-backed mobile money + bank transfer credentials the
 * customer must configure before any bus ticket purchase.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth_check.php';
requireCustomer();

require_once __DIR__ . '/includes/dashboard_shell.php';
renderDashboardChromeStart([
    'role' => ROLE_CUSTOMER,
    'title' => 'Payment Methods',
    'active' => 'payment-methods.php',
    'search' => false,
    'status' => 'Customer Account',
]);
?>
<style>
  .pm-grid { display:grid; grid-template-columns: 1.1fr 0.9fr; gap:1.75rem; align-items:start; }
  @media (max-width: 900px) { .pm-grid { grid-template-columns: 1fr; } }
  .pm-method { display:flex; align-items:center; gap:1rem; padding:1.1rem 1.25rem; border:1px solid var(--clr-border); border-radius:16px; background:var(--clr-surface); margin-bottom:0.85rem; }
  .pm-method-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:var(--clr-bg); font-weight:800; flex-shrink:0; }
  .pm-method-body { flex:1; min-width:0; }
  .pm-method-title { font-weight:700; }
  .pm-method-sub { color:var(--clr-text-muted); font-size:0.82rem; margin-top:0.15rem; }
  .pm-method-actions { display:flex; gap:0.5rem; flex-shrink:0; }
  .pm-empty { padding:2rem 1.5rem; text-align:center; border:1px dashed var(--clr-border); border-radius:16px; color:var(--clr-text-muted); }
  .pm-loading { color:var(--clr-text-muted); font-size:0.9rem; padding:1rem 0; }
  .pm-method .status-badge { display:inline-block; padding:0.2rem 0.6rem; border-radius:999px; font-size:0.72rem; font-weight:700; }
  .pm-method .status-confirmed { background:rgba(16,185,129,0.12); color:var(--clr-green); }
  .pm-method .status-pending { background:rgba(245,158,11,0.15); color:var(--clr-yellow); }
</style>

<div class="container dashboard-content-frame" style="padding-top:2.25rem;padding-bottom:3rem;" id="pm-page" data-csrf="<?= e($_SESSION['csrf_token'] ?? '') ?>">

  <div class="page-header">
    <h1 class="page-title">Payment Methods</h1>
    <p class="text-muted" style="margin-top:0.35rem;">A payment method must be on file before you can buy a bus ticket. Charges go straight to your mobile money wallet or a real bank transfer — no card details, ever.</p>
  </div>

  <div id="pm-alert" style="display:none;margin-bottom:1.25rem;"></div>

  <div class="pm-grid">
    <div>
      <h3 style="margin-bottom:1rem;">Saved Methods</h3>
      <div id="pm-list"><div class="pm-loading">Loading your saved payment methods…</div></div>
    </div>

    <div>
      <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;">
        <h3 style="margin-bottom:1rem;">Add Mobile Money</h3>
        <form id="pm-mobile-form">
          <div class="form-group">
            <label class="form-label" for="pm-mobile-number">Mobile Number</label>
            <input type="tel" id="pm-mobile-number" class="form-control" placeholder="0991234567" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="pm-operator">Operator</label>
            <select id="pm-operator" class="form-control" required>
              <option value="">Loading operators…</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary" id="pm-mobile-submit" style="width:100%;">Save Mobile Money</button>
        </form>
      </div>

      <div class="card" style="padding:1.5rem;">
        <h3 style="margin-bottom:0.5rem;">Bank Transfer</h3>
        <p class="text-muted text-sm" style="margin-bottom:1rem;">We'll generate a real bank account in your name that you can transfer into — one click, no card details.</p>
        <button type="button" class="btn btn-secondary" id="pm-bank-submit" style="width:100%;">Enable Bank Transfer</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var root = document.getElementById('pm-page');
  var csrf = root.dataset.csrf || '';
  var baseUrl = document.querySelector('meta[name="base-url"]').content;
  var api = baseUrl + 'api/tie/transport/payment-methods.php';

  function showAlert(kind, message) {
    var box = document.getElementById('pm-alert');
    box.className = 'alert ' + (kind === 'error' ? 'alert-error' : 'alert-success');
    box.textContent = message;
    box.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function apiGet(action) {
    return fetch(api + '?action=' + encodeURIComponent(action), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function apiPost(body) {
    return fetch(api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body) }).then(function (r) { return r.json(); });
  }

  function renderMethods(methods) {
    var list = document.getElementById('pm-list');
    if (!methods.length) { list.innerHTML = '<div class="pm-empty">No payment methods yet. Add mobile money or enable bank transfer to unlock ticket purchases.</div>'; return; }
    list.innerHTML = '';
    methods.forEach(function (m) {
      var el = document.createElement('div');
      el.className = 'pm-method';
      var icon = m.channel === 'mobile_money' ? '📱' : '🏦';
      var title = m.channel === 'mobile_money' ? (m.operator_name || 'Mobile Money') : (m.bank_name || 'Bank Transfer');
      var sub = m.channel === 'mobile_money' ? m.mobile_number_masked : (m.account_number ? 'Acct ' + m.account_number + ' — ' + m.account_name : 'Account details pending');
      var badge = m.verified ? '<span class="status-badge status-confirmed">Verified</span>' : '<span class="status-badge status-pending">Pending first use</span>';
      el.innerHTML =
        '<div class="pm-method-icon">' + icon + '</div>' +
        '<div class="pm-method-body">' +
          '<div class="pm-method-title">' + title + (m.is_default ? ' <span class="text-xs text-muted">(Default)</span>' : '') + '</div>' +
          '<div class="pm-method-sub">' + sub + '</div>' +
          '<div style="margin-top:0.35rem;">' + badge + '</div>' +
        '</div>' +
        '<div class="pm-method-actions"></div>';
      var actions = el.querySelector('.pm-method-actions');
      if (!m.is_default) {
        var defaultBtn = document.createElement('button');
        defaultBtn.type = 'button'; defaultBtn.className = 'btn btn-sm btn-secondary'; defaultBtn.textContent = 'Make Default';
        defaultBtn.onclick = function () { setDefault(m.id); };
        actions.appendChild(defaultBtn);
      }
      var removeBtn = document.createElement('button');
      removeBtn.type = 'button'; removeBtn.className = 'btn btn-sm btn-danger'; removeBtn.textContent = 'Remove';
      removeBtn.onclick = function () { removeMethod(m.id); };
      actions.appendChild(removeBtn);
      list.appendChild(el);
    });
  }

  function loadMethods() {
    apiGet('list').then(function (j) {
      if (j && j.success) renderMethods(j.result.methods);
      else document.getElementById('pm-list').innerHTML = '<div class="pm-empty">Could not load your payment methods. Refresh to try again.</div>';
    });
  }

  function loadOperators() {
    apiGet('operators').then(function (j) {
      var select = document.getElementById('pm-operator');
      if (!j || !j.success || !j.result.operators.length) { select.innerHTML = '<option value="">Operators unavailable — try again shortly</option>'; return; }
      select.innerHTML = '<option value="">Select operator…</option>' + j.result.operators.map(function (op) {
        return '<option value="' + op.ref_id + '">' + op.name + '</option>';
      }).join('');
    });
  }

  function setDefault(id) {
    apiPost({ action: 'set_default', id: id }).then(function (j) {
      if (j && j.success) { renderMethods(j.result.methods); showAlert('success', 'Default payment method updated.'); }
      else showAlert('error', (j && j.error && j.error.message) || 'Could not update default method.');
    });
  }

  function removeMethod(id) {
    if (!confirm('Remove this payment method?')) return;
    apiPost({ action: 'remove', id: id }).then(function (j) {
      if (j && j.success) { renderMethods(j.result.methods); showAlert('success', 'Payment method removed.'); }
      else showAlert('error', (j && j.error && j.error.message) || 'Could not remove this method.');
    });
  }

  document.getElementById('pm-mobile-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('pm-mobile-submit');
    var mobile = document.getElementById('pm-mobile-number').value.trim();
    var operatorRefId = document.getElementById('pm-operator').value;
    if (!operatorRefId) { showAlert('error', 'Select a mobile money operator.'); return; }
    btn.disabled = true; btn.textContent = 'Saving…';
    apiPost({ action: 'add_mobile_money', mobile: mobile, operator_ref_id: operatorRefId }).then(function (j) {
      btn.disabled = false; btn.textContent = 'Save Mobile Money';
      if (j && j.success) { document.getElementById('pm-mobile-form').reset(); loadMethods(); showAlert('success', 'Mobile money number saved.'); }
      else showAlert('error', (j && j.error && j.error.details && j.error.details.fields && Object.values(j.error.details.fields)[0]) || (j && j.error && j.error.message) || 'Could not save that number.');
    });
  });

  document.getElementById('pm-bank-submit').addEventListener('click', function () {
    var btn = document.getElementById('pm-bank-submit');
    btn.disabled = true; btn.textContent = 'Setting up…';
    apiPost({ action: 'add_bank_transfer' }).then(function (j) {
      btn.disabled = false; btn.textContent = 'Enable Bank Transfer';
      if (j && j.success) { loadMethods(); showAlert('success', 'Bank transfer account ready.'); }
      else showAlert('error', (j && j.error && j.error.message) || 'Could not set up bank transfer right now.');
    });
  });

  loadMethods();
  loadOperators();
})();
</script>

<?php renderDashboardChromeEnd(); ?>
