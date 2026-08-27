-- Accommodation v2 production invariants.
-- Preserve the compatibility blocked_rooms projection while separating manual
-- inventory controls from physical-unit maintenance blocks.
ALTER TABLE tie_accommodation_inventory_nights
  ADD COLUMN IF NOT EXISTS manual_blocked_rooms INT UNSIGNED NOT NULL DEFAULT 0 AFTER capacity_rooms,
  ADD COLUMN IF NOT EXISTS maintenance_blocked_rooms INT UNSIGNED NOT NULL DEFAULT 0 AFTER manual_blocked_rooms;

ALTER TABLE tie_accommodation_cancellation_policies
  ADD COLUMN IF NOT EXISTS version INT UNSIGNED NOT NULL DEFAULT 1 AFTER is_active;

ALTER TABLE tie_accommodation_assignments
  ADD COLUMN IF NOT EXISTS version INT UNSIGNED NOT NULL DEFAULT 1 AFTER assigned_by;

-- Existing blocked inventory was an aggregate. First preserve it as a manual
-- block, then attribute the known active unit blocks back to maintenance.
UPDATE tie_accommodation_inventory_nights
SET manual_blocked_rooms = blocked_rooms,
    maintenance_blocked_rooms = 0;

UPDATE tie_accommodation_inventory_nights n
INNER JOIN (
  SELECT n2.room_type_id, n2.stay_date, COUNT(b.id) AS maintenance_count
  FROM tie_accommodation_inventory_nights n2
  INNER JOIN tie_accommodation_unit_blocks b
    ON b.room_type_id = n2.room_type_id
   AND b.status = 'ACTIVE'
   AND n2.stay_date >= b.start_date
   AND n2.stay_date < b.end_date
  GROUP BY n2.room_type_id, n2.stay_date
) known ON known.room_type_id = n.room_type_id AND known.stay_date = n.stay_date
SET n.maintenance_blocked_rooms = known.maintenance_count,
    n.manual_blocked_rooms = GREATEST(n.blocked_rooms - known.maintenance_count, 0),
    n.blocked_rooms = GREATEST(n.blocked_rooms - known.maintenance_count, 0) + known.maintenance_count;

-- Reassert the compatibility projection for every row.
UPDATE tie_accommodation_inventory_nights
SET blocked_rooms = manual_blocked_rooms + maintenance_blocked_rooms;
