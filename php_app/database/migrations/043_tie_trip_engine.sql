-- Quick Taxi Operations: a dedicated taxi trip lifecycle, distinct from the
-- bus/coach `tie_transport_runs`/`tie_transport_sessions` coordination model.
-- A trip is the single canonical record Dashboard, Trips, and (later)
-- Passengers/Messages/Earnings/Reports all read from.

CREATE TABLE IF NOT EXISTS tie_trips (
  id CHAR(36) NOT NULL PRIMARY KEY,
  trip_code VARCHAR(20) NOT NULL,
  driver_user_id VARCHAR(30) NOT NULL,
  passenger_name VARCHAR(120) NOT NULL,
  passenger_phone VARCHAR(30) NULL,
  passenger_user_id VARCHAR(30) NULL,
  pickup_location VARCHAR(200) NOT NULL,
  destination_location VARCHAR(200) NOT NULL,
  vehicle_label VARCHAR(120) NULL,
  vehicle_plate VARCHAR(30) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'REQUESTED',
  is_scheduled TINYINT(1) NOT NULL DEFAULT 0,
  scheduled_at DATETIME NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  assigned_at DATETIME NULL,
  accepted_at DATETIME NULL,
  en_route_at DATETIME NULL,
  arrived_at DATETIME NULL,
  onboard_at DATETIME NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  cancellation_actor VARCHAR(20) NULL,
  cancellation_reason VARCHAR(300) NULL,
  estimated_fare DECIMAL(12,2) NULL,
  final_fare DECIMAL(12,2) NULL,
  distance_km DECIMAL(6,2) NULL,
  duration_seconds INT UNSIGNED NULL,
  payment_method ENUM('digital','cash') NULL,
  payment_status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  version INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_trips_code (trip_code),
  KEY idx_tie_trips_driver_status (driver_user_id, status, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Append-only audit trail. Every lifecycle transition writes one row here —
-- this is what the Trip Timeline drawer renders from, and what makes the
-- state machine auditable instead of a bare `status` column.
CREATE TABLE IF NOT EXISTS tie_trip_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id CHAR(36) NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  actor_type VARCHAR(20) NOT NULL,
  actor_id VARCHAR(30) NULL,
  previous_status VARCHAR(20) NULL,
  new_status VARCHAR(20) NULL,
  reason VARCHAR(300) NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tie_trip_events_trip FOREIGN KEY (trip_id) REFERENCES tie_trips(id) ON DELETE CASCADE,
  KEY idx_tie_trip_events_trip (trip_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Driver duty status (Go Online / Go Offline). Kept as its own tiny table
-- rather than a `driver_profiles` column: any authenticated user can reach
-- the driver console, and most will not yet have a `driver_profiles` row
-- (those require license/verification fields this module has no business
-- writing to).
CREATE TABLE IF NOT EXISTS tie_trip_driver_status (
  driver_user_id VARCHAR(30) NOT NULL PRIMARY KEY,
  is_online TINYINT(1) NOT NULL DEFAULT 0,
  online_since DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
