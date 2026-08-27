-- Quick Taxi: walk-in passengers. A walk-in has no Uthenga customer account,
-- so customer_id must become nullable; walk_in_name carries their name
-- instead, and booking_source distinguishes the two paths for analytics and
-- reconciliation, per the driver-added-passenger workflow.
ALTER TABLE tie_transport_sessions
  MODIFY COLUMN customer_id VARCHAR(30) NULL,
  ADD COLUMN walk_in_name VARCHAR(120) NULL AFTER customer_id,
  ADD COLUMN booking_source VARCHAR(20) NOT NULL DEFAULT 'uthenga' AFTER walk_in_name;
