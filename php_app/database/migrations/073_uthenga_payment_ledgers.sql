-- Uthenga Payment Engine — payment intents + 3 ledgers + webhook audit log.
-- Converts includes/payment_schema.php's runtime CREATE TABLE IF NOT EXISTS
-- bootstrap into a proper tracked migration, per project convention.

CREATE TABLE IF NOT EXISTS uthenga_payment_intents (
    id VARCHAR(64) PRIMARY KEY,
    intent_ref VARCHAR(32) NOT NULL UNIQUE,
    customer_id VARCHAR(64) NOT NULL,
    service_type VARCHAR(32) NOT NULL,
    service_id VARCHAR(64) NOT NULL,
    booking_id VARCHAR(64) NULL,
    gross_amount DECIMAL(14,2) NOT NULL,
    platform_fee DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    vendor_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'MWK',
    payment_method VARCHAR(32) NULL,
    phone_number VARCHAR(32) NULL,
    provider_name VARCHAR(32) NOT NULL DEFAULT 'paychangu',
    provider_tx_ref VARCHAR(128) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'CREATED',
    idempotency_key VARCHAR(128) NULL,
    policy_version VARCHAR(16) NOT NULL DEFAULT 'v1.0',
    expires_at DATETIME NULL,
    verification LONGTEXT NULL,
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_service (service_type, service_id),
    INDEX idx_status (status),
    INDEX idx_tx_ref (provider_tx_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uthenga_customer_ledger (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    payment_intent_id VARCHAR(64) NOT NULL,
    intent_ref VARCHAR(32) NOT NULL,
    customer_id VARCHAR(64) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'MWK',
    payment_method VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    receipt_number VARCHAR(64) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_intent (payment_intent_id),
    INDEX idx_customer_ledger (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uthenga_revenue_ledger (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    payment_intent_id VARCHAR(64) NOT NULL,
    intent_ref VARCHAR(32) NOT NULL,
    service_category VARCHAR(32) NOT NULL,
    gross_amount DECIMAL(14,2) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL,
    platform_fee DECIMAL(14,2) NOT NULL,
    provider_fee DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    net_revenue DECIMAL(14,2) NOT NULL,
    policy_version VARCHAR(16) NOT NULL DEFAULT 'v1.0',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_intent_rev (payment_intent_id),
    INDEX idx_category (service_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uthenga_vendor_payable_ledger (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    vendor_id VARCHAR(64) NOT NULL,
    payment_intent_id VARCHAR(64) NOT NULL,
    intent_ref VARCHAR(32) NOT NULL,
    service_category VARCHAR(32) NOT NULL,
    gross_amount DECIMAL(14,2) NOT NULL,
    commission_fee DECIMAL(14,2) NOT NULL,
    net_payable DECIMAL(14,2) NOT NULL,
    payout_status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
    settled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vendor (vendor_id),
    INDEX idx_payout (payout_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS uthenga_payment_webhook_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(32) NOT NULL DEFAULT 'paychangu',
    event_type VARCHAR(64) NOT NULL,
    tx_ref VARCHAR(128) NULL,
    signature VARCHAR(256) NULL,
    payload LONGTEXT NOT NULL,
    verification_status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tx (tx_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
