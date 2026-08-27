<?php
/**
 * Uthenga API — Shop Checkout (JSON)
 * Thin JSON wrapper around uthenga_shop_create_order_from_cart() — the same
 * real order-creation logic shop-checkout.php's own form uses (extracted so
 * both call sites share one implementation). Payment itself goes through the
 * shared Uthenga Checkout modal / UthengaPaymentEngine for pay_online orders.
 *
 * POST body JSON: { customer_name, customer_email, customer_phone,
 *   delivery_address, delivery_instructions, preferred_delivery_time,
 *   payment_method, csrf_token }
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/shop_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be logged in to check out.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!isset($_SESSION['csrf_token'], $input['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

// Best-effort normalization mirroring assets/js/phone-validate.js so a
// customer-typed +265/265-prefixed number is stored in the same 0XXXXXXXXX
// shape the rest of the codebase expects — falls back to the raw input if it
// doesn't look like a Malawian mobile number at all.
$phoneRaw = trim((string) ($input['customer_phone'] ?? ''));
$phoneNormalized = $phoneRaw;
$digits = preg_replace('/[\s\-().]/', '', $phoneRaw);
if (str_starts_with($digits, '+')) {
    $digits = substr($digits, 1);
}
if (str_starts_with($digits, '265') && strlen($digits) > 9) {
    $digits = '0' . substr($digits, 3);
} elseif ($digits !== '' && $digits[0] !== '0' && strlen($digits) === 9) {
    $digits = '0' . $digits;
}
if (preg_match('/^0\d{9}$/', $digits)) {
    $phoneNormalized = $digits;
}

$cartItems = uthenga_shop_cart_items();
$result = uthenga_shop_create_order_from_cart($cartItems, [
    'customer_name'           => $input['customer_name'] ?? ($_SESSION['user_name'] ?? ''),
    'customer_email'          => $input['customer_email'] ?? ($_SESSION['user_email'] ?? ''),
    'customer_phone'          => $phoneNormalized,
    'delivery_address'        => $input['delivery_address'] ?? '',
    'delivery_instructions'   => $input['delivery_instructions'] ?? '',
    'preferred_delivery_time' => $input['preferred_delivery_time'] ?? '',
    'payment_method'          => $input['payment_method'] ?? 'cash_on_delivery',
    'user_id'                 => (string) ($_SESSION['user_id'] ?? ''),
]);

echo json_encode($result);
