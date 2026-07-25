<?php
/**
 * Uthenga — Active Promotional & Paid Popup Ads Endpoint
 * Returns active, date-valid popup advertisements as JSON pool.
 * No authentication required (public endpoint).
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$items = [];

// 1. Try querying promotional_popups table
try {
    $popups = dbQuery("
        SELECT
            id, title, description, image_url, cta_text, cta_url,
            display_delay_seconds, start_date, end_date
        FROM promotional_popups
        WHERE is_active = 1
          AND (start_date IS NULL OR start_date <= CURDATE())
          AND (end_date   IS NULL OR end_date   >= CURDATE())
        ORDER BY id DESC
        LIMIT 10
    ");
    if (!empty($popups)) {
        foreach ($popups as $p) {
            $img = $p['image_url'] ?? '';
            if ($img && !preg_match('#^https?://#', $img)) {
                $img = BASE_URL . ltrim($img, '/');
            }
            $ctaUrl = $p['cta_url'] ?? '#';
            if ($ctaUrl && $ctaUrl !== '#' && !preg_match('#^https?://#', $ctaUrl)) {
                $ctaUrl = BASE_URL . ltrim($ctaUrl, '/');
            }
            $items[] = [
                'id'           => 'popup-' . $p['id'],
                'title'        => $p['title'],
                'description'  => $p['description'],
                'image_url'    => $img,
                'cta_text'     => !empty($p['cta_text']) ? $p['cta_text'] : 'Learn More',
                'cta_url'      => $ctaUrl,
                'sponsor_name' => 'Featured Partner',
                'is_paid'      => true,
                'delay_seconds'=> max(0, (int)($p['display_delay_seconds'] ?? 3))
            ];
        }
    }
} catch (Exception $e) {
    // Table may not exist yet or empty
}

// 2. Try querying advertisements table with popup/banner type
try {
    $ads = dbQuery("
        SELECT *
        FROM advertisements
        WHERE is_active = 1
          AND (start_date IS NULL OR start_date <= NOW())
          AND (end_date   IS NULL OR end_date   >= NOW())
        ORDER BY sort_order ASC, created_at DESC
        LIMIT 10
    ");
    if (!empty($ads)) {
        foreach ($ads as $ad) {
            $img = $ad['image_url'] ?? '';
            if ($img && !preg_match('#^https?://#', $img)) {
                $img = BASE_URL . ltrim($img, '/');
            }
            $ctaUrl = $ad['link_url'] ?? '#';
            if ($ctaUrl && $ctaUrl !== '#' && !preg_match('#^https?://#', $ctaUrl)) {
                $ctaUrl = BASE_URL . ltrim($ctaUrl, '/');
            }
            $items[] = [
                'id'           => 'ad-' . $ad['id'],
                'title'        => $ad['title'],
                'description'  => $ad['description'] ?? $ad['caption'] ?? 'Exclusive vendor offer available on Uthenga Marketplace.',
                'image_url'    => $img,
                'cta_text'     => !empty($ad['cta_text']) ? $ad['cta_text'] : 'Claim Paid Deal',
                'cta_url'      => $ctaUrl,
                'sponsor_name' => $ad['advertiser_name'] ?? $ad['sponsor_name'] ?? 'Verified Vendor',
                'is_paid'      => true,
                'delay_seconds'=> 3
            ];
        }
    }
} catch (Exception $e) {
    // Table may not exist or query error
}

// 3. If pool is empty, provide high-quality fallback paid ads
if (empty($items)) {
    $items = [
        [
            'id'           => 'fallback-ad-1',
            'title'        => 'Lake Shore Summer Festival 2026',
            'description'  => 'Get 15% OFF VIP & Standard tickets when you book this week! Sponsored by Sunbird Hotels.',
            'image_url'    => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&fit=crop&q=80',
            'cta_text'     => 'Book Festival Tickets',
            'cta_url'      => BASE_URL . 'events.php',
            'sponsor_name' => 'Sunbird Hotels & Resorts',
            'is_paid'      => true,
            'delay_seconds'=> 3
        ],
        [
            'id'           => 'fallback-ad-2',
            'title'        => 'Luxury Beachfront Villa Stay',
            'description'  => 'Experience pristine views at Cape Maclear Retreat. Paid promotion by Malawian Stays.',
            'image_url'    => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&fit=crop&q=80',
            'cta_text'     => 'View Luxury Stays',
            'cta_url'      => BASE_URL . 'hotels.php',
            'sponsor_name' => 'Malawian Stays Ltd',
            'is_paid'      => true,
            'delay_seconds'=> 3
        ],
        [
            'id'           => 'fallback-ad-3',
            'title'        => 'Express Airport Shuttle & Travel',
            'description'  => 'Reliable transport transfers across Lilongwe and Blantyre. Paid ad by Shuttle Express.',
            'image_url'    => 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&fit=crop&q=80',
            'cta_text'     => 'Book Airport Shuttle',
            'cta_url'      => BASE_URL . 'transport.php',
            'sponsor_name' => 'Shuttle Express Malawi',
            'is_paid'      => true,
            'delay_seconds'=> 3
        ]
    ];
}

echo json_encode([
    'active'     => true,
    'items'      => $items,
    'total'      => count($items),
    'repopup_ms' => 180000 // 3 minutes re-popup interval
]);

