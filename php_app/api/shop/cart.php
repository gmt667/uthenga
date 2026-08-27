<?php
/**
 * Uthenga API — Shop Cart (JSON)
 * Thin JSON wrapper around the real, existing session-based cart helpers in
 * includes/shop_helpers.php (uthenga_shop_cart_add/_items/_order_totals) —
 * no new cart logic, just a fetch()-callable contract for the dashboard's
 * inline Shop widget.
 *
 * POST body JSON: { action: 'add', product_id, quantity, csrf_token }
 * GET: ?action=list
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/shop_helpers.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = trim((string) ($_GET['action'] ?? 'list'));
    if ($action !== 'list') {
        echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);
        exit;
    }
    $items = uthenga_shop_cart_items();
    $totals = uthenga_shop_order_totals($items);
    echo json_encode(['success' => true, 'items' => array_values($items), 'totals' => $totals]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!isset($_SESSION['csrf_token'], $input['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$action = trim((string) ($input['action'] ?? ''));

if ($action === 'add') {
    $productId = (int) ($input['product_id'] ?? 0);
    $quantity = max(1, (int) ($input['quantity'] ?? 1));
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing product_id.']);
        exit;
    }
    $result = uthenga_shop_cart_add($productId, $quantity);
    if (!$result['ok']) {
        echo json_encode(['success' => false, 'error' => $result['message']]);
        exit;
    }
    $items = uthenga_shop_cart_items();
    $totals = uthenga_shop_order_totals($items);
    echo json_encode(['success' => true, 'message' => $result['message'] ?? 'Added to cart.', 'items' => array_values($items), 'totals' => $totals]);
    exit;
}

if ($action === 'remove') {
    $productId = (int) ($input['product_id'] ?? 0);
    uthenga_shop_cart_remove($productId);
    $items = uthenga_shop_cart_items();
    $totals = uthenga_shop_order_totals($items);
    echo json_encode(['success' => true, 'items' => array_values($items), 'totals' => $totals]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);
