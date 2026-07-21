<?php
/**
 * Uthenga - Shop Order Receipt
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/shop_helpers.php';
requireCustomer();

$activeNav = 'shop';
$userId = (string) ($_SESSION['user_id'] ?? '');
$orderNumber = trim((string) ($_GET['order'] ?? ''));
$order = $orderNumber !== '' ? uthenga_shop_order_by_number($orderNumber) : null;

if (!$order || (string) ($order['user_id'] ?? '') !== $userId) {
    redirect(BASE_URL . 'shop-orders.php');
}

$items = uthenga_shop_order_items((int) $order['id']);
$deliveryStatus = dbQueryOne('SELECT * FROM shop_deliveries WHERE order_id = ? LIMIT 1', [(int) $order['id']]);
$rider = null;
if (!empty($deliveryStatus['rider_id'])) {
    $rider = dbQueryOne('SELECT * FROM delivery_riders WHERE id = ? LIMIT 1', [(int) $deliveryStatus['rider_id']]);
}

$success = !empty($_SESSION['shop_order_success']) && $_SESSION['shop_order_success'] === $orderNumber;
unset($_SESSION['shop_order_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'cancel') {
        $result = uthenga_shop_cancel_order((int) $order['id'], $userId);
        if (!empty($result['ok'])) {
            redirect(BASE_URL . 'shop-order.php?order=' . urlencode($orderNumber));
        }
    }
}

require_once __DIR__ . '/includes/dashboard_shell.php';
renderDashboardChromeStart([
    'role' => ROLE_CUSTOMER,
    'title' => 'Order Receipt',
    'active' => 'shop-orders.php',
    'search' => false,
    'status' => 'Customer Account',
]);
?>
<style>
  .receipt-shell { padding:2rem 0 3rem; }
  .receipt-card { padding:1.5rem; border:1px solid var(--clr-border); border-radius:24px; background:var(--clr-surface); box-shadow: var(--shadow-md); }
  .receipt-grid { display:grid; grid-template-columns: minmax(0, 1.1fr) minmax(280px, .9fr); gap:1.25rem; }
  .receipt-lines { display:grid; gap:.55rem; }
  .receipt-line { display:flex; justify-content:space-between; gap:1rem; padding:.35rem 0; border-bottom:1px solid var(--clr-border); }
  .receipt-line:last-child { border-bottom:none; }
  .timeline { display:grid; gap:.7rem; }
  .timeline-step { padding:.75rem 1rem; border:1px solid var(--clr-border); border-radius:16px; background:var(--clr-surface2); }
  .timeline-step strong { display:block; }
  .print-bar { display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1rem; }
  @media (max-width: 960px) { .receipt-grid { grid-template-columns:1fr; } }
</style>

<div class="container receipt-shell">
  <?php if ($success): ?>
    <div class="glass-panel" style="padding:1rem;margin-bottom:1rem;border-left:4px solid var(--clr-green);">
      <strong>Your order has been placed successfully.</strong>
    </div>
  <?php endif; ?>

  <div class="page-header" style="margin-bottom:1rem;">
    <div>
      <h1 class="page-title">Receipt <?= e($order['order_number']) ?></h1>
      <p class="text-muted">Invoice and delivery details for your Shop order.</p>
    </div>
    <div class="dashboard-head-meta">
      <a href="<?= BASE_URL ?>shop-orders.php" class="btn btn-secondary btn-sm">Back to Orders</a>
      <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print Receipt</button>
    </div>
  </div>

  <div class="receipt-grid">
    <section class="receipt-card">
      <div class="section-label">Order Summary</div>
      <h2 style="margin-top:.3rem;"><?= e($order['customer_name']) ?></h2>
      <div class="detail-badges" style="margin-top:.75rem;">
        <span class="detail-badge"><?= e($order['payment_method']) ?></span>
        <span class="detail-badge <?= uthenga_shop_status_badge((string) $order['order_status']) ?>"><?= e($order['order_status']) ?></span>
        <span class="detail-badge <?= uthenga_shop_status_badge((string) $order['payment_status']) ?>"><?= e($order['payment_status']) ?></span>
      </div>
      <div style="margin-top:1rem;" class="receipt-lines">
        <?php foreach ($items as $item): ?>
          <div class="receipt-line">
            <span><?= e($item['product_name']) ?> x <?= (int) $item['quantity'] ?></span>
            <strong><?= uthenga_shop_money((float) $item['line_total']) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="receipt-lines" style="margin-top:1rem;">
        <div class="receipt-line"><span>Subtotal</span><strong><?= uthenga_shop_money((float) $order['subtotal']) ?></strong></div>
        <div class="receipt-line"><span>Delivery fee</span><strong><?= uthenga_shop_money((float) $order['delivery_fee']) ?></strong></div>
        <div class="receipt-line"><span>Tax</span><strong><?= uthenga_shop_money((float) $order['tax_amount']) ?></strong></div>
        <div class="receipt-line"><span>Discount</span><strong>-<?= uthenga_shop_money((float) $order['discount_amount']) ?></strong></div>
        <div class="receipt-line"><span>Total</span><strong><?= uthenga_shop_money((float) $order['total_amount']) ?></strong></div>
      </div>
      <div class="print-bar">
        <a href="<?= BASE_URL ?>shop.php" class="btn btn-secondary btn-sm">Continue Shopping</a>
        <?php if (in_array(strtolower((string) $order['order_status']), ['pending', 'confirmed', 'preparing'], true)): ?>
          <form method="post" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-secondary btn-sm">Cancel Order</button>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <aside class="receipt-card">
      <div class="section-label">Delivery & Tracking</div>
      <h3 style="margin-top:.3rem;">Order status</h3>
      <div class="timeline" style="margin-top:1rem;">
        <div class="timeline-step"><strong>Placed</strong><span class="text-muted"><?= e($order['placed_at']) ?></span></div>
        <div class="timeline-step"><strong>Confirmed</strong><span class="text-muted"><?= e($order['confirmed_at'] ?: 'Pending') ?></span></div>
        <div class="timeline-step"><strong>Prepared</strong><span class="text-muted"><?= e($order['prepared_at'] ?: 'Pending') ?></span></div>
        <div class="timeline-step"><strong>Dispatched</strong><span class="text-muted"><?= e($order['dispatched_at'] ?: 'Pending') ?></span></div>
        <div class="timeline-step"><strong>Delivered</strong><span class="text-muted"><?= e($order['delivered_at'] ?: 'Pending') ?></span></div>
      </div>
      <div style="margin-top:1rem;">
        <h3>Delivery Address</h3>
        <p class="text-muted" style="margin-bottom:.5rem;"><?= nl2br(e($order['delivery_address'])) ?></p>
        <?php if (!empty($order['delivery_instructions'])): ?>
          <p class="text-muted"><strong>Instructions:</strong> <?= nl2br(e($order['delivery_instructions'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($order['preferred_delivery_time'])): ?>
          <p class="text-muted"><strong>Preferred time:</strong> <?= e($order['preferred_delivery_time']) ?></p>
        <?php endif; ?>
      </div>
      <?php if ($deliveryStatus): ?>
        <div style="margin-top:1rem;">
          <h3>Rider</h3>
          <p class="text-muted">
            <?= e($rider['name'] ?? 'Unassigned') ?><br>
            <?= e($rider['phone_number'] ?? '') ?><br>
            <?= e($rider['bike_registration'] ?? '') ?>
          </p>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</div>

<?php renderDashboardChromeEnd(); ?>
