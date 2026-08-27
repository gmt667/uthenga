-- ============================================================
-- Migration: 070_tie_bus_fleet_vehicle_details.sql
-- Extends tie_bus_fleet_vehicles with the real, vendor-editable
-- specification fields the Add Bus wizard actually collects
-- (fleet number, vehicle type, manufacturer/year/colour, capacity
-- breakdown, amenities, and operational settings) — previously
-- collected by the wizard UI but silently discarded on submit.
-- ============================================================

ALTER TABLE tie_bus_fleet_vehicles
  ADD COLUMN fleet_number VARCHAR(30) NULL AFTER reg_number,
  ADD COLUMN vehicle_type VARCHAR(40) NULL AFTER make_model,
  ADD COLUMN manufacturer VARCHAR(60) NULL AFTER vehicle_type,
  ADD COLUMN year SMALLINT UNSIGNED NULL AFTER manufacturer,
  ADD COLUMN color VARCHAR(30) NULL AFTER year,
  ADD COLUMN standing_capacity SMALLINT UNSIGNED NULL AFTER capacity,
  ADD COLUMN luggage_capacity VARCHAR(30) NULL AFTER standing_capacity,
  ADD COLUMN amenities JSON NULL AFTER luggage_capacity,
  ADD COLUMN gps_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER amenities,
  ADD COLUMN maintenance_threshold_km INT UNSIGNED NULL AFTER gps_enabled,
  ADD COLUMN boarding_buffer_minutes SMALLINT UNSIGNED NULL AFTER maintenance_threshold_km;
