<?php
/**
 * Uthenga - Checkout
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/shop_helpers.php';
requireCustomer();

$activeNav = 'shop';
$cartItems = uthenga_shop_cart_items();
if (empty($cartItems)) {
    redirect(BASE_URL . 'shop-cart.php');
}

$settings = uthenga_shop_settings();
$totals = uthenga_shop_order_totals($cartItems);
$methods = uthenga_shop_payment_methods();
$user = currentUser() ?: [];
$userId = (string) ($_SESSION['user_id'] ?? '');
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $result = uthenga_shop_create_order_from_cart($cartItems, [
            'customer_name'           => $_POST['customer_name'] ?? ($_SESSION['user_name'] ?? ''),
            'customer_email'          => $_POST['customer_email'] ?? ($_SESSION['user_email'] ?? ''),
            'customer_phone'          => $_POST['customer_phone'] ?? ($user['phone'] ?? ''),
            'delivery_address'        => $_POST['delivery_address'] ?? '',
            'delivery_instructions'   => $_POST['delivery_instructions'] ?? '',
            'preferred_delivery_time' => $_POST['preferred_delivery_time'] ?? '',
            'payment_method'          => $_POST['payment_method'] ?? 'cash_on_delivery',
            'user_id'                 => $userId,
        ]);

        if (!$result['success']) {
            $error = $result['error'];
        } else {
            // For "pay_online", the order is created Pending — shop-order.php
            // opens the real Uthenga Checkout modal from here; nothing is
            // marked paid until UthengaPaymentEngine verifies a real payment.
            $_SESSION['shop_order_success'] = $result['order_number'];
            redirect(BASE_URL . 'shop-order.php?order=' . urlencode($result['order_number']));
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
  .checkout-layout { display:grid; grid-template-columns: minmax(0, 1.2fr) minmax(300px, .8fr); gap:1.25rem; padding:2rem 0 3rem; }
  .checkout-panel, .checkout-summary { padding:1.25rem; border:1px solid var(--clr-border); border-radius:24px; background:var(--clr-surface); }
  .checkout-summary { position:sticky; top:84px; height: fit-content; }
  .field-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: .9rem; }
  .summary-line { display:flex; justify-content:space-between; gap:1rem; padding:.55rem 0; border-bottom:1px solid var(--clr-border); }
  .summary-line:last-child { border-bottom:none; font-weight:800; font-size:1.05rem; }
  @media (max-width: 960px) {
    .checkout-layout { grid-template-columns: 1fr; }
    .checkout-summary { position: static; }
    .field-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 768px) {
    .checkout-layout {
      gap: 1rem;
      padding: 1.25rem 0 2rem;
    }

    .checkout-panel,
    .checkout-summary {
      padding: 1rem;
      border-radius: 20px;
    }

    .field-grid {
      gap: .75rem;
    }

    .checkout-panel .product-actions {
      width: 100%;
      flex-direction: column;
      align-items: stretch;
    }

    .checkout-panel .product-actions .btn {
      width: 100%;
      justify-content: center;
    }

    .summary-line {
      gap: .75rem;
      padding: .5rem 0;
      font-size: .88rem;
    }

    .checkout-summary {
      order: -1;
    }
  }
  @media (max-width: 480px) {
    .checkout-panel,
    .checkout-summary {
      padding: .9rem;
      border-radius: 18px;
    }

    .checkout-panel h3,
    .checkout-summary h3 {
      font-size: 1.05rem;
    }

    .form-control,
    .btn {
      min-height: 42px;
    }

    .checkout-panel textarea.form-control {
      min-height: 92px;
    }

    .summary-line:last-child {
      font-size: .98rem;
    }
  }
  @media (max-width: 360px) {
    .checkout-layout {
      gap: .85rem;
      padding: 1rem 0 1.6rem;
    }

    .checkout-panel,
    .checkout-summary {
      padding: .8rem;
      border-radius: 16px;
    }

    .page-header {
      gap: .6rem;
    }

    .page-header .page-title {
      font-size: 1.16rem;
      line-height: 1.15;
    }

    .page-header .text-muted {
      font-size: .8rem;
      line-height: 1.4;
    }

    .checkout-panel h3,
    .checkout-summary h3 {
      font-size: 1rem;
    }

    .field-grid {
      gap: .65rem;
    }

    .summary-line {
      gap: .5rem;
      padding: .42rem 0;
      font-size: .8rem;
    }

    .summary-line span,
    .summary-line strong {
      min-width: 0;
      word-break: break-word;
    }

    .product-actions {
      gap: .4rem;
    }

    .product-actions .btn {
      min-height: 38px;
      font-size: .8rem;
      padding-inline: .7rem;
    }
  }
  @media (max-width: 320px) {
    .checkout-layout {
      gap: .75rem;
      padding: .9rem 0 1.4rem;
    }

    .checkout-panel,
    .checkout-summary {
      padding: .72rem;
      border-radius: 14px;
    }

    .page-header .page-title {
      font-size: 1.05rem;
    }

    .page-header .text-muted {
      font-size: .75rem;
    }

    .form-control,
    .btn {
      min-height: 38px;
      font-size: .78rem;
    }

    .checkout-panel textarea.form-control {
      min-height: 84px;
    }

    .summary-line {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: .15rem;
      padding: .35rem 0;
      font-size: .75rem;
    }

    .summary-line strong {
      justify-self: end;
      font-size: .78rem;
    }

    .checkout-summary .glass-panel {
      padding: .7rem !important;
    }

    .product-actions .btn {
      min-height: 36px;
      padding-inline: .65rem;
    }
  }
</style>

<div class="container">
  <div style="padding:2rem 0 1rem;">
    <div class="page-header">
      <div>
        <h1 class="page-title">Checkout</h1>
        <p class="text-muted">Complete your delivery details and choose how you would like to pay.</p>
      </div>
      <div class="dashboard-head-meta">
        <a href="<?= BASE_URL ?>shop-cart.php" class="btn btn-secondary btn-sm">Back to Cart</a>
      </div>
    </div>
  </div>

  <?php if ($error !== ''): ?>
    <div class="glass-panel" style="padding:1rem;margin-bottom:1rem;border-left:4px solid var(--clr-red);"><strong><?= e($error) ?></strong></div>
  <?php endif; ?>

  <div class="checkout-layout">
    <section class="checkout-panel">
      <div class="section-label">Delivery Details</div>
      <h3 style="margin-top:.25rem;">Where should we deliver?</h3>
      <form method="post" class="grid" style="gap:1rem;margin-top:1rem;">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
        <div class="field-grid">
          <label class="form-group">
            <span class="form-label">Full Name</span>
            <input type="text" name="customer_name" class="form-control" value="<?= e($user['name'] ?? ($_SESSION['user_name'] ?? '')) ?>" required>
          </label>
          <label class="form-group">
            <span class="form-label">Email Address</span>
            <input type="email" name="customer_email" class="form-control" value="<?= e($user['email'] ?? ($_SESSION['user_email'] ?? '')) ?>" required>
          </label>
        </div>
        <div class="field-grid">
          <label class="form-group">
            <span class="form-label">Phone Number</span>
            <input type="text" name="customer_phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" required>
          </label>
          <label class="form-group">
            <span class="form-label">Preferred Delivery Time</span>
            <input type="text" name="preferred_delivery_time" class="form-control" placeholder="e.g. Today after 5:00 PM">
          </label>
        </div>
        <label class="form-group">
          <span class="form-label">Delivery Address</span>
          <textarea name="delivery_address" class="form-control" rows="3" required placeholder="House number, street, area, city"></textarea>
        </label>
        <label class="form-group">
          <span class="form-label">Delivery Instructions</span>
          <textarea name="delivery_instructions" class="form-control" rows="3" placeholder="Gate code, landmarks, or rider notes"></textarea>
        </label>
        <label class="form-group">
          <span class="form-label">Payment Method</span>
          <select name="payment_method" class="form-control" required>
            <?php foreach (uthenga_shop_payment_methods_map() as $value => $label): ?>
              <option value="<?= e($value) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted" style="display:block;margin-top:.45rem;line-height:1.45;">
            Pay Online supports Bank Transfer, Airtel Money and TNM Mpamba through the secure Uthenga Checkout.
          </small>
        </label>
        <div class="product-actions">
          <button type="submit" class="btn btn-primary">Place Order</button>
          <a href="<?= BASE_URL ?>shop.php" class="btn btn-secondary">Continue Shopping</a>
        </div>
      </form>
    </section>

    <aside class="checkout-summary">
      <div class="section-label">Cart Summary</div>
      <h3 style="margin-top:.25rem;">Order total</h3>
      <?php foreach ($cartItems as $item): ?>
        <div class="summary-line" style="font-size:.9rem;">
          <span><?= e($item['name']) ?> x <?= (int) $item['quantity'] ?></span>
          <strong><?= uthenga_shop_money((float) $item['line_total']) ?></strong>
        </div>
      <?php endforeach; ?>
      <div class="summary-line"><span>Subtotal</span><strong><?= uthenga_shop_money((float) $totals['subtotal']) ?></strong></div>
      <div class="summary-line"><span>Delivery fee</span><strong><?= uthenga_shop_money((float) $totals['delivery_fee']) ?></strong></div>
      <div class="summary-line"><span>Tax</span><strong><?= uthenga_shop_money((float) $totals['tax_amount']) ?></strong></div>
      <div class="summary-line"><span>Discount</span><strong>-<?= uthenga_shop_money((float) $totals['discount_amount']) ?></strong></div>
      <div class="summary-line"><span>Total</span><strong><?= uthenga_shop_money((float) $totals['total']) ?></strong></div>
      <div style="margin-top:1rem;" class="glass-panel">
        <p class="text-sm" style="margin:0;">Payments through online gateways can later plug into the same checkout flow without changing this form.</p>
      </div>
    </aside>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
