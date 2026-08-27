-- Phase 5: provenance for vendor-managed listing coordinates.
-- MariaDB 10.4-compatible; user/device locations are intentionally not stored.
ALTER TABLE listings
  ADD COLUMN IF NOT EXISTS location_source VARCHAR(30) NULL AFTER gps_lng,
  ADD COLUMN IF NOT EXISTS location_accuracy_m DECIMAL(10,2) NULL AFTER location_source,
  ADD COLUMN IF NOT EXISTS location_captured_at DATETIME NULL AFTER location_accuracy_m,
  ADD COLUMN IF NOT EXISTS location_verified_at DATETIME NULL AFTER location_captured_at,
  ADD INDEX IF NOT EXISTS idx_listings_geo (gps_lat, gps_lng);
