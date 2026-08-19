-- Venue Management: link availability blocks to their originating assignment
-- so unassigning an event removes only its own SETUP/EVENT window even when
-- the same event occupies multiple spaces at a venue.

ALTER TABLE tie_venue_availability
  ADD COLUMN IF NOT EXISTS assignment_id VARCHAR(30) NULL AFTER event_id,
  ADD KEY IF NOT EXISTS idx_va_assignment (assignment_id);