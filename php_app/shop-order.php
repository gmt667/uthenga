<?php
/**
 * Uthenga - Shop Order Receipt
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/shop_helpers.php';
requireCustomer();

$activeNav = 'shop';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$orderNumber = trim((string) ($_GET['order'] ?? ''));
$order = $orderNumber !== '' ? uthenga_shop_order_by_number($orderNumber) : null;

if (!$order || (int) ($order['user_id'] ?? 0) !== $userId) {
    redirect(BASE_URL . 'shop-orders.php');
}

$items = uthenga_shop_order_items((int) $order['id']);
$payment = uthenga_shop_payment_by_order_id((int) $order['id']);
$deliveryStatus = dbQueryOne('SELECT * FROM shop_deliveries WHERE order_id = ? LIMIT 1', [(int) $order['id']]);
$rider = null;
if (!empty($deliveryStatus['rider_id'])) {
    $rider = dbQueryOne('SELECT * FROM delivery_riders WHERE id = ? LIMIT 1', [(int) $deliveryStatus['rider_id']]);
}

$success = !empty($_SESSION['shop_order_success']) && $_SESSION['shop_order_success'] === $orderNumber;
unset($_SESSION['shop_order_success']);

$downloadMode = (string) ($_GET['download'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'cancel') {
        $result = uthenga_shop_cancel_order((int) $order['id'], (string) $userId);
        if (!empty($result['ok'])) {
            redirect(BASE_URL . 'shop-order.php?order=' . urlencode($orderNumber));
        }
    }
}

if ($downloadMode !== '') {
    $safeOrderNumber = preg_replace('/[^A-Z0-9\-]/i', '', $orderNumber) ?: 'receipt';
    $downloadFile = 'uthenga-receipt-' . $safeOrderNumber . ($downloadMode === 'pdf' ? '.pdf' : '.html');
    $paymentLabel = $payment['provider'] ?? $payment['payment_method'] ?? ($order['payment_method'] ?? 'N/A');
    if ($downloadMode === 'pdf') {
        header('Content-Type: application/pdf');
        $pdf = uthenga_shop_generate_receipt_pdf($order, $items, $payment, $deliveryStatus, $rider);
        header('Content-Disposition: attachment; filename="' . $downloadFile . '"');
        echo $pdf;
        exit;
    }

    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $downloadFile . '"');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Receipt <?= e($order['order_number']) ?> | <?= APP_NAME ?></title>
  <style>
    :root {
      --bg: #f8fafc;
      --panel: #fff;
      --line: #dbe4ee;
      --text: #102033;
      --muted: #64748b;
      --accent: #0ea5e9;
      --success: #16a34a;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background: linear-gradient(180deg, #f8fafc, #eef3f8);
      color: var(--text);
    }
    .page {
      max-width: 920px;
      margin: 0 auto;
      padding: 24px;
    }
    .card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 20px;
      box-shadow: 0 12px 40px rgba(15, 23, 42, .08);
      padding: 24px;
      margin-bottom: 18px;
    }
    .header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; }
    .eyebrow {
      display:inline-block;
      color:var(--accent);
      font-size:12px;
      font-weight:700;
      letter-spacing:.08em;
      text-transform:uppercase;
      margin-bottom:8px;
    }
    h1, h2, h3, p { margin:0; }
    h1 { font-size:28px; line-height:1.15; margin-bottom:6px; }
    h2 { font-size:20px; margin-bottom:12px; }
    .muted { color: var(--muted); line-height:1.5; }
    .badges { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
    .badge {
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:6px 10px;
      border-radius:999px;
      background:#eff6ff;
      color:#0369a1;
      font-size:12px;
      font-weight:700;
    }
    .grid {
      display:grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(260px, .9fr);
      gap:18px;
    }
    .lines { display:grid; gap:8px; }
    .line {
      display:flex;
      justify-content:space-between;
      gap:12px;
      padding:8px 0;
      border-bottom:1px solid var(--line);
    }
    .line:last-child { border-bottom:0; font-weight:800; font-size:16px; }
    .timeline { display:grid; gap:10px; margin-top:14px; }
    .step {
      border:1px solid var(--line);
      background:#f8fbff;
      border-radius:14px;
      padding:12px 14px;
    }
    .step strong { display:block; margin-bottom:4px; }
    .footer-note { color:var(--muted); font-size:13px; line-height:1.5; }
    .receipt-id { font-size:13px; color:var(--muted); }
    @media (max-width: 768px) {
      .page { padding:14px; }
      .card { padding:18px; border-radius:16px; }
      .grid { grid-template-columns:1fr; }
      .line { font-size:14px; }
      h1 { font-size:22px; }
    }
    @media print {
      body { background:#fff; }
      .page { padding:0; }
      .card { box-shadow:none; border-radius:0; page-break-inside:avoid; }
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="card">
      <div class="header">
        <div>
          <div class="eyebrow">Receipt</div>
          <h1>Uthenga Shop</h1>
          <p class="muted">Order receipt for <?= e($order['customer_name']) ?></p>
        </div>
        <div class="receipt-id">
          <strong>Receipt No:</strong> <?= e($order['order_number']) ?><br>
          <strong>Order Date:</strong> <?= e($order['placed_at']) ?>
        </div>
      </div>
      <div class="badges">
        <span class="badge"><?= e($paymentLabel) ?></span>
        <span class="badge"><?= e(uthenga_shop_status_label((string) $order['order_status'])) ?></span>
        <span class="badge"><?= e(uthenga_shop_status_label((string) $order['payment_status'])) ?></span>
      </div>
    </div>

    <div class="grid">
      <section class="card">
        <h2>Order Summary</h2>
        <p class="muted" style="margin-bottom:14px;">Customer: <?= e($order['customer_name']) ?></p>
        <div class="lines">
          <?php foreach ($items as $item): ?>
            <div class="line"><span><?= e($item['product_name']) ?> x <?= (int) $item['quantity'] ?></span><strong><?= uthenga_shop_money((float) $item['line_total']) ?></strong></div>
          <?php endforeach; ?>
          <div class="line"><span>Subtotal</span><strong><?= uthenga_shop_money((float) $order['subtotal']) ?></strong></div>
          <div class="line"><span>Delivery Fee</span><strong><?= uthenga_shop_money((float) $order['delivery_fee']) ?></strong></div>
          <div class="line"><span>Discount</span><strong>-<?= uthenga_shop_money((float) $order['discount_amount']) ?></strong></div>
          <div class="line"><span>Total</span><strong><?= uthenga_shop_money((float) $order['total_amount']) ?></strong></div>
        </div>
        <div style="margin-top:16px;" class="footer-note">
          Generated from the live Uthenga shop order data.
        </div>
      </section>

      <aside class="card">
        <h2>Delivery & Tracking</h2>
        <div class="timeline">
          <div class="step"><strong>Placed</strong><span class="muted"><?= e($order['placed_at']) ?></span></div>
          <div class="step"><strong>Confirmed</strong><span class="muted"><?= e($order['confirmed_at'] ?: 'Pending') ?></span></div>
          <div class="step"><strong>Prepared</strong><span class="muted"><?= e($order['prepared_at'] ?: 'Pending') ?></span></div>
          <div class="step"><strong>Dispatched</strong><span class="muted"><?= e($order['dispatched_at'] ?: 'Pending') ?></span></div>
          <div class="step"><strong>Delivered</strong><span class="muted"><?= e($order['delivered_at'] ?: 'Pending') ?></span></div>
        </div>
        <div style="margin-top:16px;">
          <h3 style="margin-bottom:8px;">Delivery Address</h3>
          <p class="muted"><?= nl2br(e($order['delivery_address'])) ?></p>
          <?php if (!empty($order['delivery_instructions'])): ?>
            <p class="muted" style="margin-top:8px;"><strong>Instructions:</strong> <?= nl2br(e($order['delivery_instructions'])) ?></p>
          <?php endif; ?>
          <?php if (!empty($order['preferred_delivery_time'])): ?>
            <p class="muted" style="margin-top:8px;"><strong>Preferred time:</strong> <?= e($order['preferred_delivery_time']) ?></p>
          <?php endif; ?>
        </div>
      </aside>
    </div>
  </div>
</body>
</html>
    <?php
    exit;
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
  .receipt-card { padding:1.65rem; border:1px solid var(--clr-border); border-radius:24px; background:var(--clr-surface); box-shadow: var(--shadow-md); }
  .receipt-grid { display:grid; grid-template-columns: minmax(0, 1.1fr) minmax(280px, .9fr); gap:1.4rem; }
  .receipt-lines { display:grid; gap:.65rem; }
  .receipt-line { display:flex; justify-content:space-between; gap:1rem; padding:.45rem 0; border-bottom:1px solid var(--clr-border); line-height:1.35; }
  .receipt-line:last-child { border-bottom:none; }
  .timeline { display:grid; gap:.85rem; }
  .timeline-step { padding:.85rem 1rem; border:1px solid var(--clr-border); border-radius:16px; background:var(--clr-surface2); line-height:1.35; }
  .timeline-step strong { display:block; }
  .print-bar { display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1.15rem; }
  @media (max-width: 960px) { .receipt-grid { grid-template-columns:1fr; } }
  @media (max-width: 768px) {
    .receipt-shell {
      padding: 1.25rem 0 2rem;
    }

    .receipt-card {
      padding: 1.05rem;
      border-radius: 20px;
    }

    .receipt-grid {
      gap: 1.1rem;
    }

    .receipt-line {
      gap: .65rem;
      padding: .38rem 0;
      font-size: .88rem;
    }

    .timeline {
      gap: .65rem;
    }

    .timeline-step {
      padding: .72rem .9rem;
      border-radius: 14px;
    }

    .print-bar .btn,
    .print-bar form {
      width: 100%;
    }

    .print-bar .btn {
      justify-content: center;
    }

    .dashboard-head-meta {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      width: 100%;
    }

    .dashboard-head-meta .btn {
      flex: 1 1 0;
      min-width: 0;
    }
  }
  @media (max-width: 480px) {
    .receipt-card {
      padding: .95rem;
      border-radius: 18px;
    }

    .receipt-card h2,
    .receipt-card h3 {
      font-size: 1.08rem;
    }

    .receipt-line {
      font-size: .83rem;
    }

    .detail-badges {
      gap: .35rem;
    }

    .detail-badge {
      padding: .24rem .5rem;
      font-size: .68rem;
    }

    .timeline-step strong {
      font-size: .92rem;
    }
  }
  @media (max-width: 320px) {
    .receipt-shell {
      padding: 1rem 0 1.5rem;
    }

    .receipt-card {
      padding: .8rem;
      border-radius: 16px;
    }

    .page-header {
      gap: .65rem;
    }

    .page-header .page-title {
      font-size: 1.1rem;
      line-height: 1.15;
    }

    .page-header .text-muted {
      font-size: .8rem;
      line-height: 1.4;
    }

    .dashboard-head-meta {
      gap: .4rem;
    }

    .dashboard-head-meta .btn,
    .print-bar .btn,
    .print-bar form {
      width: 100%;
    }

    .dashboard-head-meta .btn,
    .print-bar .btn {
      min-height: 38px;
      font-size: .8rem;
      padding: .48rem .7rem;
    }

    .receipt-line {
      gap: .5rem;
      padding: .28rem 0;
      font-size: .78rem;
    }

    .receipt-line strong {
      font-size: .8rem;
    }

    .detail-badge {
      padding: .2rem .45rem;
      font-size: .64rem;
    }

    .timeline-step {
      padding: .6rem .75rem;
      border-radius: 12px;
    }

    .timeline-step strong {
      font-size: .84rem;
    }

    .timeline-step .text-muted {
      font-size: .74rem;
    }
  }
  @media (max-width: 360px) {
    .receipt-shell {
      padding: .9rem 0 1.35rem;
    }

    .receipt-grid {
      gap: .85rem;
    }

    .receipt-card {
      padding: .8rem;
      border-radius: 16px;
    }

    .receipt-card h2,
    .receipt-card h3 {
      font-size: 1rem;
      line-height: 1.2;
    }

    .receipt-line {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: .15rem;
      padding: .28rem 0;
      font-size: .78rem;
    }

    .receipt-line strong {
      justify-self: end;
      font-size: .8rem;
    }

    .timeline {
      gap: .5rem;
    }

    .timeline-step {
      padding: .58rem .72rem;
      border-radius: 12px;
    }

    .timeline-step .text-muted {
      font-size: .74rem;
    }

    .print-bar {
      gap: .45rem;
    }

    .print-bar .btn,
    .print-bar form {
      width: 100%;
    }
  }
  @media (max-width: 320px) {
    .receipt-shell {
      padding: .8rem 0 1.2rem;
    }

    .receipt-card {
      padding: .75rem;
      border-radius: 14px;
    }

    .page-header {
      gap: .55rem;
    }

    .page-header .page-title {
      font-size: 1rem;
      line-height: 1.15;
    }

    .page-header .text-muted {
      font-size: .74rem;
      line-height: 1.4;
    }

    .dashboard-head-meta {
      gap: .35rem;
    }

    .dashboard-head-meta .btn,
    .print-bar .btn {
      min-height: 36px;
      font-size: .76rem;
      padding: .45rem .62rem;
    }

    .detail-badges {
      gap: .28rem;
    }

    .detail-badge {
      padding: .16rem .38rem;
      font-size: .6rem;
    }

    .receipt-line {
      padding: .24rem 0;
      font-size: .74rem;
    }

    .receipt-line strong {
      font-size: .76rem;
    }

    .timeline-step {
      padding: .55rem .68rem;
    }

    .timeline-step strong {
      font-size: .8rem;
    }

    .timeline-step .text-muted {
      font-size: .7rem;
    }
  }
</style>

<div class="container receipt-shell">
  <?php if ($success): ?>
    <div class="glass-panel" style="padding:1rem;margin-bottom:1rem;border-left:4px solid var(--clr-green);">
      <strong>Your order has been placed successfully.</strong>
    </div>
  <?php endif; ?>

  <div class="page-header">
    <div>
      <h1 class="page-title">Receipt <?= e($order['order_number']) ?></h1>
      <p class="text-muted">Invoice and delivery details for your Shop order.</p>
    </div>
      <div class="dashboard-head-meta">
        <a href="<?= BASE_URL ?>shop-orders.php" class="btn btn-secondary btn-sm">Back to Orders</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print Receipt</button>
        <a href="<?= BASE_URL ?>shop-order.php?order=<?= urlencode($orderNumber) ?>&download=pdf" class="btn btn-secondary btn-sm">Download PDF</a>
      </div>
    </div>

  <div class="receipt-grid">
    <section class="receipt-card">
      <div class="section-label">Order Summary</div>
      <h2 style="margin-top:.3rem;"><?= e($order['customer_name']) ?></h2>
      <div class="detail-badges" style="margin-top:.75rem;">
        <span class="detail-badge"><?= e(uthenga_shop_payment_method_label((string) $order['payment_method'])) ?></span>
        <span class="detail-badge <?= uthenga_shop_status_badge((string) $order['order_status']) ?>"><?= e(uthenga_shop_status_label((string) $order['order_status'])) ?></span>
        <span class="detail-badge <?= uthenga_shop_status_badge((string) $order['payment_status']) ?>"><?= e(uthenga_shop_status_label((string) $order['payment_status'])) ?></span>
      </div>
      <div class="muted" style="margin-top:.65rem;line-height:1.45;">
        <strong>Order:</strong> <?= e(uthenga_shop_status_hint((string) $order['order_status'])) ?><br>
        <strong>Payment:</strong> <?= e(uthenga_shop_status_hint((string) $order['payment_status'])) ?>
      </div>
      <?php if ($payment): ?>
        <div class="glass-panel" style="padding:0.9rem;margin-top:1rem;">
          <div class="section-label">Payment Details</div>
          <div class="receipt-lines" style="margin-top:.65rem;">
            <div class="receipt-line"><span>Provider</span><strong><?= e($payment['provider'] ?? $payment['payment_method'] ?? 'N/A') ?></strong></div>
            <div class="receipt-line"><span>Reference</span><strong><?= e($payment['payment_reference'] ?? 'N/A') ?></strong></div>
            <div class="receipt-line"><span>Status</span><strong><?= e(uthenga_shop_status_label((string) ($payment['payment_status'] ?? 'pending'))) ?></strong></div>
          </div>
          <p class="muted" style="margin-top:.65rem;"><?= e(uthenga_shop_status_hint((string) ($payment['payment_status'] ?? 'pending'))) ?></p>
        </div>
      <?php endif; ?>
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
