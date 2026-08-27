-- Phase 19 durable outbox. Delivery providers may only read pending records.
CREATE TABLE IF NOT EXISTS tie_notification_outbox (
  id VARCHAR(32) NOT NULL PRIMARY KEY,
  user_id VARCHAR(30) NOT NULL,
  channel VARCHAR(20) NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  status VARCHAR(20) NOT NULL,
  idempotency_key CHAR(64) NOT NULL,
  provider_message_id VARCHAR(160) NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_error_code VARCHAR(80) NULL,
  scheduled_at DATETIME NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_notification_idempotency (idempotency_key),
  KEY idx_tie_notification_pending (status, scheduled_at),
  KEY idx_tie_notification_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
