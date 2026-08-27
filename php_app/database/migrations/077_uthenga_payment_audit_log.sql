-- Uthenga Payment Engine — structured payment audit trail.
-- One row per state-changing action against a payment intent: actor,
-- timestamp, intent (transaction) reference, source, old state, new state.

CREATE TABLE IF NOT EXISTS uthenga_payment_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    intent_ref VARCHAR(32) NOT NULL,
    actor_id VARCHAR(30) NOT NULL,
    action VARCHAR(40) NOT NULL,
    source VARCHAR(20) NOT NULL,
    from_status VARCHAR(32) NULL,
    to_status VARCHAR(32) NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_audit_intent (intent_ref),
    INDEX idx_payment_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
