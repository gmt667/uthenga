<?php
/**
 * Uthenga — Check-In LIVE (Phase: Operational Command Center)
 *
 * Adds the immutable scan log and exit-tracking columns for the check-in
 * operations workspace. Idempotent — safe to run repeatedly.
 */
$db = new PDO(
    'mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;dbname=uthenga-db;charset=utf8mb4',
    'uthenga_user',
    'uthenga@646',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function ensure_column(PDO $db, string $table, string $column, string $definition): void
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "  + $table.$column\n";
    }
}

echo "Migrating schema for Check-In LIVE console...\n";

$db->exec(
    "CREATE TABLE IF NOT EXISTS checkin_scans (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        request_id varchar(32) NOT NULL,
        listing_id varchar(30) NOT NULL,
        ticket_id varchar(64) DEFAULT NULL,
        booking_id varchar(20) DEFAULT NULL,
        decision varchar(20) NOT NULL,
        reason_code varchar(40) DEFAULT NULL,
        gate varchar(40) DEFAULT NULL,
        device_id varchar(40) DEFAULT NULL,
        operator_id varchar(30) DEFAULT NULL,
        operator_name varchar(120) DEFAULT NULL,
        source varchar(20) NOT NULL DEFAULT 'scan',
        idempotency_key varchar(48) DEFAULT NULL,
        details json DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ck_listing (listing_id, created_at),
        KEY idx_ck_ticket (ticket_id),
        KEY idx_ck_key (idempotency_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "  + table checkin_scans\n";

ensure_column($db, 'event_tickets', 'checked_out_at', 'datetime DEFAULT NULL');
ensure_column($db, 'event_tickets', 'checked_out_by', 'varchar(30) DEFAULT NULL');
ensure_column($db, 'event_tickets', 'checked_out_gate', 'varchar(40) DEFAULT NULL');

echo "Done.\n";
