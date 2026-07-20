<?php
/**
 * Uthenga - Card Payment Callback Handler
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$cardEnabled = (bool)uthenga_env('CARD_PAYMENTS_ENABLED', false);
$isDemoMode  = (bool)($_GET['demo'] ?? false);
$txnRef      = trim($_GET['txn'] ?? ($_POST['merchantTransactionId'] ?? ''));

function uthenga_card_find_transaction(string $reference) {
    if ($reference === '') {
        return null;
    }

    return dbQueryOne(
        "SELECT * FROM transactions WHERE transaction_reference = ? OR id = ? LIMIT 1",
        [$reference, $reference]
    );
}

function uthenga_card_confirm_booking(array $txn): void {
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

if ($isDemoMode || !$cardEnabled) {
    if ($txnRef !== '') {
        dbExecute(
            "UPDATE transactions SET status='success' WHERE (transaction_reference = ? OR id = ?) AND status='pending'",
            [$txnRef, $txnRef]
        );
        $txn = uthenga_card_find_transaction($txnRef);
        if ($txn) {
            uthenga_card_confirm_booking($txn);
            uthenga_record_transaction_analytics([
                'transaction_reference' => $txn['transaction_reference'] ?? $txnRef,
                'booking_id' => $txn['booking_id'] ?? null,
                'user_id' => $txn['user_id'] ?? null,
                'gateway_name' => $txn['gateway_name'] ?? 'Card',
                'status' => 'success',
                'amount' => $txn['amount'] ?? 0,
                'created_at' => $txn['created_at'] ?? date('Y-m-d H:i:s'),
            ], 'status_updated');
        }
    }

    header('Location: ' . BASE_URL . 'payments/success.php?txn=' . urlencode($txnRef) . '&gateway=card');
    exit;
}

$paRes = $_POST['PaRes'] ?? '';
$md    = $_POST['MD'] ?? '';

$txnReference = trim($md ?: $txnRef);

if (!empty($paRes)) {
    dbExecute(
        "UPDATE transactions SET status='success' WHERE (transaction_reference = ? OR id = ?) AND status='pending'",
        [$txnReference, $txnRef]
    );
    $txn = uthenga_card_find_transaction($txnReference);
    if ($txn) {
        uthenga_card_confirm_booking($txn);
        uthenga_record_transaction_analytics([
            'transaction_reference' => $txn['transaction_reference'] ?? $txnReference,
            'booking_id' => $txn['booking_id'] ?? null,
            'user_id' => $txn['user_id'] ?? null,
            'gateway_name' => $txn['gateway_name'] ?? 'Card',
            'status' => 'success',
            'amount' => $txn['amount'] ?? 0,
            'created_at' => $txn['created_at'] ?? date('Y-m-d H:i:s'),
        ], 'status_updated');
    }
    header('Location: ' . BASE_URL . 'payments/success.php?txn=' . urlencode($txnReference) . '&gateway=card');
} else {
    dbExecute(
        "UPDATE transactions SET status='failed' WHERE transaction_reference = ? OR id = ?",
        [$txnReference, $txnRef]
    );
    $txn = uthenga_card_find_transaction($txnReference);
    if ($txn) {
        uthenga_record_transaction_analytics([
            'transaction_reference' => $txn['transaction_reference'] ?? $txnReference,
            'booking_id' => $txn['booking_id'] ?? null,
            'user_id' => $txn['user_id'] ?? null,
            'gateway_name' => $txn['gateway_name'] ?? 'Card',
            'status' => 'failed',
            'amount' => $txn['amount'] ?? 0,
            'created_at' => $txn['created_at'] ?? date('Y-m-d H:i:s'),
        ], 'status_updated');
    }
    header('Location: ' . BASE_URL . 'payments/checkout.php?error=3ds_failed&txn=' . urlencode($txnReference));
}
exit;
