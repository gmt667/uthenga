<?php
/** CLI-only, read-only verification of the explicitly configured test boundary. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../tests/support/FinancialTestDatabase.php';
$config = UthengaFinancialTestDatabase::configuration(getenv());
if ($config['host'] !== '127.0.0.1' || $config['name'] !== 'uthenga_financial_test') throw new RuntimeException('Explicit financial test target mismatch.');
$pdo = UthengaFinancialTestDatabase::connect(getenv());
foreach (['uthenga_app', 'uthenga_db'] as $normalDatabase) {
    try {
        $pdo->exec('USE `' . $normalDatabase . '`');
        throw new RuntimeException('The test database user can access a normal Uthenga database.');
    } catch (PDOException $expected) {
        // Access denial is the expected isolation proof. Reconnect because USE
        // may leave a permissive server session in an implementation-specific state.
        $pdo = UthengaFinancialTestDatabase::connect(getenv());
    }
}
echo 'Financial test boundary confirmed: ' . $config['host'] . ' / ' . $config['name'] . PHP_EOL;
