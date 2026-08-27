-- ============================================================
-- Migration: 072_tie_bus_fleet_maintenance_issue_cost.sql
-- Adds real cost tracking to maintenance/issue logs — previously
-- there was no way to record what a service or repair actually cost,
-- even though the Maintenance tab's UI implies a running cost history.
-- ============================================================

ALTER TABLE tie_bus_fleet_maintenance
  ADD COLUMN cost DECIMAL(10,2) NULL AFTER mileage_km;

ALTER TABLE tie_bus_fleet_issues
  ADD COLUMN cost DECIMAL(10,2) NULL AFTER severity;
