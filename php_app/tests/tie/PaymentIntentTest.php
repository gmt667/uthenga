<?php
/** Phases 15/16 payment-intent contracts and provider-boundary coverage. */
require_once __DIR__ . '/../../config.php'; require_once __DIR__ . '/../../db.php'; require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_payment_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException('Assertion failed: ' . $message); }

$selections = [['service_id' => 'EVENT-1', 'resource_type' => 'ticket_type', 'resource_id' => 1, 'quantity' => 1]];
$request = UthengaTiePaymentContracts::start(['plan_id' => 'TIEPLAN-0123456789ABCDEF', 'idempotency_key' => 'payment-intent-key-0123456789', 'selections' => $selections]);
tie_payment_assert($request->planId === 'TIEPLAN-0123456789ABCDEF', 'Payment contract retains the approved plan reference.');
try { UthengaTiePaymentContracts::start(['plan_id' => 'TIEPLAN-0123456789ABCDEF', 'idempotency_key' => 'short', 'selections' => $selections]); throw new RuntimeException('Weak idempotency key was accepted.'); } catch (UthengaTieException $error) { tie_payment_assert($error->type() === 'validation_error', 'Payment contract rejects weak idempotency keys.'); }

tie_payment_assert(UthengaTiePaymentState::transition(UthengaTiePaymentState::QUOTED, UthengaTiePaymentState::HOLD_ACQUIRED), 'A quote can acquire an inventory hold.');
tie_payment_assert(UthengaTiePaymentState::transition(UthengaTiePaymentState::PAYMENT_PENDING, UthengaTiePaymentState::VERIFIED), 'A pending payment can become verified only through the state machine.');
tie_payment_assert(!UthengaTiePaymentState::transition(UthengaTiePaymentState::CHECKOUT_READY, UthengaTiePaymentState::BOOKED), 'Checkout readiness cannot skip provider verification and booking handoff.');
tie_payment_assert(!UthengaTiePaymentState::transition(UthengaTiePaymentState::VERIFIED, UthengaTiePaymentState::BOOKED), 'Verified payment cannot skip the booking-commit state.');

$gateway = new UthengaTiePaychanguGateway('api-test-not-used', 'webhook-test-secret'); $payload = '{"event":"checkout.payment","data":{"tx_ref":"TIEPAY-TEST"}}'; $signature = hash_hmac('sha256', $payload, 'webhook-test-secret');
tie_payment_assert($gateway->verifyWebhookSignature($payload, $signature), 'PayChangu webhook signatures use a distinct HMAC webhook secret.');
tie_payment_assert(!$gateway->verifyWebhookSignature($payload, hash_hmac('sha256', $payload, 'wrong-secret')), 'Invalid webhook signatures are rejected.');

$unavailable = new UthengaTieUnavailableInventoryHoldProvider();
try { $unavailable->acquire([], []); throw new RuntimeException('Unavailable inventory hold provider accepted a hold.'); } catch (UthengaTieException $error) { tie_payment_assert($error->type() === 'provider_error', 'Payment cannot create checkout without an atomic inventory hold provider.'); }
tie_payment_assert(UthengaTiePaychanguGatewayFactory::configured() instanceof UthengaTiePaymentGateway, 'Payment gateway factory always returns a provider boundary.');

if ($pdo instanceof PDO) {
    $user = $pdo->query('SELECT id, name FROM users LIMIT 1')->fetch(); tie_payment_assert(is_array($user), 'Configured database has a user for inventory-hold integration.');
    $listingId = 'TIEHOLD' . bin2hex(random_bytes(4)); $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO listings (id, listing_type, title, description, location, image, vendor_id, vendor_name, meta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$listingId, 'event', 'Hold test event', 'Temporary test event', 'Lilongwe', 'test.jpg', $user['id'], $user['name'], '{}']);
        $pdo->prepare('INSERT INTO ticket_types (listing_id, name, price, total_quantity, remaining_quantity, is_active) VALUES (?, ?, ?, ?, ?, 1)')->execute([$listingId, 'Standard', 12500, 5, 5]); $resourceId = (int) $pdo->lastInsertId();
        $provider = new UthengaTieMariaDbInventoryHoldProvider($pdo); $plan = ['plan_id' => 'TIEPLAN-HOLD-TEST', 'user_id' => $user['id'], 'trip_summary' => ['currency' => 'MWK', 'start_date' => '2026-08-10', 'end_date' => '2026-08-11'], 'activities' => [['service_id' => $listingId, 'category' => 'event']]]; $selected = [['service_id' => $listingId, 'resource_type' => 'ticket_type', 'resource_id' => $resourceId, 'quantity' => 2]];
        $quote = $provider->quote($plan, $selected); tie_payment_assert($quote['amount'] === 25000.0, 'Authoritative inventory quote uses selected ticket pricing.'); $quote['selections'] = $selected; $hold = $provider->acquire($plan, $quote); tie_payment_assert((int) $pdo->query("SELECT remaining_quantity FROM ticket_types WHERE id = $resourceId")->fetchColumn() === 3, 'Atomic hold decrements ticket inventory.'); $provider->release($hold['hold_id']); tie_payment_assert((int) $pdo->query("SELECT remaining_quantity FROM ticket_types WHERE id = $resourceId")->fetchColumn() === 5, 'Released hold restores ticket inventory.');
    } finally { $pdo->rollBack(); }
}

echo "TIE Phases 15/16 payment intent tests passed.\n";
