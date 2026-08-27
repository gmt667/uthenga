<?php
/**
 * Browser return point for TIE PayChangu checkout.
 * A return is never treated as payment proof: this page reads only the
 * authenticated user's server-owned intent status, which is updated after
 * signed webhook receipt and provider verification.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/tie/bootstrap.php';

$pageTitle = 'Payment status';
$activeNav = 'trip-planner';
$intentId = trim((string) ($_GET['payment_intent_id'] ?? ''));
$isBusBooking = preg_match('/^BKG-[A-F0-9]{12}$/', $intentId) === 1;
$validIntentId = $isBusBooking || preg_match('/^[a-f0-9-]{36}$/i', $intentId) === 1;
$signedIn = isLoggedIn();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<main class="container" style="max-width:720px;padding-top:3rem;padding-bottom:4rem;">
  <section class="card" aria-live="polite" style="padding:2rem;text-align:center;">
    <p class="text-muted" style="margin:0 0 .5rem;">Uthenga payment</p>
    <h1 style="margin-top:0;">Checking your payment</h1>
    <?php if (!$validIntentId): ?>
      <p>We could not identify that payment. You can review your trip plan and try again.</p>
      <a class="btn btn-primary" href="<?= BASE_URL ?>ai.php#/planner">Return to trip planner</a>
    <?php elseif (!$signedIn): ?>
      <p>Sign in to the same Uthenga account used to start this payment, then we’ll show its verified status.</p>
      <a class="btn btn-primary" href="<?= BASE_URL ?>login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? BASE_URL . 'tie-payment-return.php') ?>">Sign in to continue</a>
    <?php else: ?>
      <p id="payment-status-message">Waiting for verified confirmation from PayChangu…</p>
      <p id="payment-status-detail" class="text-muted"></p>
      <div id="payment-status-actions" style="margin-top:1.25rem;"></div>
    <?php endif; ?>
  </section>
</main>

<?php if ($validIntentId && $signedIn && !$isBusBooking): ?>
<script>
(() => {
  const intentId = <?= json_encode($intentId, JSON_UNESCAPED_SLASHES) ?>;
  const baseUrl = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) ?>;
  const message = document.getElementById('payment-status-message');
  const detail = document.getElementById('payment-status-detail');
  const actions = document.getElementById('payment-status-actions');
  let attempts = 0;
  const complete = new Set(['BOOKED', 'FAILED', 'CANCELLED', 'REFUND_REQUIRED', 'REFUNDED', 'MANUAL_REVIEW']);
  const showPlan = () => { actions.innerHTML = '<a class="btn btn-primary" href="' + baseUrl + 'ai.php#/planner">Return to trip planner</a>'; };
  const check = async () => {
    attempts += 1;
    try {
      const response = await fetch(baseUrl + 'api/tie/payments/status.php?payment_intent_id=' + encodeURIComponent(intentId), {credentials: 'same-origin'});
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error('status_unavailable');
      const payment = data.payment_intent || {};
      const state = String(payment.status || 'PAYMENT_PENDING');
      detail.textContent = 'Payment status: ' + state.replaceAll('_', ' ').toLowerCase() + '.';
      if (state === 'BOOKED') {
        message.textContent = 'Payment verified — your booking is confirmed.';
        showPlan();
        return;
      }
      if (complete.has(state)) {
        message.textContent = state === 'REFUND_REQUIRED' ? 'Your payment needs support review before the booking can be completed.' : 'This payment was not completed.';
        showPlan();
        return;
      }
      message.textContent = 'We are waiting for verified confirmation from PayChangu…';
      if (attempts < 8) window.setTimeout(check, 3000);
      else { message.textContent = 'Payment confirmation is still processing. You can safely return to your trip planner and check again shortly.'; showPlan(); }
    } catch (_) {
      message.textContent = 'We could not retrieve the payment status right now. No booking has been assumed.';
      showPlan();
    }
  };
  check();
})();
</script>
<?php endif; ?>

<?php if ($isBusBooking && $signedIn): ?>
<script>
(() => {
  const bookingId = <?= json_encode($intentId, JSON_UNESCAPED_SLASHES) ?>;
  const baseUrl = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) ?>;
  const message = document.getElementById('payment-status-message');
  const detail = document.getElementById('payment-status-detail');
  const actions = document.getElementById('payment-status-actions');
  let attempts = 0;
  const showTickets = () => { actions.innerHTML = '<a class="btn btn-primary" href="' + baseUrl + 'bus-tickets.php?view=my-tickets">View my tickets</a> <a class="btn btn-secondary" href="' + baseUrl + 'transport.php">Back to Transport</a>'; };
  const check = async () => {
    attempts += 1;
    try {
      const response = await fetch(baseUrl + 'api/tie/transport/purchase-status.php?booking_id=' + encodeURIComponent(bookingId), { credentials: 'same-origin' });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error('status_unavailable');
      const result = data.result || {};
      detail.textContent = 'Payment status: ' + String(result.payment_status || 'Pending').toLowerCase() + '.';
      if (result.payment_status === 'Paid') { message.textContent = 'Payment verified — your ticket' + (result.tickets.length > 1 ? 's are' : ' is') + ' ready.'; showTickets(); return; }
      if (result.payment_status === 'Failed') { message.textContent = 'This payment was not completed. Your seat has been released.'; showTickets(); return; }
      message.textContent = 'We are waiting for verified confirmation from PayChangu…';
      if (attempts < 8) window.setTimeout(check, 3000);
      else { message.textContent = 'Payment confirmation is still processing. Check My Tickets again shortly.'; showTickets(); }
    } catch (_) {
      message.textContent = 'We could not retrieve the payment status right now. No ticket has been assumed.';
      showTickets();
    }
  };
  check();
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
