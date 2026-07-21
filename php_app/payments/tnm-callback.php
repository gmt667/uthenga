<?php
/**
 * Uthenga - TNM Mpamba Callback Handler
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$tnmKey     = uthenga_env('TNM_API_KEY', '');
$tnmSecret  = uthenga_env('TNM_SECRET', '');
$isDemoMode = (bool)($_GET['demo'] ?? false);
$txnRef     = trim($_GET['txn'] ?? ($_POST['reference'] ?? ''));

function uthenga_tnm_find_transaction(string $reference) {
    if ($reference === '') {
        return null;
    }

    return dbQueryOne(
        "SELECT * FROM transactions WHERE transaction_reference = ? OR id = ? LIMIT 1",
        [$reference, $reference]
    );
}

function uthenga_tnm_confirm_booking(array $txn): void {
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

if ($isDemoMode || !$tnmKey) {
    if ($txnRef !== '') {
        dbExecute(
            "UPDATE transactions SET status='success' WHERE (transaction_reference = ? OR id = ?) AND status='pending'",
            [$txnRef, $txnRef]
        );
        $txn = uthenga_tnm_find_transaction($txnRef);
        if ($txn) {
            uthenga_tnm_confirm_booking($txn);
            uthenga_finance_record_sale(uthenga_finance_context_from_booking($txn));
            uthenga_record_transaction_analytics([
                'transaction_reference' => $txn['transaction_reference'] ?? $txnRef,
                'booking_id' => $txn['booking_id'] ?? null,
                'user_id' => $txn['user_id'] ?? null,
                'gateway_name' => $txn['gateway_name'] ?? 'TNM Mpamba',
                'status' => 'success',
                'amount' => $txn['amount'] ?? 0,
                'created_at' => $txn['created_at'] ?? date('Y-m-d H:i:s'),
            ], 'status_updated');
        }
    }

    header('Location: ' . BASE_URL . 'payments/success.php?txn=' . urlencode($txnRef) . '&gateway=tnm');
    exit;
}

$rawBody  = file_get_contents('php://input');
$payload  = json_decode($rawBody, true) ?: [];

$signature = $_SERVER['HTTP_X_TNM_SIGNATURE'] ?? '';
$expected  = hash_hmac('sha256', $rawBody, $tnmSecret);
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$txnReference = trim((string)($payload['reference'] ?? $txnRef));
$status       = strtolower((string)($payload['status'] ?? ''));

if (in_array($status, ['success', 'completed'], true)) {
    dbExecute(
        "UPDATE transactions SET status='success' WHERE (transaction_reference = ? OR id = ?) AND status='pending'",
        [$txnReference, $txnRef]
    );
    $txn = uthenga_tnm_find_transaction($txnReference);
    if ($txn) {
        uthenga_tnm_confirm_booking($txn);
        uthenga_finance_record_sale(uthenga_finance_context_from_booking($txn));
        uthenga_record_transaction_analytics([
            'transaction_reference' => $txn['transaction_reference'] ?? $txnReference,
            'booking_id' => $txn['booking_id'] ?? null,
            'user_id' => $txn['user_id'] ?? null,
            'gateway_name' => $txn['gateway_name'] ?? 'TNM Mpamba',
            'status' => 'success',
            'amount' => $txn['amount'] ?? 0,
            'created_at' => $txn['created_at'] ?? date('Y-m-d H:i:s'),
        ], 'status_updated');
    }
} elseif (in_array($status, ['failed', 'cancelled'], true)) {
    dbExecute(
        "UPDATE transactions SET status='failed' WHERE transaction_reference = ? OR id = ?",
        [$txnReference, $txnRef]
    );
    $txn = uthenga_tnm_find_transaction($txnReference);
    if ($txn) {
        uthenga_record_transaction_analytics([
            'transaction_reference' => $txn['transaction_reference'] ?? $txnReference,
            'booking_id' => $txn['booking_id'] ?? null,
            'user_id' => $txn['user_id'] ?? null,
            'gateway_name' => $txn['gateway_name'] ?? 'TNM Mpamba',
            'status' => 'failed',
            'amount' => $txn['amount'] ?? 0,
            'created_at' => $txn['created_at'] ?? date('Y-m-d H:i:s'),
        ], 'status_updated');
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
