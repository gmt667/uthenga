<?php
require_once __DIR__ . '/support/FinancialTestDatabase.php';
$valid = ['UTHENGA_ENV'=>'test','UTHENGA_TEST_DB_HOST'=>'127.0.0.1','UTHENGA_TEST_DB_PORT'=>'3307','UTHENGA_TEST_DB_NAME'=>'uthenga_financial_test','UTHENGA_TEST_DB_USER'=>'test_user','UTHENGA_TEST_DB_PASSWORD'=>'test_password'];
if (UthengaFinancialTestDatabase::configuration($valid)['name'] !== 'uthenga_financial_test') throw new RuntimeException('Safe test configuration was rejected.');
foreach ([['UTHENGA_ENV'=>'production'] + $valid, array_diff_key($valid,['UTHENGA_TEST_DB_PASSWORD'=>true]), array_replace($valid,['UTHENGA_TEST_DB_NAME'=>'uthenga_db']), array_replace($valid,['UTHENGA_TEST_DB_HOST'=>'db.example.com'])] as $unsafe) {
    try { UthengaFinancialTestDatabase::configuration($unsafe); throw new RuntimeException('Unsafe database configuration was accepted.'); } catch (RuntimeException $expected) {}
}
echo "Financial test database guard tests passed.\n";
