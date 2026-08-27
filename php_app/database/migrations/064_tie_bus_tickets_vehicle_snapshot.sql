-- ============================================================
-- Migration: 064_tie_bus_tickets_vehicle_snapshot.sql
-- Snapshot which specific vehicle a ticket was issued for, so a
-- later reassignment of the departure's bus never retroactively
-- changes which bus an already-sold ticket says it rode on —
-- same pattern already used for tie_bus_departure_seats.class_name/price.
-- ============================================================

ALTER TABLE tie_bus_tickets
  ADD COLUMN vehicle_reg_number VARCHAR(20) NULL DEFAULT NULL COMMENT 'Snapshot of the assigned vehicle at issuance — never retroactively changes if the departure is later reassigned' AFTER seat_label;
