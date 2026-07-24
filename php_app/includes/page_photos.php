<?php
/**
 * Shared photo strip / visual panel helpers for public, admin, and auth pages.
 */

require_once __DIR__ . '/../config.php';

if (!function_exists('uthenga_photo_strip_assets')) {
    function uthenga_photo_strip_assets(string $mode = 'public'): array {
        $mode = strtolower(trim($mode));

        $sets = [
            'public' => [
                'headline' => 'Malawi, curated visually',
                'copy' => 'Fresh travel, hospitality, and marketplace imagery across the platform.',
                'images' => [
                    ['src' => BASE_URL . 'assets/images/hero/lakeside-resort.png', 'alt' => 'Lakeside resort at sunset'],
                    ['src' => BASE_URL . 'assets/images/hero/hotel-room.png', 'alt' => 'Modern hotel room'],
                    ['src' => BASE_URL . 'assets/images/hero/transport-van.png', 'alt' => 'City transport minibus'],
                ],
            ],
            'admin' => [
                'headline' => 'Operational overview',
                'copy' => 'A cleaner visual layer for the admin console and management screens.',
                'images' => [
                    ['src' => BASE_URL . 'assets/images/hero/hotel-room.png', 'alt' => 'Hospitality dashboard visual'],
                    ['src' => BASE_URL . 'assets/images/hero/transport-van.png', 'alt' => 'Transport operations visual'],
                    ['src' => BASE_URL . 'assets/images/shop/beer-assortment.png', 'alt' => 'Shop product visual'],
                ],
            ],
            'auth' => [
                'headline' => 'A polished welcome',
                'copy' => 'Visual context for sign in, registration, and password recovery screens.',
                'images' => [
                    ['src' => BASE_URL . 'assets/images/hero/lakeside-resort.png', 'alt' => 'Welcome visual one'],
                    ['src' => BASE_URL . 'assets/images/hero/hotel-room.png', 'alt' => 'Welcome visual two'],
                ],
            ],
        ];

        return $sets[$mode] ?? $sets['public'];
    }
}

if (!function_exists('uthenga_render_photo_strip')) {
    function uthenga_render_photo_strip(string $mode = 'public'): void {
        $data = uthenga_photo_strip_assets($mode);
        $modeClass = 'uthenga-photo-strip--' . preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim($mode)));
        ?>
        <section class="uthenga-photo-strip <?= e($modeClass) ?>" aria-label="<?= e($data['headline']) ?>">
          <div class="uthenga-photo-strip-copy">
            <span class="section-label"><?= e($data['headline']) ?></span>
            <p><?= e($data['copy']) ?></p>
          </div>
          <div class="uthenga-photo-strip-grid">
            <?php foreach ($data['images'] as $image): ?>
              <img src="<?= e($image['src']) ?>" alt="<?= e($image['alt']) ?>" loading="lazy">
            <?php endforeach; ?>
          </div>
        </section>
        <?php
    }
}
