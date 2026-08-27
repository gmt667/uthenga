-- Quick Taxi Operations: Schedule. "Start Shift" / "End Shift" is the same
-- online/offline toggle the Dashboard already exposes (tie_trip_driver_status)
-- — this migration only adds a session log around that existing toggle, so a
-- driver's actual worked sessions (not a vendor-assigned roster, which does
-- not exist in this codebase) become visible history. Availability is a
-- real, driver-set recurring weekly template — not a fabricated demand
-- forecast or vendor-approved shift assignment.

CREATE TABLE IF NOT EXISTS tie_driver_shift_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_user_id VARCHAR(30) NOT NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  trips_count INT UNSIGNED NULL,
  earnings DECIMAL(12,2) NULL,
  KEY idx_tie_driver_shift_sessions_driver (driver_user_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- day_of_week is ISO-8601 (1=Monday .. 7=Sunday), matching DateTimeImmutable's
-- format('N') used everywhere else in this codebase for weekday arithmetic.
CREATE TABLE IF NOT EXISTS tie_driver_availability (
  driver_user_id VARCHAR(30) NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,
  is_off TINYINT(1) NOT NULL DEFAULT 0,
  start_time TIME NULL,
  end_time TIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (driver_user_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
