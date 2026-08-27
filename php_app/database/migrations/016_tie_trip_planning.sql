-- Phase 9: metadata for TIE drafts; plans stay separate from bookings/payments.
-- Compatibility creation is needed for deployments where migration 008 was not
-- previously applied. It mirrors the existing itinerary table's public fields.
CREATE TABLE IF NOT EXISTS trip_itineraries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  itinerary_code VARCHAR(32) NOT NULL,
  user_id VARCHAR(30) NULL,
  title VARCHAR(220) NOT NULL,
  destination VARCHAR(200) NOT NULL,
  duration_days INT UNSIGNED NOT NULL DEFAULT 1,
  travel_date DATE NULL,
  budget_mwk DECIMAL(15,2) NULL,
  group_size INT UNSIGNED NOT NULL DEFAULT 1,
  itinerary_data JSON NOT NULL,
  ai_generated TINYINT(1) NOT NULL DEFAULT 0,
  pdf_url VARCHAR(500) NULL,
  share_token VARCHAR(64) NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_trip_itineraries_code (itinerary_code),
  KEY idx_trip_itineraries_user (user_id),
  KEY idx_trip_itineraries_share (share_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE trip_itineraries
  ADD COLUMN IF NOT EXISTS tie_lifecycle VARCHAR(30) NOT NULL DEFAULT 'DRAFT' AFTER ai_generated,
  ADD COLUMN IF NOT EXISTS tie_plan_version VARCHAR(40) NULL AFTER tie_lifecycle,
  ADD COLUMN IF NOT EXISTS tie_preferences JSON NULL AFTER tie_plan_version,
  ADD COLUMN IF NOT EXISTS tie_diagnostics JSON NULL AFTER tie_preferences,
  ADD COLUMN IF NOT EXISTS tie_provenance JSON NULL AFTER tie_diagnostics;

CREATE INDEX IF NOT EXISTS idx_trip_itineraries_tie_lifecycle ON trip_itineraries (user_id, tie_lifecycle, updated_at);
