<?php
/**
 * Uthenga - Shop PayChangu Callback Handler
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/shop_helpers.php';

$paychanguSecret = PAYCHANGU_SECRET_KEY !== '' ? PAYCHANGU_SECRET_KEY : uthenga_env('PAYCHANGU_SECRET_KEY', '');
$isDemoMode = !empty($_GET['demo']) || !empty($_POST['demo']);
$reference = trim((string) ($_GET['txn'] ?? $_GET['reference'] ?? $_GET['order'] ?? $_POST['reference'] ?? $_POST['tx_ref'] ?? ''));

function uthenga_shop_paychangu_find_order_by_reference(string $reference): ?array {
    if ($reference === '') {
        return null;
    }

    $payment = uthenga_shop_payment_by_reference($reference);
    if ($payment) {
        return $payment;
    }

    if (!uthenga_table_exists('shop_orders')) {
        return null;
    }

    $order = dbQueryOne(
        'SELECT * FROM shop_orders WHERE order_number = ? OR id = ? LIMIT 1',
        [$reference, $reference]
    );

    if (!$order) {
        return null;
    }

    $payment = uthenga_shop_payment_by_order_id((int) $order['id']);
    if ($payment) {
        $payment['order_number'] = $order['order_number'];
        $payment['user_id'] = $order['user_id'] ?? null;
        $payment['customer_name'] = $order['customer_name'] ?? '';
        $payment['customer_email'] = $order['customer_email'] ?? '';
        $payment['customer_phone'] = $order['customer_phone'] ?? '';
        $payment['total_amount'] = $order['total_amount'] ?? 0;
        $payment['currency'] = $order['currency'] ?? APP_CURRENCY;
        $payment['order_status'] = $order['order_status'] ?? 'pending';
        $payment['order_payment_status'] = $order['payment_status'] ?? 'pending';
        return $payment;
    }

    return [
        'id' => 0,
        'order_id' => (int) $order['id'],
        'order_number' => $order['order_number'],
        'user_id' => $order['user_id'] ?? null,
        'customer_name' => $order['customer_name'] ?? '',
        'customer_email' => $order['customer_email'] ?? '',
        'customer_phone' => $order['customer_phone'] ?? '',
        'total_amount' => $order['total_amount'] ?? 0,
        'currency' => $order['currency'] ?? APP_CURRENCY,
        'payment_reference' => $reference,
        'payment_status' => $order['payment_status'] ?? 'pending',
        'order_status' => $order['order_status'] ?? 'pending',
        'order_payment_status' => $order['payment_status'] ?? 'pending',
        'payment_method' => 'paychangu',
        'provider' => 'PayChangu',
    ];
}

function uthenga_shop_paychangu_update_payment(array $payment, array $payload, string $status): void {
    $order = null;
    if (!empty($payment['order_id'])) {
        $order = dbQueryOne('SELECT * FROM shop_orders WHERE id = ? LIMIT 1', [(int) $payment['order_id']]);
    }

    if (!$order && !empty($payment['order_number'])) {
        $order = dbQueryOne('SELECT * FROM shop_orders WHERE order_number = ? LIMIT 1', [(string) $payment['order_number']]);
    }

    if (!$order) {
        return;
    }

    if ($status === 'paid') {
        uthenga_shop_confirm_payment($order, $payment, $payload, 'paid');
        return;
    }

    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    dbExecute(
        'UPDATE shop_payments SET payment_status = ?, gateway_payload = COALESCE(?, gateway_payload), updated_at = NOW() WHERE payment_reference = ? OR id = ?',
        [
            $status,
            $payloadJson,
            (string) ($payment['payment_reference'] ?? $payment['id'] ?? ''),
            (int) ($payment['id'] ?? 0),
        ]
    );
}

if ($isDemoMode || $paychanguSecret === '') {
    $payment = uthenga_shop_paychangu_find_order_by_reference($reference);
    if ($payment) {
        uthenga_shop_paychangu_update_payment($payment, ['demo' => true, 'reference' => $reference], 'paid');
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($payment['order_number'])) {
            $_SESSION['shop_order_success'] = (string) $payment['order_number'];
        }
        header('Location: ' . BASE_URL . 'shop-order.php?order=' . urlencode((string) ($payment['order_number'] ?? $reference)));
        exit;
    }

    header('Location: ' . BASE_URL . 'shop-orders.php');
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    $payload = [];
}

$reference = trim((string) ($payload['reference'] ?? $payload['tx_ref'] ?? $payload['transaction_reference'] ?? $reference));
$status = strtolower(trim((string) ($payload['status'] ?? $payload['payment_status'] ?? $payload['transaction_status'] ?? '')));
$signature = trim((string) ($_SERVER[PAYCHANGU_WEBHOOK_SIGNATURE_HEADER] ?? ''));

if ($signature === '' && strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $payment = uthenga_shop_paychangu_find_order_by_reference($reference);
    if ($payment && !empty($payment['order_number'])) {
        header('Location: ' . BASE_URL . 'shop-order.php?order=' . urlencode((string) $payment['order_number']));
        exit;
    }

    if ($reference !== '') {
        header('Location: ' . BASE_URL . 'shop-order.php?order=' . urlencode($reference));
        exit;
    }

    header('Location: ' . BASE_URL . 'shop-orders.php');
    exit;
}

if ($signature === '') {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Missing PayChangu signature.']);
    exit;
}

$expected256 = hash_hmac('sha256', $rawBody, $paychanguSecret);
$expected512 = hash_hmac('sha512', $rawBody, $paychanguSecret);
if (!hash_equals($expected256, $signature) && !hash_equals($expected512, $signature)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Invalid PayChangu signature.']);
    exit;
}

$payment = uthenga_shop_paychangu_find_order_by_reference($reference);
$normalizedStatus = match ($status) {
    'successful', 'success', 'paid', 'completed', 'succeeded' => 'paid',
    'failed', 'cancelled', 'canceled', 'expired' => 'failed',
    'pending', 'processing', 'initiated' => 'processing',
    default => $status !== '' ? $status : 'processing',
};

$metadata = [
    'paychangu_reference' => $reference,
    'paychangu_status' => $status,
    'payload' => $payload,
];

if ($payment) {
    uthenga_shop_paychangu_update_payment($payment, $metadata, $normalizedStatus);
    if ($normalizedStatus === 'paid' && session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
        @session_start();
        if (session_status() === PHP_SESSION_ACTIVE && !empty($payment['order_number'])) {
            $_SESSION['shop_order_success'] = (string) $payment['order_number'];
        }
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'reference' => $reference,
    'status' => $normalizedStatus,
]);
