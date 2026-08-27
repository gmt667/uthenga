-- Phase 10: orchestration records only. Canonical bookings remain in bookings.
CREATE TABLE IF NOT EXISTS tie_booking_executions (
  id VARCHAR(36) NOT NULL PRIMARY KEY,
  plan_id VARCHAR(32) NOT NULL,
  user_id VARCHAR(30) NOT NULL,
  idempotency_key VARCHAR(128) NOT NULL,
  state VARCHAR(30) NOT NULL,
  rollback_policy VARCHAR(30) NOT NULL,
  payment_reference_hash CHAR(64) NULL,
  journey_state JSON NULL,
  diagnostics JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_booking_idempotency (user_id, idempotency_key),
  KEY idx_tie_booking_plan (plan_id),
  KEY idx_tie_booking_state (state),
  KEY idx_tie_booking_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_booking_operations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  execution_id VARCHAR(36) NOT NULL,
  activity_id VARCHAR(64) NOT NULL,
  service_id VARCHAR(64) NOT NULL,
  booking_id VARCHAR(64) NULL,
  operation_state VARCHAR(30) NOT NULL,
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  provider_name VARCHAR(50) NOT NULL,
  diagnostics JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_booking_operation (execution_id, activity_id),
  KEY idx_tie_booking_operation_execution (execution_id),
  CONSTRAINT fk_tie_booking_operation_execution FOREIGN KEY (execution_id) REFERENCES tie_booking_executions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
