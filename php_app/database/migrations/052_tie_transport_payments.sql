-- Quick Taxi: per-passenger payment ledger for a departure (tie_transport_runs
-- / tie_transport_sessions). Independent of the plan-based tie_payment_intents
-- system (that machinery is tightly coupled to trip-plan quotes/holds, which
-- Coordination sessions never create) — this is a fresh, purpose-built ledger
-- that reuses only the PayChangu gateway class and its webhook-signature
-- pattern from php_app/includes/tie/Payment.php.
--
-- Multiple rows per session are expected (a failed electronic attempt
-- followed by a retry, or a switch to cash) — the "current" payment for a
-- session is always the latest row by created_at, mirroring the same
-- most-recent-row pattern already proven for call requests.

CREATE TABLE IF NOT EXISTS tie_transport_payments (
  id CHAR(36) NOT NULL PRIMARY KEY,
  session_id CHAR(36) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  currency CHAR(3) NOT NULL,
  method VARCHAR(20) NOT NULL,
  state VARCHAR(24) NOT NULL,
  provider_name VARCHAR(50) NULL,
  provider_reference VARCHAR(128) NULL,
  checkout_url VARCHAR(2048) NULL,
  verification JSON NULL,
  confirmed_by VARCHAR(30) NULL,
  confirmed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_transport_payments_session (session_id, created_at),
  KEY idx_tie_transport_payments_reference (provider_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Idempotent webhook replay guard, mirroring tie_payment_events' event_key
-- UNIQUE-constraint pattern.
CREATE TABLE IF NOT EXISTS tie_transport_payment_events (
  id CHAR(36) NOT NULL PRIMARY KEY,
  payment_id CHAR(36) NOT NULL,
  event_key VARCHAR(160) NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  payload JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_transport_payment_events_key (event_key),
  KEY idx_tie_transport_payment_events_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
