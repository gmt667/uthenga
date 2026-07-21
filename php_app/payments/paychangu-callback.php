<?php
/**
 * Uthenga - PayChangu Payment Callback Handler
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$paychanguSecret = PAYCHANGU_SECRET_KEY !== '' ? PAYCHANGU_SECRET_KEY : uthenga_env('PAYCHANGU_SECRET_KEY', '');
$isDemoMode      = !empty($_GET['demo']) || !empty($_POST['demo']);
$txnRef          = trim((string)($_GET['txn'] ?? $_POST['reference'] ?? $_POST['tx_ref'] ?? ''));

function uthenga_paychangu_find_transaction(string $reference): ?array {
    if ($reference === '') {
        return null;
    }

    return dbQueryOne(
        'SELECT * FROM transactions WHERE transaction_reference = ? OR id = ? LIMIT 1',
        [$reference, $reference]
    ) ?: null;
}

function uthenga_paychangu_confirm_booking(array $txn): void {
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

function uthenga_paychangu_record_analytics(array $txn, string $status): void {
    $txn['status'] = $status;
    uthenga_record_transaction_analytics($txn, 'status_updated');
}

if ($isDemoMode || $paychanguSecret === '') {
    if ($txnRef !== '') {
        dbExecute(
            "UPDATE transactions
             SET status='success', updated_at = NOW()
             WHERE (transaction_reference = ? OR id = ?) AND status IN ('pending', 'initiated')",
            [$txnRef, $txnRef]
        );
        $txn = uthenga_paychangu_find_transaction($txnRef);
        if ($txn) {
            uthenga_paychangu_confirm_booking($txn);
            uthenga_finance_record_sale(uthenga_finance_context_from_booking($txn));
            uthenga_paychangu_record_analytics($txn, 'success');
        }
    }

    header('Location: ' . BASE_URL . 'payments/success.php?txn=' . urlencode($txnRef) . '&gateway=paychangu');
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';
$payload  = json_decode($rawBody, true);
if (!is_array($payload)) {
    $payload = [];
}

$reference = trim((string)($payload['reference'] ?? $payload['tx_ref'] ?? $payload['transaction_reference'] ?? $txnRef));
$status    = strtolower(trim((string)($payload['status'] ?? $payload['payment_status'] ?? $payload['transaction_status'] ?? '')));
$signature = trim((string)($_SERVER[PAYCHANGU_WEBHOOK_SIGNATURE_HEADER] ?? ''));

if ($signature === '' && strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if ($txnRef !== '') {
        header('Location: ' . BASE_URL . 'payments/success.php?txn=' . urlencode($txnRef) . '&gateway=paychangu');
        exit;
    }
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

$txn = uthenga_paychangu_find_transaction($reference);
$normalizedStatus = match ($status) {
    'successful', 'success', 'paid', 'completed', 'succeeded' => 'success',
    'failed', 'cancelled', 'canceled', 'expired' => 'failed',
    'pending', 'processing', 'initiated' => 'pending',
    default => $status !== '' ? $status : 'pending',
};

$metadata = [
    'paychangu_reference' => $reference,
    'paychangu_status' => $status,
    'payload' => $payload,
];

if ($normalizedStatus === 'success') {
    dbExecute(
        "UPDATE transactions
         SET status='success', metadata = COALESCE(?, metadata), updated_at = NOW()
         WHERE (transaction_reference = ? OR id = ?) AND status IN ('pending', 'initiated')",
        [json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $reference, $txnRef]
    );
    $txn = $txn ?: uthenga_paychangu_find_transaction($reference);
    if ($txn) {
        $txn['status'] = 'success';
        uthenga_paychangu_confirm_booking($txn);
        uthenga_finance_record_sale(uthenga_finance_context_from_booking($txn));
        uthenga_paychangu_record_analytics($txn, 'success');
    }
} elseif ($normalizedStatus === 'failed') {
    dbExecute(
        "UPDATE transactions
         SET status='failed', metadata = COALESCE(?, metadata), updated_at = NOW()
         WHERE transaction_reference = ? OR id = ?",
        [json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $reference, $txnRef]
    );
    $txn = $txn ?: uthenga_paychangu_find_transaction($reference);
    if ($txn) {
        $txn['status'] = 'failed';
        uthenga_paychangu_record_analytics($txn, 'failed');
    }
} else {
    dbExecute(
        "UPDATE transactions
         SET status = COALESCE(status, 'pending'),
             metadata = COALESCE(?, metadata),
             updated_at = NOW()
         WHERE transaction_reference = ? OR id = ?",
        [json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $reference, $txnRef]
    );
    if ($txn) {
        $txn['status'] = 'pending';
        uthenga_paychangu_record_analytics($txn, 'pending');
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'reference' => $reference, 'status' => $normalizedStatus]);
