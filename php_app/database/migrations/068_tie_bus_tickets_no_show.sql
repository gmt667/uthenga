-- ============================================================
-- Migration: 068_tie_bus_tickets_no_show.sql
-- Lets a ticket reach a real terminal "no_show" state when boarding
-- closes with the passenger never having been scanned in — instead
-- of staying "issued" forever with no way to tell whether the trip
-- ever actually happened for that passenger.
-- ============================================================

ALTER TABLE tie_bus_tickets
  MODIFY COLUMN status ENUM('issued','boarded','cancelled','no_show') NOT NULL DEFAULT 'issued';
