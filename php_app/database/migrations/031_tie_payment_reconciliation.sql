-- Provider-independent payment recovery and refund audit evidence.
-- Raw webhook bodies, API secrets and card details are deliberately excluded.

ALTER TABLE tie_payment_events
  ADD COLUMN IF NOT EXISTS processed_at DATETIME NULL AFTER processing_status;

CREATE TABLE IF NOT EXISTS tie_payment_reconciliation_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_intent_id CHAR(36) NOT NULL,
  source_name VARCHAR(30) NOT NULL,
  result_status VARCHAR(30) NOT NULL,
  error_code VARCHAR(80) NULL,
  duration_ms DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_payment_reconciliation_intent (payment_intent_id, created_at),
  KEY idx_tie_payment_reconciliation_status (result_status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE tie_accommodation_refund_requests
  MODIFY COLUMN status ENUM(
    'PENDING','APPROVED','REJECTED','EXECUTING','AWAITING_PROVIDER',
    'EXECUTED','FAILED','MANUAL_REVIEW'
  ) NOT NULL DEFAULT 'PENDING',
  ADD COLUMN IF NOT EXISTS provider_name VARCHAR(50) NULL AFTER status,
  ADD COLUMN IF NOT EXISTS provider_charge_id VARCHAR(160) NULL AFTER provider_name,
  ADD COLUMN IF NOT EXISTS provider_refund_reference VARCHAR(160) NULL AFTER provider_charge_id,
  ADD COLUMN IF NOT EXISTS execution_idempotency_key VARCHAR(100) NULL AFTER provider_refund_reference,
  ADD COLUMN IF NOT EXISTS provider_response_hash CHAR(64) NULL AFTER execution_idempotency_key,
  ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER review_note,
  ADD COLUMN IF NOT EXISTS executed_at DATETIME NULL AFTER approved_at,
  ADD COLUMN IF NOT EXISTS version INT UNSIGNED NOT NULL DEFAULT 1 AFTER executed_at,
  ADD UNIQUE KEY IF NOT EXISTS uq_tie_accommodation_refund_execution (execution_idempotency_key);

CREATE TABLE IF NOT EXISTS tie_accommodation_refund_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  refund_request_id CHAR(36) NOT NULL,
  actor_id VARCHAR(30) NOT NULL,
  action_key VARCHAR(60) NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  provider_name VARCHAR(50) NULL,
  provider_response_hash CHAR(64) NULL,
  note VARCHAR(1000) NULL,
  correlation_id VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_refund_event (refund_request_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
