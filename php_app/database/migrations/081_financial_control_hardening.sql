-- Phase D: maker-checker financial review records. Apply through the normal
-- migration runner; this file is intentionally not executed by web requests.
CREATE TABLE IF NOT EXISTS uthenga_financial_review_requests (
    id VARCHAR(40) PRIMARY KEY,
    domain VARCHAR(32) NOT NULL,
    review_version INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'MWK',
    provider_or_channel VARCHAR(80) NOT NULL,
    external_reference VARCHAR(160) NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    period_start DATE NULL,
    period_end DATE NULL,
    evidence_reference VARCHAR(255) NULL,
    supporting_note VARCHAR(500) NOT NULL,
    payload_json LONGTEXT NULL,
    maker_id VARCHAR(30) NOT NULL,
    checker_id VARCHAR(30) NULL,
    decision_reason VARCHAR(500) NULL,
    executed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_finance_idempotency (domain, idempotency_key),
    UNIQUE KEY uq_finance_external_reference (domain, provider_or_channel, external_reference),
    KEY idx_finance_review_queue (domain, status, created_at),
    KEY idx_finance_review_period (domain, period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS uthenga_financial_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    review_request_id VARCHAR(40) NOT NULL,
    actor_id VARCHAR(30) NOT NULL,
    actor_role VARCHAR(80) NOT NULL,
    permission_used VARCHAR(64) NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status VARCHAR(20) NOT NULL,
    amount_minor BIGINT NULL,
    currency CHAR(3) NULL,
    reason VARCHAR(500) NULL,
    idempotency_key_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_finance_audit_request (review_request_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
