<?php
require_once dirname(__DIR__) . '/includes/financial_callbacks.php';
$adapter = new UthengaPaychanguCallbackAdapter();
$raw = json_encode(['event_type'=>'checkout.payment','tx_ref'=>'U-TEST-001','charge_id'=>'charge-test-1','currency'=>'MWK','amount'=>'1000.00','status'=>'success'], JSON_UNESCAPED_SLASHES);
$secret = 'test-webhook-secret';
$event = $adapter->authenticate($raw, hash_hmac('sha256', $raw, $secret), $secret);
if ($event['state'] !== 'SUCCESSFUL' || $event['amount_minor'] !== 100000 || $event['reference'] !== 'U-TEST-001') throw new RuntimeException('Valid PayChangu fixture was not normalized.');
foreach (['', str_repeat('0', 64)] as $signature) {
    try { $adapter->authenticate($raw, $signature, $secret); throw new RuntimeException('Invalid signature was accepted.'); } catch (InvalidArgumentException $expected) {}
}
try { $adapter->authenticate('{', hash_hmac('sha256', '{', $secret), $secret); throw new RuntimeException('Malformed payload was accepted.'); } catch (InvalidArgumentException $expected) {}
$unknownRaw = json_encode(['tx_ref'=>'a','charge_id'=>'b','currency'=>'MWK','amount'=>'1','status'=>'invented']);
$unknown = $adapter->authenticate($unknownRaw, hash_hmac('sha256', $unknownRaw, $secret), $secret);
if ($unknown['state'] !== 'UNKNOWN') throw new RuntimeException('Unknown provider state was not contained.');
echo "PayChangu callback adapter tests passed.\n";
