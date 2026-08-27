-- Phases 15/16: separate payment ledger. It never stores provider secrets or raw webhook bodies.
CREATE TABLE IF NOT EXISTS tie_payment_intents (
  id CHAR(36) NOT NULL PRIMARY KEY,
  plan_id VARCHAR(32) NOT NULL,
  user_id VARCHAR(30) NOT NULL,
  idempotency_key VARCHAR(128) NOT NULL,
  provider_name VARCHAR(50) NOT NULL,
  provider_tx_ref VARCHAR(128) NOT NULL,
  provider_reference_hash CHAR(64) NULL,
  state VARCHAR(32) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  currency CHAR(3) NOT NULL,
  quote_hash CHAR(64) NOT NULL,
  quote_snapshot JSON NOT NULL,
  inventory_hold_id VARCHAR(128) NULL,
  checkout_url VARCHAR(2048) NULL,
  verification JSON NULL,
  diagnostics JSON NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_payment_idempotency (user_id, idempotency_key),
  UNIQUE KEY uq_tie_payment_provider_reference (provider_tx_ref),
  KEY idx_tie_payment_plan (plan_id),
  KEY idx_tie_payment_state (state),
  KEY idx_tie_payment_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_payment_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_intent_id CHAR(36) NOT NULL,
  event_key CHAR(64) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  processing_status VARCHAR(30) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_payment_event (event_key),
  KEY idx_tie_payment_event_intent (payment_intent_id),
  CONSTRAINT fk_tie_payment_event_intent FOREIGN KEY (payment_intent_id) REFERENCES tie_payment_intents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
