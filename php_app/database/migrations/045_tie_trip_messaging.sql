-- Quick Taxi Operations: Messages workspace. Every trip lifecycle transition
-- already writes a tie_trip_events row (see 043_tie_trip_engine.sql) — that
-- row is reused directly as a "system notification"; this migration only
-- adds the read/unread marker a notification feed needs. A tie_trip_events
-- row belongs to exactly one driver's trip, so a single read_at column is
-- sufficient (there is no multi-viewer case to model here).
ALTER TABLE tie_trip_events
  ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER metadata;
