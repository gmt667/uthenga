<?php
$bootstrap = file_get_contents(dirname(__DIR__) . '/tools/financial_test_bootstrap.php');
if ($bootstrap === false) throw new RuntimeException('Bootstrap is missing.');
foreach (['CREATE TABLE IF NOT EXISTS users','BIGINT UNSIGNED','075_uthenga_fee_rules.sql','081_financial_control_hardening.sql','082_provider_callback_receipts.sql','083_provider_callback_receipt_processing.sql','084_fee_rule_actor_compatibility.sql','Bootstrap failed at'] as $required) {
    if (!str_contains($bootstrap, $required)) throw new RuntimeException('Missing bootstrap prerequisite or diagnostic: ' . $required);
}
echo "Financial bootstrap order tests passed.\n";
