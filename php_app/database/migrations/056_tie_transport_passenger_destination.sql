-- Quick Taxi: an optional per-passenger destination, so a departure with
-- passengers headed to different places can show a real (unordered — no
-- route-sequencing engine exists to honestly claim a "next stop" order)
-- summary of where its passengers are actually going, instead of treating
-- the whole vehicle as a single destination.
ALTER TABLE tie_transport_sessions
  ADD COLUMN destination VARCHAR(200) NULL AFTER passenger_count;
