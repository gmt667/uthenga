<?php
/**
 * Uthenga - Shop homepage and product browser.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/shop_helpers.php';
require_once __DIR__ . '/includes/header.php';

$settings = uthenga_shop_settings();
$categories = uthenga_shop_category_tree();
$productSlug = trim((string) ($_GET['product'] ?? ''));
$categoryId = (int) ($_GET['category'] ?? 0);
$query = trim((string) ($_GET['q'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'featured');
$featuredOnly = !empty($_GET['featured']);
$newOnly = !empty($_GET['new']);
$bestOnly = !empty($_GET['best']);
$promotionOnly = !empty($_GET['promotion']);
$inStockOnly = !empty($_GET['stock']);

$filters = [
    'query' => $query,
    'category_id' => $categoryId > 0 ? $categoryId : null,
    'featured' => $featuredOnly,
    'new' => $newOnly,
    'best' => $bestOnly,
    'promotion' => $promotionOnly,
    'in_stock' => $inStockOnly,
    'sort' => $sort,
];

$cartItems = uthenga_shop_cart_items();
$cartTotals = uthenga_shop_order_totals($cartItems);
$featuredProducts = uthenga_shop_products(['featured' => true, 'limit' => 8, 'sort' => 'featured']);
$newArrivals = uthenga_shop_products(['new' => true, 'limit' => 8, 'sort' => 'newest']);
$bestSellers = uthenga_shop_products(['best' => true, 'limit' => 8, 'sort' => 'featured']);
$promoProducts = uthenga_shop_products(['promotion' => true, 'limit' => 8, 'sort' => 'featured']);
$products = $productSlug !== '' ? [] : uthenga_shop_products($filters);
$product = $productSlug !== '' ? uthenga_shop_product_by_slug($productSlug) : null;
$gallery = $product ? uthenga_shop_product_image_urls($product) : [];
$relatedProducts = $product ? uthenga_shop_products([
    'category_id' => (int) ($product['category_id'] ?? 0),
    'limit' => 6,
    'sort' => 'featured',
]) : [];

if ($product && !empty($relatedProducts)) {
    $relatedProducts = array_values(array_filter($relatedProducts, function ($item) use ($productSlug) {
        return (string) ($item['slug'] ?? '') !== $productSlug;
    }));
}

$activeNav = 'shop';
?>
<style>
  .shop-hero {
    padding: 2rem 0 1rem;
  }
  .shop-hero-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.8fr);
    gap: 1.25rem;
    align-items: stretch;
  }
  .shop-hero-card, .shop-side-card {
    background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,.82));
    border: 1px solid var(--clr-border);
    border-radius: 24px;
    box-shadow: var(--shadow-md);
    overflow: hidden;
  }
  html[data-theme="dark"] .shop-hero-card,
  html[data-theme="dark"] .shop-side-card {
    background: linear-gradient(180deg, rgba(17,24,39,.96), rgba(17,24,39,.88));
  }
  .shop-hero-card {
    padding: 2rem;
    position: relative;
  }
  .shop-hero-card::after {
    content: '';
    position: absolute;
    inset: auto -8rem -8rem auto;
    width: 18rem;
    height: 18rem;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(14,165,233,.16), transparent 65%);
    pointer-events: none;
  }
  .shop-hero-kicker {
    display: inline-flex;
    gap: .4rem;
    align-items: center;
    padding: .35rem .75rem;
    border-radius: 999px;
    background: var(--clr-accent-glow);
    color: var(--clr-accent);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    font-size: .72rem;
  }
  .shop-hero-actions {
    display: flex;
    gap: .75rem;
    flex-wrap: wrap;
    margin-top: 1.5rem;
  }
  .shop-hero-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .9rem;
    margin-top: 1.5rem;
  }
  .shop-stat {
    padding: 1rem;
    border: 1px solid var(--clr-border);
    border-radius: 18px;
    background: var(--clr-surface);
  }
  .shop-stat span { display:block; color: var(--clr-text-muted); font-size: .76rem; text-transform: uppercase; letter-spacing: .06em; }
  .shop-stat strong { display:block; margin-top:.2rem; font-size: 1.15rem; }
  .shop-side-card { padding: 1.25rem; }
  .shop-mini-list { display: grid; gap: .75rem; margin-top: 1rem; }
  .shop-mini-item {
    display:flex; gap:.75rem; align-items:center;
    padding:.75rem; border:1px solid var(--clr-border); border-radius:16px;
    background: var(--clr-surface);
  }
  .shop-mini-item img { width:56px; height:56px; object-fit:cover; border-radius:14px; flex:none; }
  .shop-mini-item strong { display:block; font-size:.92rem; }
  .shop-mini-item span { display:block; color:var(--clr-text-muted); font-size:.78rem; }
  .shop-section { padding: 1rem 0 2.5rem; }
  .shop-toolbar {
    display:grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 1rem;
    align-items: end;
    margin-bottom: 1.25rem;
  }
  .shop-filters {
    display:flex; flex-wrap:wrap; gap:.65rem; align-items:center;
  }
  .shop-filters a {
    padding:.45rem .8rem; border-radius:999px; border:1px solid var(--clr-border); color:var(--clr-text-soft); background:var(--clr-surface);
    font-size:.82rem; font-weight:600;
  }
  .shop-filters a.active { background: var(--clr-accent); color:#fff; border-color: transparent; }
  .product-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
  }
  .product-card {
    display:flex;
    flex-direction:column;
    overflow:hidden;
    border:1px solid var(--clr-border);
    border-radius: 22px;
    background: var(--clr-surface);
    box-shadow: var(--shadow-sm);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  }
  .product-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--clr-border2); }
  .product-image {
    position:relative;
    aspect-ratio: 1 / 1;
    background: var(--clr-surface2);
  }
  .product-image img { width:100%; height:100%; object-fit:cover; }
  .product-badge {
    position:absolute; top:.8rem; left:.8rem;
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.28rem .55rem; border-radius:999px; font-size:.68rem; font-weight:700;
    background: rgba(15,23,42,.8); color:#fff;
  }
  .product-body { padding: 1rem; display:flex; flex-direction:column; gap:.55rem; }
  .product-meta { display:flex; align-items:center; justify-content:space-between; gap:.5rem; font-size:.75rem; color:var(--clr-text-muted); }
  .product-title { margin:0; font-size:1rem; }
  .product-desc { margin:0; font-size:.82rem; color:var(--clr-text-muted); line-height:1.5; min-height: 2.4em; }
  .product-footer { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-top:auto; }
  .product-price { font-weight:800; font-size:1.02rem; }
  .product-stock { font-size:.74rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
  .product-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
  .shop-detail-grid { display:grid; grid-template-columns: minmax(0, 1.2fr) minmax(280px, .8fr); gap:1.25rem; margin-top:1rem; }
  .gallery-main { border-radius: 24px; overflow:hidden; border:1px solid var(--clr-border); background: var(--clr-surface); }
  .gallery-main img { width:100%; aspect-ratio: 1.05 / 1; object-fit:cover; }
  .gallery-thumbs { display:flex; gap:.5rem; overflow:auto; margin-top:.75rem; padding-bottom:.25rem; }
  .gallery-thumbs img { width:76px; height:76px; object-fit:cover; border-radius:14px; border:1px solid var(--clr-border); }
  .detail-panel { padding:1.25rem; border:1px solid var(--clr-border); border-radius:24px; background: var(--clr-surface); }
  .detail-badges { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:.85rem; }
  .detail-badge { display:inline-flex; padding:.3rem .6rem; border-radius:999px; background:var(--clr-surface2); font-size:.76rem; font-weight:700; }
  .related-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: .9rem; }
  @media (max-width: 960px) {
    .shop-hero-grid, .shop-detail-grid, .shop-toolbar { grid-template-columns: 1fr; }
    .shop-hero-stats { grid-template-columns: 1fr; }
  }
  @media (max-width: 768px) {
    .shop-hero {
      padding: 1.25rem 0 .75rem;
    }

    .shop-hero-card,
    .shop-side-card,
    .detail-panel {
      border-radius: 20px;
    }

    .shop-hero-card {
      padding: 1.25rem;
    }

    .shop-hero-actions {
      flex-direction: column;
      align-items: stretch;
    }

    .shop-hero-actions .btn {
      width: 100%;
      justify-content: center;
    }

    .shop-hero-stats {
      gap: .65rem;
      margin-top: 1rem;
    }

    .shop-mini-list {
      gap: .6rem;
    }

    .shop-mini-item {
      padding: .65rem;
      gap: .6rem;
    }

    .product-grid {
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: .85rem;
    }

    .product-body {
      padding: .85rem;
      gap: .45rem;
    }

    .product-desc {
      min-height: 0;
      font-size: .78rem;
    }

    .product-footer {
      flex-direction: column;
      align-items: flex-start;
      gap: .55rem;
    }

    .product-actions {
      width: 100%;
    }

    .product-actions .btn,
    .product-actions form {
      width: 100%;
      flex: 1 1 100%;
    }

    .product-actions .btn {
      justify-content: center;
    }

    .shop-toolbar > form > div {
      grid-template-columns: 1fr !important;
    }

    .shop-filters {
      gap: .45rem;
    }

    .shop-filters a {
      padding: .38rem .7rem;
      font-size: .76rem;
    }

    .gallery-main img {
      aspect-ratio: 4 / 3;
    }

    .gallery-thumbs {
      gap: .4rem;
    }

    .gallery-thumbs img {
      width: 64px;
      height: 64px;
      border-radius: 12px;
    }

    .detail-badges {
      gap: .4rem;
      margin-bottom: .7rem;
    }

    .related-grid {
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: .75rem;
    }
  }
  @media (max-width: 480px) {
    .shop-hero-card,
    .shop-side-card,
    .detail-panel {
      border-radius: 18px;
    }

    .shop-hero-card {
      padding: 1rem;
    }

    .shop-hero-kicker {
      font-size: .68rem;
      letter-spacing: .06em;
    }

    .shop-hero-actions .btn {
      min-height: 42px;
    }

    .product-grid {
      grid-template-columns: 1fr;
    }

    .product-image {
      aspect-ratio: 16 / 11;
    }

    .product-card {
      border-radius: 18px;
    }

    .related-grid {
      grid-template-columns: 1fr;
    }

    .shop-mini-item img {
      width: 50px;
      height: 50px;
    }

    .shop-stat strong {
      font-size: 1rem;
    }

    .detail-panel {
      padding: 1rem;
    }
  }
  @media (max-width: 420px) {
    .shop-hero {
      padding-top: .9rem;
    }

    .shop-hero-card h1 {
      font-size: 1.45rem;
      line-height: 1.15;
    }

    .shop-hero-card p.text-muted {
      font-size: .92rem;
    }

    .shop-hero-actions {
      gap: .5rem;
    }

    .shop-hero-actions .btn,
    .product-actions .btn {
      min-height: 40px;
    }

    .detail-badge {
      padding: .25rem .5rem;
      font-size: .7rem;
    }

    .gallery-main {
      border-radius: 18px;
    }

    .gallery-main img {
      aspect-ratio: 1 / 1;
    }

    .detail-panel h2 {
      font-size: 1.25rem;
    }

    .detail-panel .text-sm {
      font-size: .8rem;
    }

    .product-actions {
      gap: .4rem;
    }

    .product-actions .btn,
    .product-actions form {
      width: 100%;
    }

    .shop-toolbar .form-control {
      min-height: 42px;
    }
  }
</style>

<div class="container">
  <section class="shop-hero">
    <div class="shop-hero-grid">
      <div class="shop-hero-card">
        <span class="shop-hero-kicker"><?= uthenga_public_icon_svg('shop') ?> <?= e($settings['shop_name']) ?></span>
        <h1 style="margin-top:1rem;"><?= e($settings['shop_tagline']) ?></h1>
        <p class="text-muted" style="max-width:56ch;">Shop beers, spirits, soft drinks, water, juice, and other chilled beverages from Uthenga. Browse curated collections, add items to your cart, and checkout with delivery for Malawi-wide convenience.</p>
        <div style="display:flex;flex-wrap:wrap;gap:.55rem;margin-top:.35rem;">
          <span style="display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .7rem;border:1px solid var(--clr-border);border-radius:999px;background:var(--clr-surface);font-size:.78rem;font-weight:600;"><?= uthenga_public_icon_svg('check') ?> Fast delivery</span>
          <span style="display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .7rem;border:1px solid var(--clr-border);border-radius:999px;background:var(--clr-surface);font-size:.78rem;font-weight:600;"><?= uthenga_public_icon_svg('cart') ?> Secure checkout</span>
          <span style="display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .7rem;border:1px solid var(--clr-border);border-radius:999px;background:var(--clr-surface);font-size:.78rem;font-weight:600;"><?= uthenga_public_icon_svg('wallet') ?> Flexible payment</span>
        </div>
        <div class="shop-hero-actions">
          <a href="#catalog" class="btn btn-primary"><?= uthenga_public_icon_svg('cart') ?> Browse Products</a>
          <a href="<?= BASE_URL ?>shop-cart.php" class="btn btn-secondary">View Cart <?= count($cartItems) > 0 ? '(' . count($cartItems) . ')' : '' ?></a>
          <a href="<?= BASE_URL ?>shop-orders.php" class="btn btn-secondary">My Orders</a>
        </div>
        <div class="shop-hero-stats">
          <div class="shop-stat"><span>Products</span><strong><?= number_format(count($products ?: $featuredProducts)) ?></strong></div>
          <div class="shop-stat"><span>Cart Total</span><strong><?= uthenga_shop_money((float) $cartTotals['total']) ?></strong></div>
          <div class="shop-stat"><span>Delivery Fee</span><strong><?= uthenga_shop_money((float) $cartTotals['delivery_fee']) ?></strong></div>
        </div>
      </div>
      <aside class="shop-side-card">
        <div class="section-label">Popular Now</div>
        <h3 style="margin-top:.25rem;">Featured collections</h3>
        <div class="shop-mini-list">
          <?php foreach (array_slice($featuredProducts, 0, 3) as $item): ?>
            <?php $thumb = uthenga_shop_product_image_urls($item); ?>
            <a class="shop-mini-item" href="<?= BASE_URL ?>shop.php?product=<?= urlencode((string) $item['slug']) ?>">
              <img src="<?= e($thumb[0] ?? $item['primary_image_url'] ?? '') ?>" alt="<?= e($item['name']) ?>">
              <div>
                <strong><?= e($item['name']) ?></strong>
                <span><?= e($item['category_name'] ?? 'Shop') ?> &middot; <?= uthenga_shop_money((float) $item['price']) ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </aside>
    </div>
  </section>

  <?php if ($product): ?>
    <section class="shop-section">
      <div class="shop-detail-grid">
        <div>
          <div class="gallery-main">
            <img src="<?= e($gallery[0] ?? $product['primary_image_url'] ?? '') ?>" alt="<?= e($product['name']) ?>">
          </div>
          <?php if (count($gallery) > 1): ?>
            <div class="gallery-thumbs">
              <?php foreach ($gallery as $imageUrl): ?>
                <img src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="detail-panel">
          <div class="detail-badges">
            <span class="detail-badge"><?= e($product['category_name'] ?? 'Uncategorized') ?></span>
            <span class="detail-badge <?= (int) $product['stock_quantity'] > 0 ? 'badge-approved' : 'badge-cancelled' ?>"><?= (int) $product['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock' ?></span>
            <?php if (!empty($product['is_featured'])): ?><span class="detail-badge">Featured</span><?php endif; ?>
            <?php if (!empty($product['is_new_arrival'])): ?><span class="detail-badge">New Arrival</span><?php endif; ?>
            <?php if (!empty($product['is_best_seller'])): ?><span class="detail-badge">Best Seller</span><?php endif; ?>
            <?php if (!empty($product['is_promotion'])): ?><span class="detail-badge">Promotion</span><?php endif; ?>
          </div>
          <h2><?= e($product['name']) ?></h2>
          <p class="text-muted"><?= e($product['short_description'] ?: $product['description'] ?: 'Premium shop product.') ?></p>
          <div style="display:flex;align-items:baseline;gap:.75rem;margin:1rem 0;">
            <strong style="font-size:1.7rem;"><?= uthenga_shop_money((float) $product['price']) ?></strong>
            <?php if (!empty($product['compare_at_price'])): ?><span class="text-muted" style="text-decoration:line-through;"><?= uthenga_shop_money((float) $product['compare_at_price']) ?></span><?php endif; ?>
          </div>
          <div class="text-sm text-muted" style="margin-bottom:1rem;">
            <?= e($product['brand'] ?? 'Uthenga Direct') ?> &middot; <?= e($product['unit_label'] ?? 'Item') ?> &middot; SKU <?= e($product['sku']) ?>
          </div>
          <form method="post" action="<?= BASE_URL ?>shop-cart.php" class="grid" style="gap:.75rem;">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? 'shop.php') ?>">
            <div class="grid" style="grid-template-columns:140px 1fr;gap:.75rem;">
              <label class="form-group">
                <span class="form-label">Quantity</span>
                <input type="number" name="quantity" class="form-control" min="1" max="<?= (int) $product['stock_quantity'] ?>" value="1">
              </label>
              <label class="form-group">
                <span class="form-label">Delivery</span>
                <input type="text" class="form-control" value="Delivery fee calculated at checkout" readonly>
              </label>
            </div>
            <div class="product-actions">
              <button type="submit" class="btn btn-primary" <?= (int) $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>Add to Cart</button>
              <a href="<?= BASE_URL ?>shop-cart.php" class="btn btn-secondary">Go to Cart</a>
            </div>
          </form>
          <?php if (!empty($product['description'])): ?>
            <div style="margin-top:1.25rem;">
              <h3>Product Details</h3>
              <p class="text-muted"><?= nl2br(e($product['description'])) ?></p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!empty($relatedProducts)): ?>
        <div style="margin-top:2rem;">
          <div class="section-head" style="margin-bottom:1rem;">
            <div>
              <h3>Related Products</h3>
              <p class="text-xs text-muted">More items from the same category.</p>
            </div>
          </div>
          <div class="related-grid">
            <?php foreach (array_slice($relatedProducts, 0, 6) as $item): ?>
              <?php $thumb = uthenga_shop_product_image_urls($item); ?>
              <article class="product-card">
                <div class="product-image">
                  <img src="<?= e($thumb[0] ?? $item['primary_image_url'] ?? '') ?>" alt="<?= e($item['name']) ?>">
                </div>
                <div class="product-body">
                  <h3 class="product-title"><?= e($item['name']) ?></h3>
                  <div class="product-meta"><span><?= e($item['category_name'] ?? '') ?></span><span><?= e($item['stock_label'] ?? '') ?></span></div>
                  <div class="product-footer">
                    <strong class="product-price"><?= uthenga_shop_money((float) $item['price']) ?></strong>
                    <a class="btn btn-sm btn-secondary" href="<?= BASE_URL ?>shop.php?product=<?= urlencode((string) $item['slug']) ?>">View</a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <section class="shop-section" id="catalog">
      <div class="shop-toolbar">
        <form method="get" class="grid" style="gap: .75rem;">
          <div class="grid" style="grid-template-columns: minmax(0, 1fr) 160px 160px; gap: .75rem;">
            <input type="search" name="q" class="form-control" placeholder="Search products, brands, or SKUs" value="<?= e($query) ?>">
            <select name="category" class="form-control">
              <option value="0">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="sort" class="form-control">
              <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured</option>
              <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
              <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
              <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
              <option value="stock" <?= $sort === 'stock' ? 'selected' : '' ?>>Stock</option>
              <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
            </select>
          </div>
          <div class="product-actions">
            <button type="submit" class="btn btn-primary"><?= uthenga_public_icon_svg('search') ?> Search</button>
            <a class="btn btn-secondary" href="<?= BASE_URL ?>shop.php">Reset</a>
          </div>
        </form>
        <div class="shop-filters">
          <a href="<?= BASE_URL ?>shop.php" class="<?= !$featuredOnly && !$newOnly && !$bestOnly && !$promotionOnly && !$inStockOnly ? 'active' : '' ?>">All</a>
          <a href="<?= BASE_URL ?>shop.php?featured=1" class="<?= $featuredOnly ? 'active' : '' ?>">Featured</a>
          <a href="<?= BASE_URL ?>shop.php?new=1" class="<?= $newOnly ? 'active' : '' ?>">New Arrivals</a>
          <a href="<?= BASE_URL ?>shop.php?best=1" class="<?= $bestOnly ? 'active' : '' ?>">Best Sellers</a>
          <a href="<?= BASE_URL ?>shop.php?promotion=1" class="<?= $promotionOnly ? 'active' : '' ?>">Promotions</a>
          <a href="<?= BASE_URL ?>shop.php?stock=1" class="<?= $inStockOnly ? 'active' : '' ?>">In Stock</a>
        </div>
      </div>

      <?php if (!empty($categories)): ?>
        <div class="shop-filters" style="margin-bottom:1rem;">
          <?php foreach ($categories as $cat): ?>
            <a href="<?= BASE_URL ?>shop.php?category=<?= (int) $cat['id'] ?>" class="<?= $categoryId === (int) $cat['id'] ? 'active' : '' ?>"><?= e($cat['name']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($featuredProducts)): ?>
        <div style="margin-bottom:1.5rem;">
          <div class="section-head" style="margin-bottom:1rem;">
            <div>
              <h3>Featured Products</h3>
              <p class="text-xs text-muted">Hand-picked products for quick shopping.</p>
            </div>
          </div>
          <div class="product-grid">
            <?php foreach (array_slice($featuredProducts, 0, 6) as $item): ?>
              <?php $thumb = uthenga_shop_product_image_urls($item); ?>
              <article class="product-card">
                <a class="product-image" href="<?= BASE_URL ?>shop.php?product=<?= urlencode((string) $item['slug']) ?>">
                  <img src="<?= e($thumb[0] ?? $item['primary_image_url'] ?? '') ?>" alt="<?= e($item['name']) ?>">
                  <?php if (!empty($item['is_promotion'])): ?><span class="product-badge">Promotion</span><?php elseif (!empty($item['is_featured'])): ?><span class="product-badge">Featured</span><?php endif; ?>
                </a>
                <div class="product-body">
                  <div class="product-meta"><span><?= e($item['category_name'] ?? 'Shop') ?></span><span><?= e($item['stock_label'] ?? '') ?></span></div>
                  <h3 class="product-title"><a href="<?= BASE_URL ?>shop.php?product=<?= urlencode((string) $item['slug']) ?>"><?= e($item['name']) ?></a></h3>
                  <p class="product-desc"><?= e($item['short_description'] ?? '') ?></p>
                  <div class="product-footer">
                    <strong class="product-price"><?= uthenga_shop_money((float) $item['price']) ?></strong>
                    <form method="post" action="<?= BASE_URL ?>shop-cart.php" style="margin:0;">
                      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                      <input type="hidden" name="action" value="add">
                      <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                      <input type="hidden" name="quantity" value="1">
                      <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? 'shop.php') ?>">
                      <button type="submit" class="btn btn-sm btn-secondary" <?= (int) $item['stock_quantity'] <= 0 ? 'disabled' : '' ?>>Add</button>
                    </form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="shop-section">
        <div class="section-head" style="margin-bottom:1rem;">
          <div>
            <h3>Shop Catalog</h3>
            <p class="text-xs text-muted"><?= count($products) ?> product(s) match your current filters.</p>
          </div>
        </div>
        <div class="product-grid">
          <?php if (empty($products)): ?>
            <div class="glass-panel" style="padding:1.25rem;grid-column:1/-1;">
              <h3>No products found</h3>
              <p class="text-muted">Try a different search term or clear the filters.</p>
            </div>
          <?php else: ?>
            <?php foreach ($products as $item): ?>
              <?php $thumb = uthenga_shop_product_image_urls($item); ?>
              <article class="product-card">
                <a class="product-image" href="<?= BASE_URL ?>shop.php?product=<?= urlencode((string) $item['slug']) ?>">
                  <img src="<?= e($thumb[0] ?? $item['primary_image_url'] ?? '') ?>" alt="<?= e($item['name']) ?>">
                  <span class="product-badge"><?= e($item['stock_label'] ?? 'In Stock') ?></span>
                </a>
                <div class="product-body">
                  <div class="product-meta"><span><?= e($item['category_name'] ?? 'Shop') ?></span><span><?= e($item['unit_label'] ?? 'Item') ?></span></div>
                  <h3 class="product-title"><a href="<?= BASE_URL ?>shop.php?product=<?= urlencode((string) $item['slug']) ?>"><?= e($item['name']) ?></a></h3>
                  <p class="product-desc"><?= e($item['short_description'] ?? '') ?></p>
                  <div class="product-footer">
                    <strong class="product-price"><?= uthenga_shop_money((float) $item['price']) ?></strong>
                    <div class="product-actions">
                      <a class="btn btn-sm btn-secondary" href="<?= BASE_URL ?>shop.php?product=<?= urlencode((string) $item['slug']) ?>">View</a>
                      <form method="post" action="<?= BASE_URL ?>shop-cart.php" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? 'shop.php') ?>">
                        <button type="submit" class="btn btn-sm btn-primary" <?= (int) $item['stock_quantity'] <= 0 ? 'disabled' : '' ?>>Add</button>
                      </form>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($newArrivals) || !empty($bestSellers) || !empty($promoProducts)): ?>
        <div class="grid grid-cols-3 gap-3" style="margin-top:2rem;">
          <?php if (!empty($newArrivals)): ?>
            <div class="glass-panel" style="padding:1.15rem;">
              <div class="section-head"><div><h3>New Arrivals</h3></div></div>
              <div class="shop-mini-list">
                <?php foreach (array_slice($newArrivals, 0, 3) as $item): ?>
                  <a class="shop-mini-item" href="<?= BASE_URL ?>shop.php?product=<?= urlencode((string) $item['slug']) ?>">
                    <img src="<?= e(($imgs = uthenga_shop_product_image_urls($item))[0] ?? $item['primary_image_url'] ?? '') ?>" alt="<?= e($item['name']) ?>">
                    <div>
                      <strong><?= e($item['name']) ?></strong>
                      <span><?= uthenga_shop_money((float) $item['price']) ?></span>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          <?php if (!empty($bestSellers)): ?>
            <div class="glass-panel" style="padding:1.15rem;">
              <div class="section-head"><div><h3>Best Sellers</h3></div></div>
              <div class="shop-mini-list">
                <?php foreach (array_slice($bestSellers, 0, 3) as $item): ?>
                  <a class="shop-mini-item" href="<?= BASE_URL ?>shop.php?product=<?= urlencode((string) $item['slug']) ?>">
                    <img src="<?= e(($imgs = uthenga_shop_product_image_urls($item))[0] ?? $item['primary_image_url'] ?? '') ?>" alt="<?= e($item['name']) ?>">
                    <div>
                      <strong><?= e($item['name']) ?></strong>
                      <span><?= uthenga_shop_money((float) $item['price']) ?></span>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          <?php if (!empty($promoProducts)): ?>
            <div class="glass-panel" style="padding:1.15rem;">
              <div class="section-head"><div><h3>Promotions</h3></div></div>
              <div class="shop-mini-list">
                <?php foreach (array_slice($promoProducts, 0, 3) as $item): ?>
                  <a class="shop-mini-item" href="<?= BASE_URL ?>shop.php?product=<?= urlencode((string) $item['slug']) ?>">
                    <img src="<?= e(($imgs = uthenga_shop_product_image_urls($item))[0] ?? $item['primary_image_url'] ?? '') ?>" alt="<?= e($item['name']) ?>">
                    <div>
                      <strong><?= e($item['name']) ?></strong>
                      <span><?= e($item['promotion_label'] ?: 'Special offer') ?></span>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
