<?php
/**
 * Uthenga API — Accommodation direct-booking checkout
 * Body JSON: { action: 'hold', listing_id, room_type_id, quantity, check_in_date, check_out_date }
 *
 * Creates a real, time-bound room hold (UthengaAccommodationCheckout::hold())
 * and a Pending booking draft. Payment itself goes through the shared Uthenga
 * Checkout modal / UthengaPaymentEngine — this endpoint never collects money.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be logged in to book.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!isset($_SESSION['csrf_token'], $input['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
    exit;
}

$action = trim((string) ($input['action'] ?? 'hold'));

if ($action !== 'hold') {
    echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);
    exit;
}

$listingId  = trim((string) ($input['listing_id'] ?? ''));
$roomTypeId = (int) ($input['room_type_id'] ?? 0);
$quantity   = max(1, (int) ($input['quantity'] ?? 1));
$checkIn    = trim((string) ($input['check_in_date'] ?? ''));
$checkOut   = trim((string) ($input['check_out_date'] ?? ''));

if ($listingId === '' || $roomTypeId <= 0 || $checkIn === '' || $checkOut === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'listing_id, room_type_id, check_in_date and check_out_date are required.']);
    exit;
}

try {
    $result = UthengaAccommodationCheckout::hold(
        (string) $_SESSION['user_id'],
        (string) ($_SESSION['user_name'] ?? ''),
        (string) ($_SESSION['user_email'] ?? ''),
        $listingId,
        $roomTypeId,
        $quantity,
        $checkIn,
        $checkOut
    );
    echo json_encode($result);
} catch (UthengaTieException $e) {
    http_response_code($e->httpStatus());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to hold this room right now. Please try again.']);
}
