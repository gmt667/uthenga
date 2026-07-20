-- ============================================================
-- Migration: 006_ticket_types_seats_inventory.sql
-- Adds ticket types, seat classes, room types, and inventory
-- tracking columns for UTHENGA Phase 2 features.
-- Safe to run on an existing uthenga_db database.
-- ============================================================


-- -----------------------------------------------------------------
-- Extend listings table with venue & inventory columns
-- -----------------------------------------------------------------
ALTER TABLE listings
  ADD COLUMN IF NOT EXISTS venue_capacity      INT NULL AFTER meta,
  ADD COLUMN IF NOT EXISTS venue_address       VARCHAR(255) NULL AFTER venue_capacity,
  ADD COLUMN IF NOT EXISTS gps_lat             DECIMAL(10,8) NULL AFTER venue_address,
  ADD COLUMN IF NOT EXISTS gps_lng             DECIMAL(11,8) NULL AFTER gps_lat,
  ADD COLUMN IF NOT EXISTS start_time          TIME NULL AFTER gps_lng,
  ADD COLUMN IF NOT EXISTS end_time            TIME NULL AFTER start_time,
  ADD COLUMN IF NOT EXISTS registration_number VARCHAR(50) NULL AFTER end_time,
  ADD COLUMN IF NOT EXISTS driver_name         VARCHAR(120) NULL AFTER registration_number;

-- -----------------------------------------------------------------
-- Ticket types per event listing
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_types (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id         VARCHAR(30) NOT NULL,
  name               VARCHAR(80) NOT NULL,
  description        TEXT NULL,
  price              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_quantity     INT UNSIGNED NOT NULL DEFAULT 0,
  remaining_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  sale_start         DATETIME NULL,
  sale_end           DATETIME NULL,
  is_active          TINYINT(1) NOT NULL DEFAULT 1,
  sort_order         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ticket_types_listing (listing_id),
  KEY idx_ticket_types_active (is_active),
  CONSTRAINT fk_ticket_types_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Seat classes per transport listing
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seat_classes (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id      VARCHAR(30) NOT NULL,
  class_name      VARCHAR(80) NOT NULL,
  description     TEXT NULL,
  price           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_seats     INT UNSIGNED NOT NULL DEFAULT 0,
  remaining_seats INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_seat_classes_listing (listing_id),
  CONSTRAINT fk_seat_classes_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Room types per accommodation listing
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS room_types (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id      VARCHAR(30) NOT NULL,
  room_name       VARCHAR(120) NOT NULL,
  description     TEXT NULL,
  price_per_night DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_rooms     INT UNSIGNED NOT NULL DEFAULT 0,
  available_rooms INT UNSIGNED NOT NULL DEFAULT 0,
  max_occupancy   TINYINT UNSIGNED NOT NULL DEFAULT 2,
  amenities       JSON NULL,
  room_images     JSON NULL,
  sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_room_types_listing (listing_id),
  CONSTRAINT fk_room_types_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Extend bookings table with item-level references
-- -----------------------------------------------------------------
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS ticket_type_id BIGINT UNSIGNED NULL AFTER listing_id,
  ADD COLUMN IF NOT EXISTS seat_class_id  BIGINT UNSIGNED NULL AFTER ticket_type_id,
  ADD COLUMN IF NOT EXISTS room_type_id   BIGINT UNSIGNED NULL AFTER seat_class_id,
  ADD COLUMN IF NOT EXISTS quantity       INT UNSIGNED NOT NULL DEFAULT 1 AFTER room_type_id;

-- -----------------------------------------------------------------
-- Advertisements table
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS advertisements (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(180) NOT NULL,
  ad_type    VARCHAR(40) NOT NULL DEFAULT 'banner',
  image_url  VARCHAR(500) NULL,
  link_url   VARCHAR(500) NULL,
  status     VARCHAR(20) NOT NULL DEFAULT 'active',
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ads_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Notifications table
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    VARCHAR(30) NULL,
  title      VARCHAR(255) NOT NULL,
  message    TEXT NOT NULL,
  type       VARCHAR(40) NOT NULL DEFAULT 'info',
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  link_url   VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user (user_id),
  KEY idx_notifications_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Announcements table
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS announcements (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(255) NOT NULL,
  content    TEXT NOT NULL,
  type       VARCHAR(40) NOT NULL DEFAULT 'info',
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_by VARCHAR(30) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_announcements_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Email / SMS logs
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_logs (
  id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(180) NOT NULL,
  subject  VARCHAR(255) NOT NULL,
  body     TEXT NULL,
  status   VARCHAR(20) NOT NULL DEFAULT 'sent',
  error_msg TEXT NULL,
  sent_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_logs (
  id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_phone VARCHAR(30) NOT NULL,
  message  TEXT NOT NULL,
  status   VARCHAR(20) NOT NULL DEFAULT 'sent',
  error_msg TEXT NULL,
  sent_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Seed sample ticket types for existing event listings
-- -----------------------------------------------------------------
INSERT IGNORE INTO ticket_types (listing_id, name, description, price, total_quantity, remaining_quantity, is_active, sort_order)
SELECT
  id,
  'VIP',
  'Premium VIP access with exclusive benefits',
  COALESCE(NULLIF(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.vipTicketPrice')) AS DECIMAL(15,2)), 0), 0),
  COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.vipTicketQuantity')) AS UNSIGNED), 500),
  COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.vipTicketQuantity')) AS UNSIGNED), 500),
  1, 1
FROM listings
WHERE listing_type = 'event'
  AND JSON_EXTRACT(meta, '$.vipTicketPrice') IS NOT NULL
  AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.vipTicketPrice')) != 'null';

INSERT IGNORE INTO ticket_types (listing_id, name, description, price, total_quantity, remaining_quantity, is_active, sort_order)
SELECT
  id,
  'Standard',
  'Standard event entry ticket',
  COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.standardTicketPrice')) AS DECIMAL(15,2)), 0),
  COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.totalTickets')) AS UNSIGNED), 5000),
  COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.totalTickets')) AS UNSIGNED), 5000),
  1, 5
FROM listings
WHERE listing_type = 'event'
  AND JSON_EXTRACT(meta, '$.standardTicketPrice') IS NOT NULL
  AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.standardTicketPrice')) != 'null';

-- -----------------------------------------------------------------
-- Seed sample seat classes for transport listings
-- -----------------------------------------------------------------
INSERT IGNORE INTO seat_classes (listing_id, class_name, description, price, total_seats, remaining_seats, sort_order)
SELECT
  id,
  'Standard',
  'Standard coach seating',
  COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.pricePerSeat')) AS DECIMAL(15,2)), 0),
  COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.totalSeats')) AS UNSIGNED), 65),
  COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.availableSeats')) AS UNSIGNED), 65),
  1
FROM listings
WHERE listing_type = 'transport'
  AND JSON_EXTRACT(meta, '$.pricePerSeat') IS NOT NULL
  AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.pricePerSeat')) != 'null';

-- Done
-- SELECT 'Migration 006 complete' AS status;
