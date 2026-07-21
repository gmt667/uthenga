<?php
/**
 * Uthenga - Customer Shop Orders
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/shop_helpers.php';
requireCustomer();

$activeNav = 'shop';
$userId = (string) ($_SESSION['user_id'] ?? '');
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action = (string) ($_POST['action'] ?? '');
    $orderId = (int) ($_POST['order_id'] ?? 0);

    if ($action === 'cancel') {
        $result = uthenga_shop_cancel_order($orderId, $userId);
        if (!empty($result['ok'])) {
            $success = $result['message'] ?? 'Order cancelled.';
        } else {
            $error = $result['message'] ?? 'Unable to cancel the order.';
        }
    }
}

$orders = uthenga_table_exists('shop_orders')
    ? dbQuery('SELECT * FROM shop_orders WHERE user_id = ? ORDER BY placed_at DESC, id DESC', [$userId])
    : [];

$totals = [
    'orders' => count($orders),
    'pending' => 0,
    'delivered' => 0,
    'spent' => 0,
];

foreach ($orders as $order) {
    $status = strtolower((string) ($order['order_status'] ?? ''));
    if (in_array($status, ['pending', 'confirmed', 'preparing', 'assigned_to_rider', 'out_for_delivery'], true)) {
        $totals['pending']++;
    }
    if ($status === 'delivered') {
        $totals['delivered']++;
    }
    $totals['spent'] += (float) ($order['total_amount'] ?? 0);
}

require_once __DIR__ . '/includes/dashboard_shell.php';
renderDashboardChromeStart([
    'role' => ROLE_CUSTOMER,
    'title' => 'My Orders',
    'active' => 'shop-orders.php',
    'search' => false,
    'status' => 'Customer Account',
]);
?>
<style>
  .orders-grid { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .9rem; margin: 1rem 0 1.25rem; }
  .order-card { padding:1rem; border:1px solid var(--clr-border); border-radius:20px; background:var(--clr-surface); }
  .order-card span { display:block; color:var(--clr-text-muted); font-size:.74rem; text-transform:uppercase; letter-spacing:.06em; }
  .order-card strong { display:block; margin-top:.15rem; font-size:1.2rem; }
  .order-list { display:grid; gap:1rem; }
  .order-item { padding:1rem; border:1px solid var(--clr-border); border-radius:22px; background:var(--clr-surface); }
  .order-head { display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; align-items:flex-start; }
  .order-meta { display:flex; gap:.75rem; flex-wrap:wrap; color:var(--clr-text-muted); font-size:.82rem; }
  .order-items { margin-top:.9rem; display:grid; gap:.5rem; }
  .order-item-row { display:flex; justify-content:space-between; gap:1rem; font-size:.9rem; }
  .order-actions { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:1rem; }
  @media (max-width: 960px) { .orders-grid { grid-template-columns:1fr 1fr; } }
  @media (max-width: 640px) { .orders-grid { grid-template-columns:1fr; } }
</style>

<div class="container">
  <div style="padding:2rem 0 1rem;">
    <div class="page-header">
      <div>
        <h1 class="page-title">My Orders</h1>
        <p class="text-muted">Track shop purchases, delivery progress, and receipts in one place.</p>
      </div>
      <div class="dashboard-head-meta">
        <a href="<?= BASE_URL ?>shop.php" class="btn btn-secondary btn-sm">Shop</a>
        <a href="<?= BASE_URL ?>shop-cart.php" class="btn btn-primary btn-sm">Cart</a>
      </div>
    </div>
  </div>

  <?php if ($success !== '' || $error !== ''): ?>
    <div class="glass-panel" style="padding:1rem;margin-bottom:1rem;border-left:4px solid <?= $error !== '' ? 'var(--clr-red)' : 'var(--clr-green)' ?>;">
      <strong><?= e($error !== '' ? $error : $success) ?></strong>
    </div>
  <?php endif; ?>

  <div class="orders-grid">
    <div class="order-card"><span>Total Orders</span><strong><?= number_format($totals['orders']) ?></strong></div>
    <div class="order-card"><span>Pending / Active</span><strong><?= number_format($totals['pending']) ?></strong></div>
    <div class="order-card"><span>Delivered</span><strong><?= number_format($totals['delivered']) ?></strong></div>
    <div class="order-card"><span>Total Spent</span><strong><?= uthenga_shop_money((float) $totals['spent']) ?></strong></div>
  </div>

  <div class="order-list">
    <?php if (empty($orders)): ?>
      <div class="glass-panel" style="padding:1.25rem;">
        <h3>No orders yet</h3>
        <p class="text-muted">Once you place a Shop order, it will appear here with its invoice and delivery status.</p>
        <a href="<?= BASE_URL ?>shop.php" class="btn btn-primary">Browse Products</a>
      </div>
    <?php else: ?>
      <?php foreach ($orders as $order): ?>
        <?php $items = uthenga_shop_order_items((int) $order['id']); ?>
        <article class="order-item">
          <div class="order-head">
            <div>
              <div class="section-label">Order <?= e($order['order_number']) ?></div>
              <h3 style="margin-top:.3rem;"><?= uthenga_shop_money((float) $order['total_amount']) ?></h3>
              <div class="order-meta">
                <span><?= e($order['payment_method']) ?></span>
                <span class="badge <?= uthenga_shop_status_badge((string) $order['order_status']) ?>"><?= e($order['order_status']) ?></span>
                <span class="badge <?= uthenga_shop_status_badge((string) $order['payment_status']) ?>"><?= e($order['payment_status']) ?></span>
                <span><?= e($order['placed_at']) ?></span>
              </div>
            </div>
            <a href="<?= BASE_URL ?>shop-order.php?order=<?= urlencode((string) $order['order_number']) ?>" class="btn btn-secondary btn-sm">View Receipt</a>
          </div>
          <div class="order-items">
            <?php foreach (array_slice($items, 0, 4) as $item): ?>
              <div class="order-item-row">
                <span><?= e($item['product_name']) ?> x <?= (int) $item['quantity'] ?></span>
                <strong><?= uthenga_shop_money((float) $item['line_total']) ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="order-actions">
            <a href="<?= BASE_URL ?>shop-order.php?order=<?= urlencode((string) $order['order_number']) ?>" class="btn btn-primary btn-sm">Open Order</a>
            <?php if (in_array(strtolower((string) $order['order_status']), ['pending', 'confirmed', 'preparing'], true)): ?>
              <form method="post" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Cancel Order</button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php renderDashboardChromeEnd(); ?>
