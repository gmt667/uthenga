-- Quick Taxi Operations: driver notes on a passenger. A "passenger" itself has
-- no dedicated identity table yet — the Passengers workspace derives everyone
-- it shows by aggregating tie_trips for the current driver (grouped by phone,
-- or by name when no phone was recorded for a walk-in trip). Notes are the one
-- piece of state that must outlive any single trip, so they get their own
-- small, append-only table.
CREATE TABLE IF NOT EXISTS tie_trip_passenger_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_user_id VARCHAR(30) NOT NULL,
  passenger_key VARCHAR(191) NOT NULL,
  author_id VARCHAR(30) NOT NULL,
  body VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_trip_passenger_notes_lookup (driver_user_id, passenger_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
