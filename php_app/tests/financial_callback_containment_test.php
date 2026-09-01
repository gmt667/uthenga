<?php
/** Static regression test: D2.3 callback paths must cross the containment gate. */
$root = dirname(__DIR__);
$paths = [
    'api/payment/webhook/paychangu.php',
    'api/payment/process.php',
    'api/tie/payments/paychangu-webhook.php',
    'api/tie/transport-payment/webhook.php',
    'api/tie/transport-ticket-payment/webhook.php',
    'api/tie/admin/payments-reconcile.php',
    'tools/reconcile_payments.php',
    'includes/payment_engine.php',
    'includes/tie/Payment.php',
    'includes/tie/TransportPayment.php',
    'includes/tie/BusOperations.php',
];
foreach ($paths as $path) {
    $contents = file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    if ($contents === false || !str_contains($contents, 'uthenga_financial_callback_commit_allowed')) {
        throw new RuntimeException('Callback containment gate is missing from ' . $path);
    }
}
if (!is_file($root . '/database/migrations/082_provider_callback_receipts.sql')) {
    throw new RuntimeException('Provider callback receipt migration is missing.');
}
echo "Financial callback containment tests passed.\n";
