<?php
/**
 * Uthenga — Events Commercial Growth & Marketing Schema Migration.
 * Creates database tables for campaigns, promotions, promo codes, and ad cards.
 * Safe and idempotent.
 */
$db = new PDO(
    'mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;dbname=uthenga-db;charset=utf8mb4',
    'uthenga_user',
    'uthenga@646',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "Migrating Marketing & Commercial Growth schema...\n";

$db->exec("CREATE TABLE IF NOT EXISTS events_marketing_campaigns (
    id varchar(40) NOT NULL,
    listing_id varchar(30) NOT NULL,
    title varchar(200) NOT NULL,
    objective varchar(60) NOT NULL DEFAULT 'Ticket Sales',
    status varchar(20) NOT NULL DEFAULT 'active',
    target_audience varchar(100) NOT NULL DEFAULT 'All Customers',
    offer_type varchar(40) DEFAULT 'none',
    offer_val varchar(100) DEFAULT NULL,
    channel varchar(40) NOT NULL DEFAULT 'marketplace',
    start_date date DEFAULT NULL,
    end_date date DEFAULT NULL,
    auto_stop tinyint(1) NOT NULL DEFAULT 1,
    reach_count int(11) NOT NULL DEFAULT 0,
    click_count int(11) NOT NULL DEFAULT 0,
    sales_count int(11) NOT NULL DEFAULT 0,
    revenue_attributed decimal(12,2) NOT NULL DEFAULT 0.00,
    conversion_rate decimal(5,2) NOT NULL DEFAULT 0.00,
    headline text DEFAULT NULL,
    body_text text DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mkt_cmp_listing (listing_id),
    KEY idx_mkt_cmp_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$db->exec("CREATE TABLE IF NOT EXISTS events_marketing_promotions (
    id varchar(40) NOT NULL,
    listing_id varchar(30) NOT NULL,
    title varchar(200) NOT NULL,
    discount_text varchar(100) NOT NULL,
    valid_until date DEFAULT NULL,
    usage_limit int(11) NOT NULL DEFAULT 100,
    used_count int(11) NOT NULL DEFAULT 0,
    revenue_attributed decimal(12,2) NOT NULL DEFAULT 0.00,
    status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mkt_prm_listing (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$db->exec("CREATE TABLE IF NOT EXISTS events_marketing_promocodes (
    id varchar(40) NOT NULL,
    code varchar(50) NOT NULL,
    listing_id varchar(30) DEFAULT NULL,
    discount_type varchar(50) NOT NULL,
    usage_cap int(11) NOT NULL DEFAULT 100,
    used_count int(11) NOT NULL DEFAULT 0,
    sales_count int(11) NOT NULL DEFAULT 0,
    revenue_attributed decimal(12,2) NOT NULL DEFAULT 0.00,
    status varchar(20) NOT NULL DEFAULT 'Active',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_mkt_code_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$db->exec("CREATE TABLE IF NOT EXISTS events_marketing_adcards (
    id varchar(40) NOT NULL,
    listing_id varchar(30) DEFAULT NULL,
    template varchar(40) NOT NULL DEFAULT 'announcement',
    headline varchar(255) NOT NULL,
    subtitle varchar(255) DEFAULT NULL,
    price_badge varchar(100) DEFAULT NULL,
    cta_text varchar(50) NOT NULL DEFAULT 'GET TICKETS',
    accent_color varchar(20) NOT NULL DEFAULT '#f97316',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Seed initial campaigns if table is empty
$count = (int) $db->query("SELECT COUNT(*) FROM events_marketing_campaigns")->fetchColumn();
if ($count === 0) {
    echo "Seeding initial commercial campaigns & promo codes...\n";

    // Get an event listing_id
    $evtId = $db->query("SELECT id FROM listings WHERE listing_type='event' OR listing_type='events' LIMIT 1")->fetchColumn() ?: 'evt-demo-1';

    $db->exec("INSERT INTO events_marketing_campaigns (id, listing_id, title, objective, status, target_audience, offer_type, offer_val, channel, start_date, end_date, auto_stop, reach_count, click_count, sales_count, revenue_attributed, conversion_rate, headline, body_text) VALUES
    ('cmp-1', '$evtId', 'Music Festival Early Bird', 'Early Bird', 'active', 'All Customers', 'percentage', '30% OFF', 'marketplace', '2026-08-01', '2026-08-31', 1, 12840, 2340, 410, 1800000.00, 17.50, 'MALAWI MUSIC FESTIVAL 2026', 'Live at Kamuzu Stadium'),
    ('cmp-2', '$evtId', 'Business Summit VIP Pass Push', 'VIP Promotion', 'active', 'VIP Buyers', 'percentage', '10% OFF', 'email', '2026-08-10', '2026-09-15', 1, 18400, 3120, 620, 2400000.00, 19.80, 'MALAWIAN BUSINESS SUMMIT', 'Join business leaders at BICC'),
    ('cmp-3', '$evtId', 'Youth Workshop Early Registration', 'Ticket Sales', 'scheduled', 'High Intent Prospects', 'none', NULL, 'push', '2026-09-01', '2026-09-20', 1, 6200, 980, 140, 420000.00, 14.20, 'YOUTH EMPOWERMENT WORKSHOP', 'Crossroads Hotel Lilongwe'),
    ('cmp-4', '$evtId', 'Charity Gala Table Flash Sale', 'Early Bird', 'paused', 'Abandoned Checkout', 'flash', '25% OFF', 'sms', '2026-08-05', '2026-08-15', 1, 5400, 640, 70, 180000.00, 10.90, 'CHARITY GALA DINNER', 'Sunbird Capital');");

    $db->exec("INSERT INTO events_marketing_promotions (id, listing_id, title, discount_text, valid_until, usage_limit, used_count, revenue_attributed, status) VALUES
    ('prm-1', '$evtId', 'Early Bird 30% Discount', '30% OFF', '2026-08-31', 300, 142, 820000.00, 'active'),
    ('prm-2', '$evtId', 'Corporate Group 10% Pass', '10% OFF', '2026-10-10', 100, 43, 640000.00, 'active'),
    ('prm-3', '$evtId', 'VIP Upgrade Special', 'VIP Upgrade', '2026-08-30', 50, 18, 450000.00, 'active');");

    $db->exec("INSERT INTO events_marketing_promocodes (id, code, listing_id, discount_type, usage_cap, used_count, sales_count, revenue_attributed, status) VALUES
    ('code-1', 'MUSIC30', '$evtId', '30% OFF', 300, 142, 142, 820000.00, 'Active'),
    ('code-2', 'CORPORATE10', '$evtId', '10% OFF', 100, 43, 43, 640000.00, 'Active'),
    ('code-3', 'VIP2026', '$evtId', 'VIP Upgrade', 50, 18, 18, 450000.00, 'Active'),
    ('code-4', 'FLASH50', '$evtId', '50% OFF', 100, 89, 89, 320000.00, 'Expired');");
}

echo "✅ Marketing schema migration complete!\n";
