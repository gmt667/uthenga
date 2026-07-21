<?php
/**
 * Uthenga - Shopping Cart
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/shop_helpers.php';

$activeNav = 'shop';
$flashMessage = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $flashError = 'Security check failed. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 1);
        $returnTo = uthenga_safe_redirect_url((string) ($_POST['return_to'] ?? ''), BASE_URL . 'shop-cart.php');

        if ($action === 'add') {
            $result = uthenga_shop_cart_add($productId, $quantity);
            $flashMessage = $result['message'] ?? '';
        } elseif ($action === 'update') {
            $result = uthenga_shop_cart_update($productId, $quantity);
            $flashMessage = $result['message'] ?? '';
        } elseif ($action === 'remove') {
            uthenga_shop_cart_remove($productId);
            $flashMessage = 'Item removed from cart.';
        } elseif ($action === 'clear') {
            uthenga_shop_cart_clear();
            $flashMessage = 'Cart cleared.';
        }

        if ($flashMessage !== '' || $flashError !== '') {
            $_SESSION['shop_flash'] = ['message' => $flashMessage, 'error' => $flashError];
        }

        redirect($returnTo);
    }
}

$flash = $_SESSION['shop_flash'] ?? [];
unset($_SESSION['shop_flash']);
$cartItems = uthenga_shop_cart_items();
$totals = uthenga_shop_order_totals($cartItems);

require_once __DIR__ . '/includes/header.php';
?>
<style>
  .cart-layout { display:grid; grid-template-columns: minmax(0, 1.3fr) minmax(300px, .7fr); gap:1.25rem; padding: 2rem 0 3rem; }
  .cart-item { display:grid; grid-template-columns: 88px minmax(0, 1fr) auto; gap:1rem; padding:1rem; border:1px solid var(--clr-border); border-radius:20px; background:var(--clr-surface); }
  .cart-item img { width:88px; height:88px; border-radius:16px; object-fit:cover; }
  .cart-item h3 { margin:0 0 .15rem; }
  .cart-item-meta { display:flex; gap:.75rem; flex-wrap:wrap; color:var(--clr-text-muted); font-size:.8rem; }
  .cart-summary { padding:1.25rem; border:1px solid var(--clr-border); border-radius:24px; background:var(--clr-surface); position:sticky; top:84px; }
  .summary-row { display:flex; justify-content:space-between; gap:1rem; padding:.55rem 0; border-bottom:1px solid var(--clr-border); }
  .summary-row:last-child { border-bottom:none; font-weight:800; font-size:1.05rem; }
  .empty-cart {
    padding: 2rem;
    border: 1px dashed var(--clr-border2);
    border-radius: 24px;
    background: var(--clr-surface);
    text-align:center;
  }
  @media (max-width: 960px) {
    .cart-layout { grid-template-columns: 1fr; }
    .cart-summary { position: static; }
    .cart-item { grid-template-columns: 72px 1fr; }
    .cart-item .cart-actions { grid-column: 1/-1; }
  }
</style>

<div class="container">
  <div style="padding:2rem 0 1rem;">
    <div class="page-header">
      <div>
        <h1 class="page-title"><?= uthenga_public_icon_svg('cart') ?> Shopping Cart</h1>
        <p class="text-muted">Review items, adjust quantities, and proceed to delivery checkout.</p>
      </div>
      <div class="dashboard-head-meta">
        <a href="<?= BASE_URL ?>shop.php" class="btn btn-secondary btn-sm">Continue Shopping</a>
        <a href="<?= BASE_URL ?>shop-checkout.php" class="btn btn-primary btn-sm">Checkout</a>
      </div>
    </div>
  </div>

  <?php if (!empty($flash['message']) || !empty($flash['error'])): ?>
    <div class="glass-panel" style="padding:1rem;margin-bottom:1rem;border-left:4px solid <?= !empty($flash['error']) ? 'var(--clr-red)' : 'var(--clr-green)' ?>;">
      <strong><?= e($flash['error'] ?: $flash['message']) ?></strong>
    </div>
  <?php endif; ?>

  <div class="cart-layout">
    <div style="display:grid;gap:1rem;">
      <?php if (empty($cartItems)): ?>
        <div class="empty-cart">
          <h3>Your cart is empty</h3>
          <p class="text-muted">Browse the shop and add items to get started.</p>
          <a href="<?= BASE_URL ?>shop.php" class="btn btn-primary">Browse Products</a>
        </div>
      <?php else: ?>
        <?php foreach ($cartItems as $item): ?>
          <?php $thumbs = $item['image_urls'] ?? []; ?>
          <article class="cart-item">
            <img src="<?= e($thumbs[0] ?? $item['primary_image_url'] ?? '') ?>" alt="<?= e($item['name']) ?>">
            <div>
              <h3><?= e($item['name']) ?></h3>
              <div class="cart-item-meta">
                <span><?= e($item['category_name'] ?? 'Shop') ?></span>
                <span>SKU <?= e($item['sku']) ?></span>
                <span><?= e((string) ($item['stock_quantity'] ?? 0)) ?> in stock</span>
              </div>
              <div style="margin-top:.65rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <strong><?= uthenga_shop_money((float) $item['unit_price']) ?></strong>
                <span class="text-muted">Line total: <?= uthenga_shop_money((float) $item['line_total']) ?></span>
              </div>
            </div>
            <div class="cart-actions">
              <form method="post" action="<?= BASE_URL ?>shop-cart.php" class="grid" style="gap:.5rem;min-width:160px;">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? 'shop-cart.php') ?>">
                <label class="form-group" style="margin:0;">
                  <span class="form-label">Qty</span>
                  <input type="number" name="quantity" class="form-control" min="0" max="<?= (int) ($item['stock_quantity'] ?? 1) ?>" value="<?= (int) $item['quantity'] ?>">
                </label>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                  <button type="submit" class="btn btn-sm btn-secondary">Update</button>
                </div>
              </form>
              <form method="post" action="<?= BASE_URL ?>shop-cart.php" style="margin-top:.5rem;">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? 'shop-cart.php') ?>">
                <button type="submit" class="btn btn-sm btn-secondary" style="width:100%;">Remove</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <aside class="cart-summary">
      <div class="section-label">Order Summary</div>
      <h3 style="margin-top:.25rem;">Cart totals</h3>
      <div class="summary-row"><span>Subtotal</span><strong><?= uthenga_shop_money((float) $totals['subtotal']) ?></strong></div>
      <div class="summary-row"><span>Delivery fee</span><strong><?= uthenga_shop_money((float) $totals['delivery_fee']) ?></strong></div>
      <div class="summary-row"><span>Tax</span><strong><?= uthenga_shop_money((float) $totals['tax_amount']) ?></strong></div>
      <div class="summary-row"><span>Discount</span><strong>-<?= uthenga_shop_money((float) $totals['discount_amount']) ?></strong></div>
      <div class="summary-row"><span>Total</span><strong><?= uthenga_shop_money((float) $totals['total']) ?></strong></div>
      <div style="display:grid;gap:.65rem;margin-top:1rem;">
        <a href="<?= BASE_URL ?>shop-checkout.php" class="btn btn-primary" <?= empty($cartItems) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Proceed to Checkout</a>
        <form method="post" action="<?= BASE_URL ?>shop-cart.php" style="margin:0;">
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
          <input type="hidden" name="action" value="clear">
          <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? 'shop-cart.php') ?>">
          <button type="submit" class="btn btn-secondary" style="width:100%;" <?= empty($cartItems) ? 'disabled' : '' ?>>Clear Cart</button>
        </form>
      </div>
      <p class="text-xs text-muted" style="margin-top:1rem;">Delivery fees are calculated from shop settings and can be adjusted by administrators.</p>
    </aside>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
