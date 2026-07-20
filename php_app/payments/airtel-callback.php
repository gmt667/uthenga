<?php
/**
 * Uthenga - Airtel Money Callback Handler
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$airtelKey  = uthenga_env('AIRTEL_API_KEY', '');
$isDemoMode = (bool)($_GET['demo'] ?? false);
$txnRef     = trim($_GET['txn'] ?? ($_POST['transaction_id'] ?? ''));

function uthenga_airtel_find_transaction(string $reference) {
    if ($reference === '') {
        return null;
    }

    return dbQueryOne(
        "SELECT * FROM transactions WHERE transaction_reference = ? OR id = ? LIMIT 1",
        [$reference, $reference]
    );
}

function uthenga_airtel_confirm_booking(array $txn): void {
    if (empty($txn['booking_id'])) {
        return;
    }

    dbExecute(
        "UPDATE bookings
         SET booking_status = 'confirmed',
             payment_status = 'paid',
             confirmed_at = COALESCE(confirmed_at, NOW())
         WHERE id = ?",
        [$txn['booking_id']]
    );
}

if ($isDemoMode || !$airtelKey) {
    if ($txnRef !== '') {
        dbExecute(
            "UPDATE transactions SET status='success' WHERE (transaction_reference = ? OR id = ?) AND status='pending'",
            [$txnRef, $txnRef]
        );
        $txn = uthenga_airtel_find_transaction($txnRef);
        if ($txn) {
            uthenga_airtel_confirm_booking($txn);
            uthenga_record_transaction_analytics([
                'transaction_reference' => $txn['transaction_reference'] ?? $txnRef,
                'booking_id' => $txn['booking_id'] ?? null,
                'user_id' => $txn['user_id'] ?? null,
                'gateway_name' => $txn['gateway_name'] ?? 'Airtel Money',
                'status' => 'success',
                'amount' => $txn['amount'] ?? 0,
                'created_at' => $txn['created_at'] ?? date('Y-m-d H:i:s'),
            ], 'status_updated');
        }
    }

    header('Location: ' . BASE_URL . 'payments/success.php?txn=' . urlencode($txnRef) . '&gateway=airtel');
    exit;
}

$rawBody = file_get_contents('php://input');
$payload  = json_decode($rawBody, true) ?: [];

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token      = str_replace('Bearer ', '', $authHeader);
if (empty($token) || $token !== $airtelKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$transactionId = trim((string)($payload['transaction']['id'] ?? ''));
$status        = strtolower((string)($payload['transaction']['status'] ?? ''));
$txnReference  = trim((string)($payload['transaction']['reference'] ?? $txnRef));
$lookupRef     = $txnReference !== '' ? $txnReference : $txnRef;

if ($status === 'ts' || $status === 'successful' || $status === 'success') {
    dbExecute(
        "UPDATE transactions SET status='success' WHERE (transaction_reference = ? OR id = ?) AND status='pending'",
        [$lookupRef, $txnRef]
    );
    $txn = uthenga_airtel_find_transaction($lookupRef);
    if ($txn) {
        uthenga_airtel_confirm_booking($txn);
        uthenga_record_transaction_analytics([
            'transaction_reference' => $txn['transaction_reference'] ?? $lookupRef,
            'booking_id' => $txn['booking_id'] ?? null,
            'user_id' => $txn['user_id'] ?? null,
            'gateway_name' => $txn['gateway_name'] ?? 'Airtel Money',
            'status' => 'success',
            'amount' => $txn['amount'] ?? 0,
            'created_at' => $txn['created_at'] ?? date('Y-m-d H:i:s'),
        ], 'status_updated');
    }
}

http_response_code(200);
echo json_encode(['status' => 'OK', 'transaction_id' => $transactionId]);
