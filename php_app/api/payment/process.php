<?php
/**
 * Uthenga Payment Engine — RESTful API Endpoint
 * Handles Intent Creation, Method Selection, Charge Authorization, and Real-time Status Polling.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/payment_engine.php';

header('Content-Type: application/json');

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

/** Mirrors validateCsrf() (config.php) but reads the JSON body this endpoint accepts, not $_POST. */
function uthenga_payment_csrf_ok(array $input): bool {
    return isset($_SESSION['csrf_token'], $input['csrf_token'])
        && hash_equals((string) $_SESSION['csrf_token'], (string) $input['csrf_token']);
}

try {
    if ($action === 'create_intent') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        if (!uthenga_payment_csrf_ok($input)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
            exit;
        }

        $result = UthengaPaymentEngine::createIntent([
            'customer_id'     => $_SESSION['user_id'] ?? 'guest',
            'service_type'    => $input['service_type'] ?? 'accommodation',
            'service_id'      => $input['service_id'] ?? 'serv-1',
            'booking_id'      => $input['booking_id'] ?? '',
            'amount'          => (float)($input['amount'] ?? 0),
            'currency'        => $input['currency'] ?? 'MWK',
            'idempotency_key' => $input['idempotency_key'] ?? '',
        ]);

        echo json_encode($result);
        exit;
    }

    if ($action === 'create_balance_intent') {
        // The "pay the rest of a deposit-mode reservation" flow — the amount
        // is always derived server-side from the reservation's own real
        // balance_due, never taken from client input, so a customer cannot
        // manipulate what they're asked to pay.
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $bookingId = trim((string) ($input['booking_id'] ?? ''));

        if (!uthenga_payment_csrf_ok($input)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
            exit;
        }
        if ($bookingId === '') {
            echo json_encode(['success' => false, 'error' => 'Missing booking reference.']);
            exit;
        }

        $reservation = dbQueryOne("SELECT r.*, b.listing_id, b.customer_id FROM tie_accommodation_reservations r INNER JOIN bookings b ON b.id = r.booking_id WHERE r.booking_id = ? LIMIT 1", [$bookingId]);
        if (!$reservation) {
            echo json_encode(['success' => false, 'error' => 'Reservation not found for this booking.']);
            exit;
        }
        if ((string) $reservation['customer_id'] !== (string) ($_SESSION['user_id'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not authorized to pay this reservation.']);
            exit;
        }
        $balanceDue = (float) $reservation['balance_due'];
        if ($balanceDue <= 0.009) {
            echo json_encode(['success' => false, 'error' => 'This reservation has no outstanding balance.']);
            exit;
        }

        $result = UthengaPaymentEngine::createIntent([
            'customer_id'     => $_SESSION['user_id'],
            'service_type'    => 'accommodation',
            'service_id'      => $reservation['listing_id'],
            'booking_id'      => $bookingId,
            'amount'          => $balanceDue,
            'currency'        => $reservation['currency'] ?: 'MWK',
            'idempotency_key' => 'balance_' . $bookingId . '_' . bin2hex(random_bytes(6)),
        ]);

        echo json_encode($result);
        exit;
    }

    if ($action === 'select_method') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $intentRef = trim((string)($input['intent_ref'] ?? ''));
        $method    = trim((string)($input['method'] ?? 'airtel'));
        $phone     = trim((string)($input['phone'] ?? ''));

        if ($intentRef === '') {
            echo json_encode(['success' => false, 'error' => 'Missing intent reference.']);
            exit;
        }

        if (!uthenga_payment_csrf_ok($input)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
            exit;
        }

        $result = UthengaPaymentEngine::selectMethod($intentRef, $method, $phone);
        echo json_encode($result);
        exit;
    }

    if ($action === 'cancel_intent') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $intentRef = trim((string)($input['intent_ref'] ?? ''));

        if ($intentRef === '') {
            echo json_encode(['success' => false, 'error' => 'Missing intent reference.']);
            exit;
        }

        if (!uthenga_payment_csrf_ok($input)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
            exit;
        }

        $result = UthengaPaymentEngine::cancelIntent($intentRef);
        echo json_encode($result);
        exit;
    }

    if ($action === 'check_status') {
        $intentRef = trim((string)($_GET['intent_ref'] ?? $_POST['intent_ref'] ?? ''));
        if ($intentRef === '') {
            echo json_encode(['success' => false, 'error' => 'Missing intent reference.']);
            exit;
        }

        // Try double-check verification
        $result = UthengaPaymentEngine::verifyAndPostLedgers($intentRef);
        echo json_encode($result);
        exit;
    }

    if ($action === 'demo_simulate') {
        if (APP_ENV !== 'development') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sandbox payment simulation is not available in this environment.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        if (!uthenga_payment_csrf_ok($input)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
            exit;
        }

        $intentRef = trim((string)($input['intent_ref'] ?? ''));

        $result = UthengaPaymentEngine::verifyAndPostLedgers($intentRef, ['demo' => true]);
        echo json_encode($result);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
