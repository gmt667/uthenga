-- ============================================================
-- Migration: 067_tie_bus_tickets_vehicle_model_snapshot.sql
-- Companion to vehicle_reg_number (064) — snapshots the vehicle's
-- make/model at issuance too, so ticket templates can show
-- "Toyota Coaster · BUS-0021" without a fragile live lookup back
-- to the fleet table. Same immutability rationale: never
-- retroactively changes if the departure is later reassigned.
-- ============================================================

ALTER TABLE tie_bus_tickets
  ADD COLUMN vehicle_make_model VARCHAR(120) NULL DEFAULT NULL COMMENT 'Snapshot of the assigned vehicle''s make/model at issuance' AFTER vehicle_reg_number;
