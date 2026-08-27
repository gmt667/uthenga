-- ============================================================
-- Migration: 065_tie_bus_departures_listing.sql
-- Lets a specific trip (departure) override how it appears to
-- customers — image, title, a short description, highlights,
-- and a controlled card style — instead of always inheriting
-- the parent route's presentation. All fields are optional;
-- NULL/empty always falls back to the route's own values, so
-- existing departures render exactly as before.
-- ============================================================

ALTER TABLE tie_bus_departures
  ADD COLUMN listing_title VARCHAR(200) NULL DEFAULT NULL COMMENT 'Overrides the route title on this trip''s customer card; NULL inherits the route' AFTER notes,
  ADD COLUMN image VARCHAR(50) NULL DEFAULT NULL COMMENT 'Overrides the route image on this trip''s customer card; NULL inherits the route' AFTER listing_title,
  ADD COLUMN customer_description VARCHAR(500) NULL DEFAULT NULL COMMENT 'Short customer-facing blurb shown on the card/detail view' AFTER image,
  ADD COLUMN highlights VARCHAR(300) NULL DEFAULT NULL COMMENT 'Comma-separated short tags, e.g. Air conditioning, Reserved seating' AFTER customer_description,
  ADD COLUMN card_style ENUM('standard','premium','compact') NOT NULL DEFAULT 'standard' AFTER highlights;
