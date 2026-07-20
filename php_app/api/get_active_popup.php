<?php
/**
 * Uthenga — Active Promotional Popup Endpoint
 * Returns the first active, date-valid popup as JSON.
 * No authentication required (public endpoint).
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // 5-minute browser cache

try {
    $popup = dbQueryOne("
        SELECT
            id, title, description, image_url, cta_text, cta_url,
            display_delay_seconds, start_date, end_date
        FROM promotional_popups
        WHERE is_active = 1
          AND (start_date IS NULL OR start_date <= CURDATE())
          AND (end_date   IS NULL OR end_date   >= CURDATE())
        ORDER BY id DESC
        LIMIT 1
    ");
} catch (Exception $e) {
    // Table may not exist yet
    $popup = null;
}

if (!$popup) {
    echo json_encode(['active' => false]);
    exit;
}

// Resolve image URL
if (!empty($popup['image_url']) && !preg_match('#^https?://#', $popup['image_url'])) {
    $popup['image_url'] = BASE_URL . ltrim($popup['image_url'], '/');
}

// Resolve CTA URL
if (!empty($popup['cta_url']) && !preg_match('#^https?://#', $popup['cta_url'])) {
    $popup['cta_url'] = BASE_URL . ltrim($popup['cta_url'], '/');
}

echo json_encode([
    'active'          => true,
    'id'              => (int)$popup['id'],
    'title'           => $popup['title'],
    'description'     => $popup['description'],
    'image_url'       => $popup['image_url'] ?? '',
    'cta_text'        => $popup['cta_text'] ?: 'Learn More',
    'cta_url'         => $popup['cta_url']  ?: '#',
    'delay_seconds'   => max(0, (int)($popup['display_delay_seconds'] ?? 3)),
]);
