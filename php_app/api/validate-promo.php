<?php
/**
 * Uthenga — Validate Promo Code API
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$code = trim($_POST['code'] ?? $_GET['code'] ?? '');
$eventId = (int)($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
$subtotal = (float)($_POST['subtotal'] ?? $_GET['subtotal'] ?? 0);

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter a coupon or promo code.']);
    exit;
}

$now = date('Y-m-d H:i:s');

// Try finding in event_promo_codes first
$promo = dbQueryOne("
    SELECT * FROM event_promo_codes 
    WHERE code = ? 
      AND is_active = 1 
      AND valid_from <= ? 
      AND valid_to >= ?
", [$code, $now, $now]);

// Fallback: check standard compatibility coupons table if it exists
if (!$promo) {
    $couponExists = dbCount("SHOW TABLES LIKE 'coupons'");
    if ($couponExists > 0) {
        $promo = dbQueryOne("
            SELECT code, discount_value as discount_given, 'percentage' as type 
            FROM coupons 
            WHERE code = ? AND is_active = 1
        ", [$code]);
    }
}

if (!$promo) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired promo code.']);
    exit;
}

// Check event match if specified on the promo code
if (isset($promo['event_id']) && $promo['event_id'] !== null && $eventId > 0 && (int)$promo['event_id'] !== $eventId) {
    echo json_encode(['success' => false, 'message' => 'This promo code does not apply to this specific event.']);
    exit;
}

// Check maximum uses
if (isset($promo['max_uses']) && $promo['max_uses'] !== null && (int)$promo['used_count'] >= (int)$promo['max_uses']) {
    echo json_encode(['success' => false, 'message' => 'This promo code has reached its maximum usage limit.']);
    exit;
}

// Calculate discount amount
$discountType = $promo['discount_type'] ?? 'percentage';
$discountValue = (float)($promo['discount_value'] ?? $promo['discount_given'] ?? 0);
$discount = 0.0;

if ($discountType === 'percentage') {
    $discount = $subtotal * ($discountValue / 100);
    if (isset($promo['max_discount']) && $promo['max_discount'] !== null) {
        $discount = min($discount, (float)$promo['max_discount']);
    }
} else {
    $discount = min($subtotal, $discountValue);
}

echo json_encode([
    'success' => true,
    'code' => $code,
    'discount_type' => $discountType,
    'discount_value' => $discountValue,
    'discount_amount' => $discount,
    'message' => 'Promo code applied! Discount: ' . ($discountType === 'percentage' ? $discountValue . '%' : 'MK ' . number_format($discount))
]);
exit;
