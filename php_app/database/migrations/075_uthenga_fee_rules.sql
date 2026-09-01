-- Effective-dated commission/fee rule history. A transaction must retain the
-- rate that was active when it was created, not whatever admin has configured
-- by the time it's later reported on — never overwrite a row, only close it
-- (effective_to) and insert a new one.

CREATE TABLE IF NOT EXISTS uthenga_fee_rules (
    id                VARCHAR(36) NOT NULL PRIMARY KEY,
    service_category  VARCHAR(32) NOT NULL,
    commission_rate   DECIMAL(6,3) NOT NULL,
    service_fee       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    effective_from    DATETIME NOT NULL,
    effective_to      DATETIME NULL,
    -- Corrected for clean installations: production_schema.sql uses
    -- users.id BIGINT UNSIGNED. Existing VARCHAR deployments are handled by
    -- 084_fee_rule_actor_compatibility.sql without losing legacy text.
    created_by        BIGINT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fee_rules_lookup (service_category, effective_from, effective_to),
    CONSTRAINT fk_fee_rules_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed today's real effective baseline (confirmed: every category currently
-- resolves to the global commission_rate=10 fallback, 0 service fee) so
-- existing history reads as "created under this rule" rather than orphaned.
INSERT IGNORE INTO uthenga_fee_rules (id, service_category, commission_rate, service_fee, effective_from, created_at) VALUES
    (UUID(), 'accommodation', 10.000, 0.00, '2020-01-01 00:00:00', NOW()),
    (UUID(), 'event',         10.000, 0.00, '2020-01-01 00:00:00', NOW()),
    (UUID(), 'tour',          10.000, 0.00, '2020-01-01 00:00:00', NOW()),
    (UUID(), 'transport',     10.000, 0.00, '2020-01-01 00:00:00', NOW()),
    (UUID(), 'shop',          10.000, 0.00, '2020-01-01 00:00:00', NOW());

-- Frozen-rate columns on the payment engine's own intent record.
ALTER TABLE uthenga_payment_intents
    ADD COLUMN IF NOT EXISTS fee_rule_id VARCHAR(36) NULL AFTER policy_version,
    ADD COLUMN IF NOT EXISTS commission_rate DECIMAL(6,3) NULL AFTER fee_rule_id;

-- Quick Taxi's payment table has no JSON column yet (unlike `transactions`,
-- which already has one) — needed to carry the frozen fee rule through to
-- reconcile()/postExternalLedgers().
ALTER TABLE tie_transport_payments
    ADD COLUMN IF NOT EXISTS metadata LONGTEXT NULL AFTER verification;
