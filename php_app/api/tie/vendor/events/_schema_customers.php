<?php
/**
 * Uthenga — Events Customer CRM Schema Migration.
 * Creates database tables for CRM notes, tags, and customer segments.
 * Safe and idempotent.
 */
$db = new PDO(
    'mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;dbname=uthenga-db;charset=utf8mb4',
    'uthenga_user',
    'uthenga@646',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "Migrating Events Customer CRM schema...\n";

$db->exec("CREATE TABLE IF NOT EXISTS events_customer_notes (
    id varchar(40) NOT NULL,
    vendor_id varchar(40) NOT NULL,
    customer_id varchar(40) NOT NULL,
    note text NOT NULL,
    author_name varchar(100) NOT NULL DEFAULT 'Organizer',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cus_notes_vendor (vendor_id),
    KEY idx_cus_notes_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$db->exec("CREATE TABLE IF NOT EXISTS events_customer_segments (
    id varchar(40) NOT NULL,
    vendor_id varchar(40) NOT NULL,
    title varchar(150) NOT NULL,
    description varchar(255) DEFAULT NULL,
    rules_json text DEFAULT NULL,
    customer_count int(11) NOT NULL DEFAULT 0,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cus_seg_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$db->exec("CREATE TABLE IF NOT EXISTS events_customer_tags (
    id varchar(40) NOT NULL,
    vendor_id varchar(40) NOT NULL,
    customer_id varchar(40) NOT NULL,
    tag_name varchar(60) NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_cus_tag (vendor_id, customer_id, tag_name),
    KEY idx_cus_tags_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

echo "Events Customer CRM schema migration complete!\n";
