<?php
/**
 * Uthenga Payment Engine — Webhook Gateway & Verification Service
 * Asynchronous Payment Verification Gateway for PayChangu Webhooks
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/payment_engine.php';

header('Content-Type: application/json');

$rawBody = file_get_contents('php://input');
$paychanguSecret = uthenga_env('TIE_PAYCHANGU_SECRET_KEY', uthenga_env('PAYCHANGU_SECRET_KEY', ''));

$signature256 = $_SERVER['HTTP_SIGNATURE'] ?? $_SERVER['HTTP_X_PAYCHANGU_SIGNATURE'] ?? '';
$isDemo = (APP_ENV === 'development' || $paychanguSecret === '');

// Outside demo mode, a signature is mandatory, not merely validated-if-present —
// a request with the header simply omitted must not be able to skip verification.
if (!$isDemo) {
    if ($signature256 === '') {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Missing webhook signature']);
        exit;
    }
    $expected256 = hash_hmac('sha256', $rawBody, $paychanguSecret);
    $expected512 = hash_hmac('sha512', $rawBody, $paychanguSecret);

    if (!hash_equals($expected256, $signature256) && !hash_equals($expected512, $signature256)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid webhook signature']);
        exit;
    }
}

$data = json_decode($rawBody, true) ?: $_POST;
$txRef = trim((string)($data['tx_ref'] ?? $data['reference'] ?? $_GET['tx_ref'] ?? ''));

if ($txRef === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing transaction reference']);
    exit;
}

// Log Webhook Payload Audit
try {
    dbExecute("
        INSERT INTO uthenga_payment_webhook_logs (provider, event_type, tx_ref, signature, payload, verification_status)
        VALUES ('paychangu', ?, ?, ?, ?, 'RECEIVED')
    ", [
        $data['event'] ?? 'charge.completed',
        $txRef,
        $signature256,
        $rawBody ?: json_encode($_REQUEST)
    ]);
} catch (Throwable $e) {}

// Execute Mandatory Double-Check Verification and Post 3 Ledgers
$result = UthengaPaymentEngine::verifyAndPostLedgers($txRef, $data);

if ($result['success']) {
    echo json_encode([
        'status'  => 'success',
        'message' => 'Payment verified and ledgers updated successfully.',
        'receipt' => $result['receipt_number'] ?? null,
    ]);
} else {
    http_response_code(422);
    echo json_encode([
        'status'  => 'failed',
        'message' => $result['error'] ?? 'Verification failed',
    ]);
}
