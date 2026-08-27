-- Production notification delivery evidence and worker leasing.
-- Message bodies stay in the existing outbox. Attempt records deliberately
-- contain no recipient address, phone number, or provider response body.

ALTER TABLE tie_notification_outbox
  ADD COLUMN IF NOT EXISTS provider_name VARCHAR(80) NULL AFTER channel,
  ADD COLUMN IF NOT EXISTS next_attempt_at DATETIME NULL AFTER scheduled_at,
  ADD COLUMN IF NOT EXISTS lease_token CHAR(64) NULL AFTER next_attempt_at,
  ADD COLUMN IF NOT EXISTS lease_expires_at DATETIME NULL AFTER lease_token,
  ADD COLUMN IF NOT EXISTS delivered_at DATETIME NULL AFTER sent_at,
  ADD COLUMN IF NOT EXISTS terminal_at DATETIME NULL AFTER delivered_at,
  ADD COLUMN IF NOT EXISTS status_reason VARCHAR(120) NULL AFTER last_error_code;

UPDATE tie_notification_outbox
SET next_attempt_at = COALESCE(next_attempt_at, scheduled_at, created_at)
WHERE status IN ('PENDING', 'FAILED') AND next_attempt_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_tie_notification_claim
  ON tie_notification_outbox (status, next_attempt_at, lease_expires_at, created_at);

CREATE TABLE IF NOT EXISTS tie_notification_delivery_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id VARCHAR(32) NOT NULL,
  attempt_number INT UNSIGNED NOT NULL,
  channel VARCHAR(20) NOT NULL,
  provider_name VARCHAR(80) NOT NULL,
  outcome VARCHAR(24) NOT NULL,
  request_id VARCHAR(100) NOT NULL,
  provider_message_hash CHAR(64) NULL,
  http_status SMALLINT UNSIGNED NULL,
  error_code VARCHAR(80) NULL,
  latency_ms DECIMAL(12,2) NULL,
  started_at DATETIME(6) NOT NULL,
  finished_at DATETIME(6) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_notification_attempt (notification_id, attempt_number),
  KEY idx_tie_notification_attempt_outcome (outcome, created_at),
  KEY idx_tie_notification_attempt_request (request_id),
  CONSTRAINT fk_tie_notification_attempt_outbox
    FOREIGN KEY (notification_id) REFERENCES tie_notification_outbox(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_notification_delivery_receipts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id VARCHAR(32) NOT NULL,
  provider_name VARCHAR(80) NOT NULL,
  provider_message_hash CHAR(64) NOT NULL,
  event_key CHAR(64) NOT NULL,
  outcome VARCHAR(24) NOT NULL,
  request_id VARCHAR(100) NOT NULL,
  error_code VARCHAR(80) NULL,
  received_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_tie_notification_receipt_event (event_key),
  KEY idx_tie_notification_receipt_message (provider_name, provider_message_hash),
  CONSTRAINT fk_tie_notification_receipt_outbox
    FOREIGN KEY (notification_id) REFERENCES tie_notification_outbox(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
