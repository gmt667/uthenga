-- Phase 6.13: verified vendor coordinates are the only precision-search source.
ALTER TABLE listings
  ADD COLUMN IF NOT EXISTS location_verification_status VARCHAR(24) NOT NULL DEFAULT 'unverified' AFTER location_verified_at,
  ADD COLUMN IF NOT EXISTS location_verified_by VARCHAR(30) NULL AFTER location_verification_status,
  ADD INDEX IF NOT EXISTS idx_listings_geo_verified (location_verification_status, gps_lat, gps_lng);

CREATE TABLE IF NOT EXISTS listing_location_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  listing_id VARCHAR(30) NOT NULL,
  actor_user_id VARCHAR(30) NULL,
  action VARCHAR(40) NOT NULL,
  acquisition_source VARCHAR(30) NULL,
  verification_status VARCHAR(24) NOT NULL,
  accuracy_m DECIMAL(10,2) NULL,
  captured_at DATETIME NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_location_audit_listing_created (listing_id, created_at),
  INDEX idx_location_audit_status (verification_status),
  CONSTRAINT fk_location_audit_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
