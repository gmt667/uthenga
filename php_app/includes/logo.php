<?php
/**
 * Uthenga — Reusable Theme-Aware Logo Partial
 *
 * Usage:
 *   $logoSize = 'sm';   // 'sm' | 'md' | 'lg'  (default: 'md')
 *   $logoLink = true;   // wrap in <a> back to home (default: true)
 *   require __DIR__ . '/logo.php';
 *
 * Sizes:
 *   sm  → 100×34  (sidebar / dashboard topbar)
 *   md  → 140×48  (public navbar — default)
 *   lg  → 180×62  (auth cards / login forms)
 */
require_once __DIR__ . '/../config.php';

$_logoSize  = $logoSize ?? 'md';
$_logoLink  = $logoLink ?? true;

$_sizes = [
    'sm' => ['w' => 100, 'h' => 34],
    'md' => ['w' => 140, 'h' => 48],
    'lg' => ['w' => 180, 'h' => 62],
];
$_dim = $_sizes[$_logoSize] ?? $_sizes['md'];

$_imgDark  = BASE_URL . 'assets/images/logo-dark.png';
$_imgLight = BASE_URL . 'assets/images/logo-light.png';
$_alt      = e(APP_NAME) . ' logo';
$_logoFallbackId = 'uthenga-logo-fallback-' . substr(md5((string) mt_rand()), 0, 8);

// Unset so they don't bleed into parent scope
unset($logoSize, $logoLink);
?>
<?php if ($_logoLink): ?><a href="<?= BASE_URL ?>index.php" class="logo-partial" aria-label="<?= e(APP_NAME) ?> — Home"><?php else: ?><span class="logo-partial"><?php endif; ?>
  <span class="logo-visual" aria-hidden="true">
    <img src="<?= $_imgDark ?>"  alt="<?= $_alt ?>" class="logo-img logo-dark"  width="<?= $_dim['w'] ?>" height="<?= $_dim['h'] ?>" draggable="false" onerror="this.closest('.logo-partial')?.classList.add('logo-broken')">
    <img src="<?= $_imgLight ?>" alt="<?= $_alt ?>" class="logo-img logo-light" width="<?= $_dim['w'] ?>" height="<?= $_dim['h'] ?>" draggable="false" onerror="this.closest('.logo-partial')?.classList.add('logo-broken')">
  </span>
  <span class="logo-fallback" id="<?= e($_logoFallbackId) ?>">
    <strong>UTHENGA</strong>
    <span>Malawi Marketplace Platform</span>
  </span>
<?php if ($_logoLink): ?></a><?php else: ?></span><?php endif; ?>
