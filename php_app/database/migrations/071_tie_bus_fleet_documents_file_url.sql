-- ============================================================
-- Migration: 071_tie_bus_fleet_documents_file_url.sql
-- The Add Bus wizard's Documents step already uploads real files
-- via api/tie/vendor/transport/upload.php, but tie_bus_fleet_documents
-- had nowhere to store the resulting URL — this column closes that gap.
-- ============================================================

ALTER TABLE tie_bus_fleet_documents
  ADD COLUMN file_url VARCHAR(500) NULL AFTER expiry_date;
