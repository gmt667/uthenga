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
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $customerName = trim((string) ($_POST['customer_name'] ?? ($_SESSION['user_name'] ?? '')));
        $customerEmail = trim((string) ($_POST['customer_email'] ?? ($_SESSION['user_email'] ?? '')));
        $customerPhone = trim((string) ($_POST['customer_phone'] ?? ($user['phone'] ?? '')));
        $deliveryAddress = trim((string) ($_POST['delivery_address'] ?? ''));
        $deliveryInstructions = trim((string) ($_POST['delivery_instructions'] ?? ''));
        $preferredDeliveryTime = trim((string) ($_POST['preferred_delivery_time'] ?? ''));
        $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'cash_on_delivery'));

        if (strlen($customerName) < 2) {
            $error = 'Please enter your full name.';
        } elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^[0-9+()\-\s]{7,30}$/', $customerPhone)) {
            $error = 'Please enter a valid phone number.';
        } elseif (strlen($deliveryAddress) < 6) {
            $error = 'Please enter a full delivery address.';
        } elseif (!in_array($paymentMethod, array_keys(uthenga_shop_payment_methods_map()), true)) {
            $error = 'Please choose a valid payment method.';
        } else {
            $orderNumber = uthenga_shop_order_number();
            $userId = (string) ($_SESSION['user_id'] ?? '');
            $sessionToken = uthenga_shop_session_token();

            try {
                if (uthenga_db_is_available() && !empty($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
                    $GLOBALS['pdo']->beginTransaction();
                }

                dbExecute(
                    'INSERT INTO shop_orders (order_number, user_id, customer_name, customer_email, customer_phone, delivery_address, delivery_instructions, preferred_delivery_time, subtotal, delivery_fee, discount_amount, tax_amount, total_amount, currency, payment_method, payment_status, order_status, fulfillment_status, session_token, placed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    [
                        $orderNumber,
                        $userId !== '' ? $userId : null,
                        $customerName,
                        $customerEmail,
                        $customerPhone,
                        $deliveryAddress,
                        $deliveryInstructions !== '' ? $deliveryInstructions : null,
                        $preferredDeliveryTime !== '' ? $preferredDeliveryTime : null,
                        $totals['subtotal'],
                        $totals['delivery_fee'],
                        $totals['discount_amount'],
                        $totals['tax_amount'],
                        $totals['total'],
                        APP_CURRENCY,
                        $paymentMethod,
                        'pending',
                        'pending',
                        'pending',
                        $sessionToken,
                    ]
                );

                $orderId = (int) dbLastId();
                foreach ($cartItems as $item) {
                    dbExecute(
                        'INSERT INTO shop_order_items (order_id, product_id, product_name, sku, unit_price, quantity, line_total, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        [
                            $orderId,
                            (int) ($item['id'] ?? 0),
                            (string) ($item['name'] ?? ''),
                            (string) ($item['sku'] ?? ''),
                            (float) ($item['unit_price'] ?? 0),
                            (int) ($item['quantity'] ?? 1),
                            (float) ($item['line_total'] ?? 0),
                            (uthenga_shop_product_image_urls($item)[0] ?? $item['primary_image_url'] ?? null),
                        ]
                    );

                    dbExecute(
                        'UPDATE shop_products SET stock_quantity = GREATEST(stock_quantity - ?, 0), status = CASE WHEN stock_quantity - ? <= 0 THEN "out_of_stock" ELSE status END WHERE id = ?',
                        [
                            (int) ($item['quantity'] ?? 1),
                            (int) ($item['quantity'] ?? 1),
                            (int) ($item['id'] ?? 0),
                        ]
                    );
                }

                dbExecute(
                    'INSERT INTO shop_payments (order_id, payment_method, provider, payment_reference, amount, currency, payment_status, gateway_payload, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $orderId,
                        $paymentMethod,
                        $paymentMethod === 'paychangu' ? 'PayChangu' : null,
                        $paymentMethod === 'bank_transfer' ? 'BANK-' . strtoupper(bin2hex(random_bytes(3))) : null,
                        $totals['total'],
                        APP_CURRENCY,
                        $paymentMethod === 'cash_on_delivery' ? 'pending' : 'pending',
                        null,
                        null,
                    ]
                );

                uthenga_shop_notify_user($userId !== '' ? $userId : (string) $_SESSION['user_id'], 'shop', 'Order Placed', 'Your order ' . $orderNumber . ' has been placed successfully.');
                uthenga_shop_notify_admins('New Shop Order', 'Order ' . $orderNumber . ' has been placed by ' . $customerName . '.');

                if (uthenga_db_is_available() && !empty($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && $GLOBALS['pdo']->inTransaction()) {
                    $GLOBALS['pdo']->commit();
                }

                uthenga_shop_cart_clear();
                $_SESSION['shop_order_success'] = $orderNumber;
                redirect(BASE_URL . 'shop-order.php?order=' . urlencode($orderNumber));
            } catch (Throwable $e) {
                if (uthenga_db_is_available() && !empty($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && $GLOBALS['pdo']->inTransaction()) {
                    $GLOBALS['pdo']->rollBack();
                }
                $error = 'Unable to place the order right now. Please try again.';
            }
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
