-- Phase 11: transport-first, real-time coordination. These records are
-- deliberately separate from marketplace bookings: a coordination request is
-- only a temporary, vendor-controlled seat request.

CREATE TABLE IF NOT EXISTS tie_transport_runs (
  id CHAR(36) NOT NULL PRIMARY KEY,
  service_id VARCHAR(30) NOT NULL,
  vendor_id VARCHAR(30) NOT NULL,
  driver_user_id VARCHAR(30) NULL,
  seat_class_id BIGINT UNSIGNED NULL,
  service_date DATE NOT NULL,
  planned_departure_at DATETIME NOT NULL,
  actual_departure_at DATETIME NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'SCHEDULED',
  loading_status VARCHAR(24) NOT NULL DEFAULT 'NOT_OPEN',
  capacity INT UNSIGNED NOT NULL,
  remaining_seats INT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_tie_transport_run_capacity CHECK (remaining_seats <= capacity),
  KEY idx_tie_transport_run_vendor (vendor_id, service_date, status),
  KEY idx_tie_transport_run_service (service_id, service_date, planned_departure_at),
  KEY idx_tie_transport_run_driver (driver_user_id, service_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_transport_sessions (
  id CHAR(36) NOT NULL PRIMARY KEY,
  run_id CHAR(36) NOT NULL,
  service_id VARCHAR(30) NOT NULL,
  customer_id VARCHAR(30) NOT NULL,
  vendor_id VARCHAR(30) NOT NULL,
  passenger_count INT UNSIGNED NOT NULL,
  status VARCHAR(32) NOT NULL,
  reservation_state VARCHAR(24) NOT NULL DEFAULT 'NONE',
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  arrived_at DATETIME NULL,
  boarded_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  cancellation_actor VARCHAR(20) NULL,
  cancellation_reason VARCHAR(160) NULL,
  journey_booking_id VARCHAR(30) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_transport_session_customer (customer_id, status, created_at),
  KEY idx_tie_transport_session_vendor (vendor_id, status, expires_at),
  KEY idx_tie_transport_session_run (run_id, status),
  KEY idx_tie_transport_session_expiry (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_transport_session_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id CHAR(36) NOT NULL,
  event_type VARCHAR(48) NOT NULL,
  actor_type VARCHAR(20) NOT NULL,
  actor_id VARCHAR(30) NULL,
  payload JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_transport_event_session (session_id, id),
  KEY idx_tie_transport_event_type (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Location is session-scoped and must be deleted/expired after coordination.
CREATE TABLE IF NOT EXISTS tie_transport_location_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id CHAR(36) NOT NULL,
  actor_type VARCHAR(20) NOT NULL,
  actor_id VARCHAR(30) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  accuracy_m DECIMAL(10,2) NULL,
  source VARCHAR(32) NOT NULL,
  captured_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_transport_location_current (session_id, actor_type, captured_at),
  KEY idx_tie_transport_location_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_transport_messages (
  id CHAR(36) NOT NULL PRIMARY KEY,
  session_id CHAR(36) NOT NULL,
  sender_id VARCHAR(30) NOT NULL,
  sender_role VARCHAR(20) NOT NULL,
  body VARCHAR(1000) NOT NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_transport_message_session (session_id, created_at),
  KEY idx_tie_transport_message_recipient (session_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- A direct call is consent-gated. The application never returns a phone number
-- until the recipient explicitly accepts the contact request.
CREATE TABLE IF NOT EXISTS tie_transport_call_requests (
  id CHAR(36) NOT NULL PRIMARY KEY,
  session_id CHAR(36) NOT NULL,
  requester_id VARCHAR(30) NOT NULL,
  recipient_id VARCHAR(30) NOT NULL,
  status VARCHAR(24) NOT NULL,
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_transport_call_recipient (recipient_id, status, expires_at),
  KEY idx_tie_transport_call_session (session_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
