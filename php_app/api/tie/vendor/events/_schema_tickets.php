<?php
/**
 * Uthenga — Ticket Commerce & Ticket Lifecycle Control Center (Phase: Tickets Console)
 *
 * Extends ticket_types with lifecycle/operations fields and creates the
 * per-ticket issuance, transfer, refund and audit tables used by the
 * organizer Ticket Operations workspace. Idempotent — safe to run repeatedly.
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

echo "Migrating schema for ticket commerce console...\n";

ensure_column($db, 'ticket_types', 'category', "varchar(60) NULL DEFAULT NULL AFTER `name`");
ensure_column($db, 'ticket_types', 'internal_code', "varchar(40) NULL DEFAULT NULL AFTER `category`");
ensure_column($db, 'ticket_types', 'fee_percent', "decimal(5,2) NOT NULL DEFAULT 10.00 AFTER `price`");
ensure_column($db, 'ticket_types', 'max_per_customer', "int(10) unsigned NOT NULL DEFAULT 0 AFTER `total_quantity`");
ensure_column($db, 'ticket_types', 'min_qty', "int(10) unsigned NOT NULL DEFAULT 1 AFTER `max_per_customer`");
ensure_column($db, 'ticket_types', 'ticket_status', "varchar(20) NOT NULL DEFAULT 'ACTIVE' AFTER `is_active`");
ensure_column($db, 'ticket_types', 'access_rules', "longtext NULL DEFAULT NULL AFTER `ticket_status`");
ensure_column($db, 'ticket_types', 'branding', "longtext NULL DEFAULT NULL AFTER `access_rules`");

$db->exec("CREATE TABLE IF NOT EXISTS event_tickets (
    id varchar(40) NOT NULL,
    listing_id varchar(30) NOT NULL,
    ticket_type_id bigint(20) unsigned NOT NULL,
    booking_id varchar(20) DEFAULT NULL,
    holder_name varchar(120) NOT NULL,
    holder_email varchar(180) DEFAULT NULL,
    holder_phone varchar(30) DEFAULT NULL,
    qr_token char(64) NOT NULL,
    verification_signature varchar(96) DEFAULT NULL,
    status varchar(20) NOT NULL DEFAULT 'ISSUED',
    checked_in_at datetime DEFAULT NULL,
    checked_in_by varchar(30) DEFAULT NULL,
    transferred_to_id varchar(40) DEFAULT NULL,
    last_sent_at datetime DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_evticket_listing (listing_id),
    KEY idx_evticket_type (ticket_type_id),
    KEY idx_evticket_booking (booking_id),
    KEY idx_evticket_status (status),
    KEY idx_evticket_qr (qr_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS event_ticket_refunds (
    id varchar(30) NOT NULL,
    listing_id varchar(30) NOT NULL,
    booking_id varchar(20) DEFAULT NULL,
    ticket_id varchar(40) DEFAULT NULL,
    amount decimal(15,2) NOT NULL DEFAULT 0,
    currency char(3) NOT NULL DEFAULT 'MWK',
    reason varchar(255) DEFAULT NULL,
    status varchar(20) NOT NULL DEFAULT 'PENDING',
    requested_by varchar(30) DEFAULT NULL,
    requested_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at datetime DEFAULT NULL,
    decided_by varchar(30) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_refund_listing (listing_id),
    KEY idx_refund_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS event_ticket_transfers (
    id varchar(30) NOT NULL,
    listing_id varchar(30) NOT NULL,
    ticket_id varchar(40) NOT NULL,
    from_holder_name varchar(120) NOT NULL,
    to_holder_name varchar(120) NOT NULL,
    to_phone varchar(30) DEFAULT NULL,
    to_email varchar(180) DEFAULT NULL,
    initiated_by varchar(30) DEFAULT NULL,
    initiated_by_type varchar(20) NOT NULL DEFAULT 'CUSTOMER',
    reason varchar(255) DEFAULT NULL,
    status varchar(20) NOT NULL DEFAULT 'PENDING',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_transfer_ticket (ticket_id),
    KEY idx_transfer_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS event_ticket_audit (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    listing_id varchar(30) NOT NULL,
    ticket_type_id bigint(20) unsigned DEFAULT NULL,
    ticket_id varchar(40) DEFAULT NULL,
    booking_id varchar(20) DEFAULT NULL,
    actor_id varchar(30) DEFAULT NULL,
    actor_name varchar(120) DEFAULT NULL,
    action varchar(60) NOT NULL,
    details longtext DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_listing (listing_id),
    KEY idx_audit_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "Done.\n";