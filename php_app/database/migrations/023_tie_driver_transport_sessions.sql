-- Driver-operated live transport sessions. A session belongs to one driver/vendor
-- and is separate from a marketplace listing or booking.
ALTER TABLE tie_transport_runs
  ADD COLUMN loading_location VARCHAR(200) NULL AFTER loading_status,
  ADD COLUMN driver_note VARCHAR(500) NULL AFTER loading_location,
  ADD COLUMN loading_started_at DATETIME NULL AFTER actual_departure_at,
  ADD COLUMN travelling_started_at DATETIME NULL AFTER loading_started_at,
  ADD COLUMN completed_at DATETIME NULL AFTER travelling_started_at,
  ADD COLUMN expired_at DATETIME NULL AFTER completed_at;

CREATE INDEX idx_tie_transport_run_active_vendor
  ON tie_transport_runs (vendor_id, status, planned_departure_at);
