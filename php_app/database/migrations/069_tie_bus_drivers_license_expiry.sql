-- ============================================================
-- Migration: 069_tie_bus_drivers_license_expiry.sql
-- Adds real license-expiry tracking for drivers, mirroring the
-- vehicle document-expiry pattern already in tie_bus_fleet_documents
-- — without this, driver compliance is an invisible gap on the
-- Maintenance/Drivers tabs even though vehicles already track it.
-- ============================================================

ALTER TABLE tie_bus_drivers
  ADD COLUMN license_expiry DATE NULL AFTER license_number;
