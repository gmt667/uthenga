<?php
/**
 * Uthenga API - Refund Request
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$bookingId = trim($data['booking_id'] ?? ($_POST['booking_id'] ?? ''));
$reason    = trim($data['reason'] ?? ($_POST['reason'] ?? ''));
$userId    = $_SESSION['user_id'];

if ($bookingId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Booking ID is required']);
    exit;
}

if (strlen($reason) < 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide a reason (at least 10 characters)']);
    exit;
}

$booking = dbQueryOne(
    "SELECT b.*, t.transaction_reference, t.amount AS paid_amount
     FROM bookings b
     LEFT JOIN transactions t ON t.booking_id = b.id AND LOWER(t.status) = 'success'
     WHERE b.id = ? AND b.customer_id = ?",
    [$bookingId, $userId]
);

if (!$booking) {
    http_response_code(404);
    echo json_encode(['error' => 'Booking not found or not yours']);
    exit;
}

if (strtolower((string)($booking['payment_status'] ?? '')) !== 'paid') {
    http_response_code(400);
    echo json_encode(['error' => 'This booking has not been paid. Refunds only apply to paid bookings.']);
    exit;
}

$existing = dbQueryOne(
    "SELECT id FROM support_tickets WHERE requester_user_id = ? AND subject LIKE ? AND status IN ('open', 'in_progress')",
    [$userId, '%REFUND-' . $bookingId . '%']
);
if ($existing) {
    echo json_encode([
        'success' => false,
        'message' => 'A refund request for this booking is already under review.',
    ]);
    exit;
}

$ticketCode = generateId('TKT');
dbExecute(
    "INSERT INTO support_tickets (ticket_code, requester_user_id, requester_name, requester_email, subject, category, status, priority, message, created_at)
     VALUES (?, ?, ?, ?, ?, 'billing', 'open', 'high', ?, NOW())",
    [
        $ticketCode,
        $userId,
        $_SESSION['user_name'] ?? 'Customer',
        $_SESSION['user_email'] ?? null,
        'REFUND-' . $bookingId . ' - Refund Request',
        "Booking ID: $bookingId\nAmount Paid: MK " . number_format((float)($booking['paid_amount'] ?? $booking['grand_total'] ?? 0)) . "\n\nRefund Reason:\n$reason"
    ]
);

echo json_encode([
    'success'   => true,
    'message'   => 'Refund request submitted successfully! Our team will review it within 2 business days.',
    'ticket_id' => $ticketCode,
]);
