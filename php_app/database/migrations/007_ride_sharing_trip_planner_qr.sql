-- ============================================================
-- Migration: 007_ride_sharing_trip_planner_qr.sql
-- Adds Mbanda ride sharing, AI Trip Planner, QR ticketing
-- Safe to run multiple times (uses IF NOT EXISTS / IF NOT EXISTS)
-- ============================================================

-- Extend bookings with QR code and usage tracking
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS qr_code_data   TEXT NULL,
  ADD COLUMN IF NOT EXISTS ticket_code    VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS tickets_used   INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS ticket_status  ENUM('pending','active','partially_used','fully_used','cancelled','refunded') NOT NULL DEFAULT 'pending';

-- -----------------------------------------------------------------
-- Mbanda Ride Sharing Trips
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ride_sharing_trips (
  id                VARCHAR(30)    NOT NULL PRIMARY KEY,
  driver_id         VARCHAR(30)    NOT NULL,
  driver_name       VARCHAR(150)   NOT NULL,
  driver_phone      VARCHAR(30)    NULL,
  pickup_location   VARCHAR(255)   NOT NULL,
  destination       VARCHAR(255)   NOT NULL,
  departure_datetime DATETIME      NOT NULL,
  available_seats   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  booked_seats      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  price_per_seat    DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  vehicle_make      VARCHAR(80)    NULL,
  vehicle_model     VARCHAR(80)    NULL,
  vehicle_color     VARCHAR(50)    NULL,
  vehicle_reg       VARCHAR(30)    NULL,
  description       TEXT           NULL,
  status            ENUM('open','full','cancelled','completed') NOT NULL DEFAULT 'open',
  created_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_rstrip_driver   (driver_id),
  KEY idx_rstrip_depart   (departure_datetime),
  KEY idx_rstrip_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Mbanda Ride Sharing Bookings
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ride_sharing_bookings (
  id              VARCHAR(30)    NOT NULL PRIMARY KEY,
  trip_id         VARCHAR(30)    NOT NULL,
  passenger_id    VARCHAR(30)    NOT NULL,
  passenger_name  VARCHAR(150)   NOT NULL,
  passenger_phone VARCHAR(30)    NULL,
  seats_booked    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  total_price     DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  status          ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  booking_code    VARCHAR(20)    NOT NULL,
  notes           TEXT           NULL,
  created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rsb_code (booking_code),
  KEY idx_rsb_trip      (trip_id),
  KEY idx_rsb_passenger (passenger_id),
  KEY idx_rsb_status    (status),
  CONSTRAINT fk_rsb_trip FOREIGN KEY (trip_id) REFERENCES ride_sharing_trips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- AI Trip Planner Sessions
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trip_planner_sessions (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     VARCHAR(30)    NULL,
  session_key VARCHAR(64)    NOT NULL,
  query_text  TEXT           NOT NULL,
  plan_json   LONGTEXT       NOT NULL,
  days        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  budget_mk   DECIMAL(15,2)  NULL,
  destination VARCHAR(200)   NULL,
  created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tps_user    (user_id),
  KEY idx_tps_session (session_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Gate scans: track per-booking ticket usage
-- -----------------------------------------------------------------
ALTER TABLE gate_scans
  ADD COLUMN IF NOT EXISTS tickets_in_booking INT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS tickets_used_after  INT UNSIGNED NULL DEFAULT NULL;
