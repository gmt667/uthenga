<?php
/** CLI-only bootstrap. It never creates, drops, or selects a non-test database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../tests/support/FinancialTestDatabase.php';
$pdo = UthengaFinancialTestDatabase::connect($_ENV + getenv());
$root = dirname(__DIR__);
// Minimal, synthetic, production-compatible prerequisites. These are test
// harness tables, not a replacement for the application's normal migrations.
// production_schema.sql defines users.id as BIGINT UNSIGNED; migration 075's
// VARCHAR(30) FK is therefore not clean-schema compatible on its own.
$pdo->exec('CREATE TABLE IF NOT EXISTS users (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, name VARCHAR(150) NOT NULL DEFAULT \'synthetic\') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec('CREATE TABLE IF NOT EXISTS tie_transport_payments (id VARCHAR(64) NOT NULL PRIMARY KEY, verification LONGTEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
$pdo->exec('CREATE TABLE IF NOT EXISTS tie_accommodation_refund_requests (id VARCHAR(64) NOT NULL PRIMARY KEY, reservation_id VARCHAR(64) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
$migrations = ['073_uthenga_payment_ledgers.sql','075_uthenga_fee_rules.sql','076_uthenga_refund_ledger.sql','081_financial_control_hardening.sql','082_provider_callback_receipts.sql','083_provider_callback_receipt_processing.sql','084_fee_rule_actor_compatibility.sql'];
$pdo->exec('CREATE TABLE IF NOT EXISTS uthenga_test_migrations (filename VARCHAR(191) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
foreach ($migrations as $filename) {
    $seen=$pdo->prepare('SELECT 1 FROM uthenga_test_migrations WHERE filename=?');$seen->execute([$filename]); if($seen->fetchColumn()) continue;
    $sql=file_get_contents($root . '/database/migrations/' . $filename); if($sql===false) throw new RuntimeException('Missing migration: '.$filename);
    try {
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $pdo->exec($statement);
        $pdo->prepare('INSERT INTO uthenga_test_migrations (filename) VALUES (?)')->execute([$filename]);
    } catch (PDOException $error) {
        fwrite(STDERR, 'Bootstrap failed at ' . $filename . ' (SQLSTATE ' . ($error->errorInfo[0] ?? $error->getCode()) . "). Run the guarded reset before retrying.\n");
        exit(1);
    }
}
echo "Financial test schema bootstrap completed.\n";
