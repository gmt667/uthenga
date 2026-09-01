<?php
/** Test-only MySQL/MariaDB boundary. Never falls back to application settings. */
final class UthengaFinancialTestDatabase
{
    public static function configuration(array $env): array
    {
        if (($env['UTHENGA_ENV'] ?? '') !== 'test') throw new RuntimeException('Financial database tests require UTHENGA_ENV=test.');
        foreach (['UTHENGA_TEST_DB_HOST','UTHENGA_TEST_DB_PORT','UTHENGA_TEST_DB_NAME','UTHENGA_TEST_DB_USER','UTHENGA_TEST_DB_PASSWORD'] as $key) {
            if (!isset($env[$key]) || $env[$key] === '') throw new RuntimeException('Missing required test database configuration.');
        }
        $host = strtolower((string) $env['UTHENGA_TEST_DB_HOST']);
        $name = (string) $env['UTHENGA_TEST_DB_NAME'];
        if (!in_array($host, ['127.0.0.1','localhost'], true) || !preg_match('/^[a-z0-9_]+_test(?:_[a-z0-9_]+)?$/i', $name)) {
            throw new RuntimeException('Financial database tests require an explicit local *_test database.');
        }
        return ['host'=>$host,'port'=>(int)$env['UTHENGA_TEST_DB_PORT'],'name'=>$name,'user'=>(string)$env['UTHENGA_TEST_DB_USER'],'password'=>(string)$env['UTHENGA_TEST_DB_PASSWORD']];
    }

    public static function connect(array $env): PDO
    {
        $config = self::configuration($env);
        $pdo = new PDO('mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['name'] . ';charset=utf8mb4', $config['user'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== $config['name']) throw new RuntimeException('Connected database does not match explicit test configuration.');
        return $pdo;
    }
}
