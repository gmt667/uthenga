-- ============================================================
-- Uthenga Marketplace — Full Database Schema + Seed Data
-- Compatible with MySQL 8.0+ / MariaDB 10.5+
-- Run this in phpMyAdmin or: mysql -u root -p < setup.sql
-- ============================================================


-- ─── Users ──────────────────────────────────────────────────────────────────
CREATE TABLE users (
  id              VARCHAR(30)   NOT NULL PRIMARY KEY,
  name            VARCHAR(120)  NOT NULL,
  full_name       VARCHAR(150)  GENERATED ALWAYS AS (name) STORED,
  email           VARCHAR(180)  NOT NULL UNIQUE,
  phone           VARCHAR(30)   NULL,
  password_hash   VARCHAR(255)  NOT NULL,
  role            ENUM(
    'Super Administrator','Administrator','Vendor',
    'Event Organizer','Hotel/Lodge Manager',
    'Tour Operator','Transport Provider','Customer'
  ) NOT NULL DEFAULT 'Customer',
  avatar          VARCHAR(500)  NULL,
  is_approved     TINYINT(1)    NOT NULL DEFAULT 1,
  account_status  VARCHAR(20)   NOT NULL DEFAULT 'active',
  balance         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  must_change_pw  TINYINT(1)    NOT NULL DEFAULT 0,
  must_change_password TINYINT(1) GENERATED ALWAYS AS (must_change_pw) STORED,
  notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
  joined_date     DATE          NOT NULL DEFAULT (CURDATE()),
  email_verified_at DATETIME     NULL,
  phone_verified_at DATETIME     NULL,
  last_login_at   DATETIME      NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_role (role),
  INDEX idx_email (email)
) ENGINE=InnoDB;

-- ─── Listings ────────────────────────────────────────────────────────────────
CREATE TABLE listings (
  id              VARCHAR(30)   NOT NULL PRIMARY KEY,
  listing_type    ENUM('event','accommodation','tour','transport') NOT NULL,
  title           VARCHAR(200)  NOT NULL,
  description     TEXT          NOT NULL,
  location        VARCHAR(200)  NOT NULL,
  image           VARCHAR(500)  NOT NULL,
  gallery         JSON          NULL,
  vendor_id       VARCHAR(30)   NOT NULL,
  vendor_name     VARCHAR(120)  NOT NULL,
  rating          DECIMAL(3,1)  NOT NULL DEFAULT 0.0,
  featured        TINYINT(1)    NOT NULL DEFAULT 0,
  is_active       TINYINT(1)    NOT NULL DEFAULT 1,
  meta            JSON          NOT NULL COMMENT 'Type-specific fields stored as JSON',
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_type (listing_type),
  INDEX idx_vendor (vendor_id),
  INDEX idx_featured (featured),
  FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── Reviews ─────────────────────────────────────────────────────────────────
CREATE TABLE reviews (
  id           VARCHAR(20)  NOT NULL PRIMARY KEY,
  listing_id   VARCHAR(30)  NOT NULL,
  user_name    VARCHAR(120) NOT NULL,
  rating       TINYINT      NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment      TEXT         NOT NULL,
  review_date  DATE         NOT NULL DEFAULT (CURDATE()),
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_listing (listing_id),
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── Bookings ────────────────────────────────────────────────────────────────
CREATE TABLE bookings (
  id              VARCHAR(20)   NOT NULL PRIMARY KEY,
  booking_code    VARCHAR(30)   GENERATED ALWAYS AS (id) STORED,
  listing_id      VARCHAR(30)   NOT NULL,
  listing_title   VARCHAR(200)  NOT NULL,
  listing_image   VARCHAR(500)  NOT NULL,
  listing_type    ENUM('event','accommodation','tour','transport') NOT NULL,
  customer_id     VARCHAR(30)   NOT NULL,
  customer_name   VARCHAR(120)  NOT NULL,
  customer_email  VARCHAR(180)  NOT NULL,
  booking_date    DATE          NOT NULL DEFAULT (CURDATE()),
  booked_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  details         JSON          NOT NULL COMMENT 'Booking-specific details (seats, dates, etc.)',
  currency        CHAR(3)       NOT NULL DEFAULT 'MWK',
  total_price     DECIMAL(15,2) NOT NULL,
  grand_total     DECIMAL(15,2) GENERATED ALWAYS AS (total_price) STORED,
  commission_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_amount      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  commission_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  payment_status  ENUM('Pending','Paid','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  booking_status  VARCHAR(30) NOT NULL DEFAULT 'pending',
  reference_name  VARCHAR(150) NULL,
  customer_notes  TEXT          NULL,
  vendor_notes    TEXT          NULL,
  transaction_id  VARCHAR(30)   NULL,
  qr_code         VARCHAR(100)  NULL,
  confirmed_at    DATETIME      NULL,
  cancelled_at    DATETIME      NULL,
  completed_at    DATETIME      NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME      NULL,
  INDEX idx_customer (customer_id),
  INDEX idx_listing (listing_id),
  INDEX idx_status (booking_status),
  INDEX idx_payment (payment_status),
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── Transactions ─────────────────────────────────────────────────────────────
CREATE TABLE transactions (
  id              VARCHAR(30)   NOT NULL PRIMARY KEY,
  transaction_reference VARCHAR(60) GENERATED ALWAYS AS (id) STORED,
  booking_id      VARCHAR(20)   NOT NULL,
  customer_id     VARCHAR(30)   NOT NULL,
  user_id         VARCHAR(30)   GENERATED ALWAYS AS (customer_id) STORED,
  customer_name   VARCHAR(120)  NOT NULL,
  amount          DECIMAL(15,2) NOT NULL,
  gateway         ENUM('Uthenga Pay','Airtel Money','TNM Mpamba','Bank Card','Direct NBS Transfer') NOT NULL,
  gateway_name    VARCHAR(100) GENERATED ALWAYS AS (gateway) STORED,
  transaction_type VARCHAR(40)  NULL,
  status          ENUM('Pending','Success','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  receipt_number  VARCHAR(30)   NOT NULL,
  vendor_id       VARCHAR(30)   NULL,
  metadata        JSON          NULL,
  transaction_date DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_booking (booking_id),
  INDEX idx_customer (customer_id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── Support Tickets ──────────────────────────────────────────────────────────
CREATE TABLE support_tickets (
  id           VARCHAR(20)  NOT NULL PRIMARY KEY,
  customer_id  VARCHAR(30)  NOT NULL,
  ticket_code  VARCHAR(32) GENERATED ALWAYS AS (id) STORED,
  requester_user_id VARCHAR(30) GENERATED ALWAYS AS (customer_id) STORED,
  customer_name VARCHAR(120) NOT NULL,
  requester_name VARCHAR(120) GENERATED ALWAYS AS (customer_name) STORED,
  customer_email VARCHAR(180) NULL,
  requester_email VARCHAR(180) GENERATED ALWAYS AS (customer_email) STORED,
  subject      VARCHAR(200) NOT NULL,
  message      TEXT         NOT NULL,
  priority     VARCHAR(20)  NOT NULL DEFAULT 'medium',
  status       VARCHAR(30)  NOT NULL DEFAULT 'Open',
  category     VARCHAR(80)  NOT NULL,
  assigned_admin_id VARCHAR(30) NULL,
  closed_at    DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME     NULL,
  INDEX idx_customer (customer_id),
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── Ticket Responses ─────────────────────────────────────────────────────────
CREATE TABLE ticket_responses (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ticket_id  VARCHAR(20)   NOT NULL,
  sender     VARCHAR(120)  NOT NULL,
  message    TEXT          NOT NULL,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── Blog Posts ───────────────────────────────────────────────────────────────
CREATE TABLE blog_posts (
  id         VARCHAR(20)  NOT NULL PRIMARY KEY,
  title      VARCHAR(200) NOT NULL,
  excerpt    TEXT         NOT NULL,
  content    LONGTEXT     NOT NULL,
  image      VARCHAR(500) NOT NULL,
  author     VARCHAR(120) NOT NULL,
  category   ENUM('Travel Guide','Local Events','Culture','Tips') NOT NULL,
  post_date  DATE         NOT NULL DEFAULT (CURDATE()),
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── Audit Logs ───────────────────────────────────────────────────────────────
CREATE TABLE audit_logs (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id    VARCHAR(30)   NULL,
  user_name  VARCHAR(120)  NOT NULL,
  user_role  VARCHAR(60)   NOT NULL,
  action     VARCHAR(100)  NOT NULL,
  details    TEXT          NOT NULL,
  entity_table VARCHAR(120) NULL,
  entity_id    VARCHAR(64)  NULL,
  before_data  JSON          NULL,
  after_data   JSON          NULL,
  ip_address   VARCHAR(45)   NULL,
  user_agent   VARCHAR(500)  NULL,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_action (action)
) ENGINE=InnoDB;

-- ─── Coupons ──────────────────────────────────────────────────────────────────
CREATE TABLE coupons (
  code           VARCHAR(30)   NOT NULL PRIMARY KEY,
  discount_type  ENUM('percentage','fixed') NOT NULL,
  value          DECIMAL(10,2) NOT NULL,
  min_spend      DECIMAL(10,2) NULL,
  expiry_date    DATE          NOT NULL,
  is_active      TINYINT(1)    NOT NULL DEFAULT 1,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------------------
-- Compatibility tables used by the PHP application
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_key    VARCHAR(80) NOT NULL,
  label       VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  is_system   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_roles_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  permission_key VARCHAR(120) NOT NULL,
  label          VARCHAR(180) NOT NULL,
  module         VARCHAR(120) NOT NULL,
  description    VARCHAR(255) NULL,
  is_system      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_permissions_key (permission_key),
  KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id       BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  assigned_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id     VARCHAR(30) NOT NULL,
  role_id     BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          VARCHAR(30) NULL,
  session_token    VARCHAR(128) NOT NULL,
  ip_address       VARCHAR(45) NULL,
  user_agent       VARCHAR(500) NULL,
  last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at       DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_sessions_token (session_token),
  KEY idx_user_sessions_user (user_id),
  CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          VARCHAR(30) NOT NULL,
  reset_token_hash VARCHAR(255) NOT NULL,
  expires_at       DATETIME NOT NULL,
  used_at          DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_password_resets_token (reset_token_hash),
  KEY idx_password_resets_user (user_id),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_accounts (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id            VARCHAR(30) NOT NULL,
  provider           ENUM('google','facebook','microsoft') NOT NULL,
  provider_user_id   VARCHAR(255) NOT NULL,
  provider_email     VARCHAR(180) NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_social_provider_user (provider, provider_user_id),
  KEY idx_social_user (user_id),
  CONSTRAINT fk_social_accounts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_permissions (
  user_id     VARCHAR(30) NOT NULL PRIMARY KEY,
  permissions JSON NOT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendors (
  id             VARCHAR(30) NOT NULL PRIMARY KEY,
  user_id        VARCHAR(30) NOT NULL,
  business_name  VARCHAR(180) NOT NULL,
  display_name   VARCHAR(180) NULL,
  description    TEXT NULL,
  business_email VARCHAR(180) NULL,
  business_phone VARCHAR(30) NULL,
  website_url    VARCHAR(500) NULL,
  payout_email   VARCHAR(180) NULL,
  status         VARCHAR(30) NOT NULL DEFAULT 'pending',
  approved_at    DATETIME NULL,
  rejected_at    DATETIME NULL,
  suspended_at   DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at     DATETIME NULL,
  UNIQUE KEY uq_vendors_user (user_id),
  KEY idx_vendors_status (status),
  CONSTRAINT fk_vendors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendor_profiles (
  vendor_id      VARCHAR(30) NOT NULL PRIMARY KEY,
  business_name  VARCHAR(180) NULL,
  phone          VARCHAR(30) NULL,
  address        VARCHAR(255) NULL,
  city           VARCHAR(100) NULL,
  category       VARCHAR(80) NULL,
  description    TEXT NULL,
  approval_status VARCHAR(30) NOT NULL DEFAULT 'pending',
  approved_at    DATETIME NULL,
  approved_by    VARCHAR(30) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS local_business_listings (
  id              VARCHAR(30)  NOT NULL PRIMARY KEY,
  vendor_id       VARCHAR(30)  NOT NULL,
  business_name   VARCHAR(180) NOT NULL,
  business_type   VARCHAR(40)  NOT NULL,
  description     TEXT         NULL,
  city            VARCHAR(100) NOT NULL,
  address         VARCHAR(255) NULL,
  phone           VARCHAR(30)  NULL,
  email           VARCHAR(180) NULL,
  website         VARCHAR(255) NULL,
  opening_hours   VARCHAR(120) NULL,
  price_range     VARCHAR(80)  NULL,
  cover_image     VARCHAR(500) NULL,
  lat             DECIMAL(10,7) NULL,
  lng             DECIMAL(10,7) NULL,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  is_featured     TINYINT(1)   NOT NULL DEFAULT 0,
  avg_rating      DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  review_count    INT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME     NULL,
  KEY idx_local_business_vendor (vendor_id),
  KEY idx_local_business_type (business_type),
  KEY idx_local_business_city (city),
  KEY idx_local_business_active (is_active),
  CONSTRAINT fk_local_business_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_items (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id   VARCHAR(20) NOT NULL,
  vendor_id    VARCHAR(30) NULL,
  item_type    VARCHAR(40) NOT NULL,
  reference_id VARCHAR(64) NOT NULL,
  item_name    VARCHAR(255) NOT NULL,
  quantity     INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price   DECIMAL(15,2) NOT NULL,
  subtotal     DECIMAL(15,2) NOT NULL,
  service_date DATE NULL,
  metadata     JSON NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_booking_items_booking (booking_id),
  KEY idx_booking_items_vendor (vendor_id),
  KEY idx_booking_items_type (item_type),
  KEY idx_booking_items_reference (reference_id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ride_sharing_trips (
  id                 VARCHAR(30)    NOT NULL PRIMARY KEY,
  driver_id          VARCHAR(30)    NOT NULL,
  driver_name        VARCHAR(150)   NOT NULL,
  driver_phone       VARCHAR(30)    NULL,
  pickup_location    VARCHAR(255)   NOT NULL,
  destination        VARCHAR(255)   NOT NULL,
  departure_datetime  DATETIME      NOT NULL,
  available_seats    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  booked_seats       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  price_per_seat     DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  vehicle_make       VARCHAR(80)    NULL,
  vehicle_model      VARCHAR(80)    NULL,
  vehicle_color      VARCHAR(50)    NULL,
  vehicle_reg        VARCHAR(30)    NULL,
  description        TEXT           NULL,
  status             ENUM('open','full','cancelled','completed') NOT NULL DEFAULT 'open',
  created_at         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_rstrip_driver   (driver_id),
  KEY idx_rstrip_depart   (departure_datetime),
  KEY idx_rstrip_status   (status),
  FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT fk_rsb_trip FOREIGN KEY (trip_id) REFERENCES ride_sharing_trips(id) ON DELETE CASCADE,
  CONSTRAINT fk_rsb_passenger FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    VARCHAR(30) NOT NULL,
  type       VARCHAR(50) NOT NULL,
  title      VARCHAR(200) NOT NULL,
  message    TEXT NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user (user_id, is_read),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id   BIGINT UNSIGNED NULL,
  channel           VARCHAR(20) NOT NULL DEFAULT 'in_app',
  recipient         VARCHAR(180) NOT NULL,
  status            VARCHAR(20) NOT NULL DEFAULT 'queued',
  provider_response JSON NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at           DATETIME NULL,
  KEY idx_notification_logs_notification (notification_id),
  KEY idx_notification_logs_channel (channel),
  FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_announcements (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(180) NOT NULL,
  message    TEXT NOT NULL,
  audience   VARCHAR(20) NOT NULL DEFAULT 'all',
  priority   VARCHAR(20) NOT NULL DEFAULT 'medium',
  starts_at  DATETIME NOT NULL,
  ends_at    DATETIME NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_by VARCHAR(30) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key`      VARCHAR(100) NOT NULL PRIMARY KEY,
  `value`    TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(30) NULL,
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (`key`, `value`) VALUES
('commission_rate', '10'),
('platform_name', 'Uthenga'),
('platform_email', 'support@uthenga.co'),
('allow_vendor_registration', '1')
ON DUPLICATE KEY UPDATE `key` = `key`;

CREATE TABLE IF NOT EXISTS system_health_logs (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  health_key    VARCHAR(120) NOT NULL,
  status        VARCHAR(20) NOT NULL DEFAULT 'unknown',
  value_payload JSON NULL,
  notes         TEXT NULL,
  recorded_by   VARCHAR(30) NULL,
  recorded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_system_health_key (health_key),
  KEY idx_system_health_status (status),
  FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gate_sessions (
  id              VARCHAR(30) NOT NULL PRIMARY KEY,
  listing_id      VARCHAR(30) NOT NULL,
  listing_title   VARCHAR(200) NOT NULL,
  started_by      VARCHAR(30) NOT NULL,
  started_name    VARCHAR(120) NOT NULL,
  status          VARCHAR(20) NOT NULL DEFAULT 'active',
  started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paused_at       DATETIME NULL,
  stopped_at      DATETIME NULL,
  total_scanned   INT UNSIGNED NOT NULL DEFAULT 0,
  total_valid     INT UNSIGNED NOT NULL DEFAULT 0,
  total_invalid   INT UNSIGNED NOT NULL DEFAULT 0,
  total_duplicate INT UNSIGNED NOT NULL DEFAULT 0,
  notes           TEXT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_gate_sessions_listing (listing_id),
  KEY idx_gate_sessions_status (status),
  KEY idx_gate_sessions_started_by (started_by),
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gate_scans (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id    VARCHAR(30) NOT NULL,
  qr_code       VARCHAR(200) NOT NULL,
  booking_id    VARCHAR(20) NULL,
  customer_name VARCHAR(120) NULL,
  ticket_type   VARCHAR(80) NULL,
  scan_result   VARCHAR(20) NOT NULL,
  scanned_by    VARCHAR(30) NULL,
  scanned_name  VARCHAR(120) NULL,
  scanned_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes         VARCHAR(500) NULL,
  KEY idx_gate_scans_session (session_id),
  KEY idx_gate_scans_qr (qr_code),
  KEY idx_gate_scans_booking (booking_id),
  KEY idx_gate_scans_result (scan_result),
  FOREIGN KEY (session_id) REFERENCES gate_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA — Ported from mockData.ts
-- Passwords are bcrypt hashed. Demo accounts are seeded with non-plaintext hashes.
-- ============================================================

-- ─── Users ───────────────────────────────────────────────────────────────────
-- Hash for the seeded super admin account (cost=12)
INSERT INTO users (id, name, email, password_hash, role, avatar, is_approved, balance, must_change_pw, joined_date) VALUES
('u-super-admin', 'Super Admin', 'admin@uthenga.com',
 '$2y$12$FwtOazj5jswV9L4CT8c..OByb4i3V3qeHkt2.u6dgNeJBCOUcxvoe',
 'Super Administrator',
 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&fit=crop&q=80',
 1, 1000000.00, 0, '2026-06-24'),

('u-1', 'Desire Mwalwanda', 'mwalwandadesire5@gmail.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Super Administrator',
 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&fit=crop&q=80',
 1, 850000.00, 0, '2026-01-10'),

('u-2', 'Chisomo Phiri', 'phiri@giantplus.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Administrator',
 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&fit=crop&q=80',
 1, 120000.00, 0, '2026-02-14'),

('v-1', 'Lake Malawi Festivals Ltd', 'events@lakemalawi.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Event Organizer',
 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=150&fit=crop&q=80',
 1, 4500000.00, 0, '2026-03-01'),

('v-2', 'Kaya Mawa Lodge Management', 'stay@kayamawa.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Hotel/Lodge Manager',
 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=150&fit=crop&q=80',
 1, 12400000.00, 0, '2026-03-10'),

('v-3', 'Malawi Wildlife Safaris', 'safari@wildmalawi.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Tour Operator',
 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&fit=crop&q=80',
 1, 8900000.00, 0, '2026-03-15'),

('v-4', 'AXA Coach Services', 'bookings@axacoach.mw',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Transport Provider',
 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=150&fit=crop&q=80',
 1, 6200000.00, 0, '2026-03-20'),

('c-1', 'Grace Banda', 'grace.banda@gmail.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Customer',
 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&fit=crop&q=80',
 1, 350000.00, 0, '2026-04-01'),

('c-2', 'Limbani Chimwaza', 'limbani@outlook.com',
 '$2y$12$zuC8o8M.cs/xu/6qGaoai.DjVb2h90fo7zHcSDCvE.xzuwwkamaNi',
 'Customer',
 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&fit=crop&q=80',
 1, 180000.00, 0, '2026-04-12');

-- NOTE: Demo credentials are pre-hashed for convenience.
-- The seeded accounts use known demo passwords; update them before production use.

-- ─── Event Listings ────────────────────────────────────────────────────────────
INSERT INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, meta) VALUES

('evt-1', 'event', 'Lake of Stars Festival 2026',
 'The legendary annual festival of music, arts, and culture on the spectacular shores of Lake Malawi. Headlined by internationally recognized African stars and homegrown Malawian legends, offering three days of unparalleled celebration, beaches, and arts workshops.',
 'Mangochi Beach Resort, Lake Malawi',
 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=600&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=600&fit=crop&q=80","https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=600&fit=crop&q=80"]',
 'v-1', 'Lake Malawi Festivals Ltd', 4.9, 1,
 '{"date":"2026-09-25","time":"12:00 PM - 11:30 PM","category":"Music Festivals","vipTicketPrice":120000,"standardTicketPrice":45000,"vipAvailable":150,"standardAvailable":680,"vipTotal":200,"standardTotal":800,"venueCapacity":1000}'),

('evt-2', 'event', 'Malawi Tech Innovation Summit',
 'The premier national digital gathering focused on emerging tech ecosystems, mobile commerce, AI in agriculture, and Malawian startup showcases. Sponsored by GIANTPLUS, bringing together top African founders, policy makers, and enterprise leaders.',
 'Bingu International Convention Centre (BICC), Lilongwe',
 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1515187029135-18ee286d815b?w=600&fit=crop&q=80"]',
 'v-1', 'Lake Malawi Festivals Ltd', 4.7, 1,
 '{"date":"2026-07-15","time":"08:30 AM - 05:00 PM","category":"Conferences","vipTicketPrice":65000,"standardTicketPrice":25000,"vipAvailable":80,"standardAvailable":350,"vipTotal":100,"standardTotal":400,"venueCapacity":500}'),

('evt-3', 'event', 'Zomba Plateau Hiking & Acoustic Session',
 'An intimate sunset acoustic concert nested atop the stunning Zomba Plateau. Experience pure tranquility, fresh pine mountain breeze, hiking trails, local acoustic guitarists, and premium Malawian coffee tastings.',
 'Zomba Plateau Forest Reserve',
 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=600&fit=crop&q=80"]',
 'v-1', 'Lake Malawi Festivals Ltd', 4.6, 0,
 '{"date":"2026-08-08","time":"02:00 PM - 08:00 PM","category":"Music Festivals","vipTicketPrice":35000,"standardTicketPrice":15000,"vipAvailable":30,"standardAvailable":120,"vipTotal":30,"standardTotal":120,"venueCapacity":150}'),

('evt-4', 'event', 'Blantyre Football Derby: FCB Nyasa Big Bullets vs Mighty Mukuru Wanderers',
 'The most fierce and historic football clash in Malawi! Experience the passionate atmosphere at the legendary Kamuzu Stadium in Blantyre as the two giants of Malawian football battle for local bragging rights and Super League points.',
 'Kamuzu Stadium, Blantyre',
 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=600&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1518063319789-7217e6706b04?w=600&fit=crop&q=80"]',
 'v-1', 'Lake Malawi Festivals Ltd', 4.8, 1,
 '{"date":"2026-07-19","time":"02:30 PM - 05:00 PM","category":"Football Games","vipTicketPrice":25000,"standardTicketPrice":8000,"vipAvailable":200,"standardAvailable":1500,"vipTotal":250,"standardTotal":1800,"venueCapacity":2050}'),

('evt-5', 'event', 'International Netball Friendly: Malawi Queens vs South Africa Proteas',
 'Join the crowd to cheer on the world-class Malawi Queens netball team in an exciting international test match against rival South Africa. A showcase of speed, agility, and pure athletic precision in a state-of-the-art arena.',
 'Griffin Saenda Indoor Sports Complex, Lilongwe',
 'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=600&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1546519638-68e109498ffc?w=600&fit=crop&q=80"]',
 'v-1', 'Lake Malawi Festivals Ltd', 4.9, 0,
 '{"date":"2026-08-15","time":"04:00 PM - 06:00 PM","category":"Sports Matches","vipTicketPrice":15000,"standardTicketPrice":5000,"vipAvailable":100,"standardAvailable":400,"vipTotal":120,"standardTotal":500,"venueCapacity":620}');

-- ─── Accommodation Listings ─────────────────────────────────────────────────
INSERT INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, meta) VALUES

('acc-1', 'accommodation', 'Kaya Mawa Private Island Lodge',
 'Voted one of the most romantic destinations in the world, Kaya Mawa is located on Likoma Island on Lake Malawi. Featuring individual hand-built stone chalets with direct beach entry, private plunge pools, panoramic views of Mozambique across the crystal blue water, and sustainable eco-friendly solar infrastructure.',
 'Likoma Island, Lake Malawi',
 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=600&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=600&fit=crop&q=80","https://images.unsplash.com/photo-1439066615861-d1af74d74000?w=600&fit=crop&q=80"]',
 'v-2', 'Kaya Mawa Lodge Management', 5.0, 1,
 '{"category":"Lodge","amenities":["Private Beach","Plunge Pool","Solar-powered","Complimentary Kayaks","Restaurant & Bar","WiFi"],"rooms":[{"id":"room-1a","name":"Madimba Presidential Chalet","pricePerNight":245000,"capacity":2,"availableRooms":2},{"id":"room-1b","name":"Nkhwazi Standard Beach Chalet","pricePerNight":125000,"capacity":2,"availableRooms":5}]}'),

('acc-2', 'accommodation', 'Sunbird Capital Hotel Lilongwe',
 'The premier 5-star city hotel in the heart of Lilongwe, offering world-class conference facilities, swimming pool, spa, and international restaurant. Ideal for business travelers and luxury-conscious tourists exploring Malawi\'s vibrant capital.',
 'City Centre, Lilongwe',
 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1582719508461-905c673771fd?w=600&fit=crop&q=80"]',
 'v-2', 'Kaya Mawa Lodge Management', 4.5, 1,
 '{"category":"Hotel","amenities":["Swimming Pool","Gym","Spa","Restaurant","Conference Center","WiFi","Parking"],"rooms":[{"id":"room-2a","name":"Deluxe King Room","pricePerNight":95000,"capacity":2,"availableRooms":15},{"id":"room-2b","name":"Executive Suite","pricePerNight":185000,"capacity":2,"availableRooms":6}]}');

-- ─── Tour Listings ─────────────────────────────────────────────────────────
INSERT INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, meta) VALUES

('tour-1', 'tour', 'Nyika Plateau Wildlife & Wildflower Safari',
 'Explore the breathtaking Nyika National Park — Africa\'s largest montane plateau. Spot rare Roan Antelope, Eland, Leopard, and thousands of rare orchid species. Expert ranger-led land cruiser game drives plus overnight wilderness camping under Malawi\'s spectacular star-filled skies.',
 'Nyika National Park, Northern Malawi',
 'https://images.unsplash.com/photo-1523805009345-7448845a9e53?w=600&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=600&fit=crop&q=80"]',
 'v-3', 'Malawi Wildlife Safaris', 4.8, 1,
 '{"durationDays":4,"maxGroupSize":8,"pricePerPerson":280000,"datesAvailable":["2026-07-20","2026-08-03","2026-08-17","2026-09-07"],"itinerary":[{"day":1,"title":"Arrival & Orientation","description":"Transfer to Nyika, sunset game drive"},{"day":2,"title":"Full Day Safari","description":"Morning and afternoon game drives, bush lunch"},{"day":3,"title":"Wildflower Walk","description":"Guided walk through orchid meadows"},{"day":4,"title":"Departure","description":"Final morning drive, transfer back"}]}'),

('tour-2', 'tour', 'Lake Malawi Snorkeling & Island Hopping',
 'Discover the underwater paradise of Lake Malawi, home to over 500 species of colourful cichlid fish found nowhere else on earth. This guided multi-day tour visits Mumbo Island, Domwe Island, Cape Maclear, and Otter Point — the ultimate freshwater scuba and snorkeling adventure in Africa.',
 'Cape Maclear, Lake Malawi National Park',
 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=600&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1530053969600-caed2596d242?w=600&fit=crop&q=80"]',
 'v-3', 'Malawi Wildlife Safaris', 4.9, 1,
 '{"durationDays":3,"maxGroupSize":12,"pricePerPerson":195000,"datesAvailable":["2026-07-25","2026-08-08","2026-08-22","2026-09-05"],"itinerary":[{"day":1,"title":"Cape Maclear Arrival","description":"Check in, afternoon snorkeling intro"},{"day":2,"title":"Island Hopping","description":"Mumbo & Domwe Islands, all-day snorkel"},{"day":3,"title":"Otter Point & Departure","description":"Morning dive, afternoon departure"}]}');

-- ─── Transport Listings ─────────────────────────────────────────────────────
INSERT INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, meta) VALUES

('trans-1', 'transport', 'AXA Executive Coach: Lilongwe → Blantyre',
 'Travel in premium comfort between Malawi\'s two major cities on AXA\'s Executive Coach service. Features plush reclining seats, on-board AC, USB charging points, complimentary bottled water, and professional uniformed drivers. Departs from Wenela Bus Terminal Lilongwe daily.',
 'Wenela Terminal, Lilongwe',
 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=600&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=600&fit=crop&q=80"]',
 'v-4', 'AXA Coach Services', 4.6, 1,
 '{"vehicleType":"Coach Bus","routeFrom":"Lilongwe","routeTo":"Blantyre","departureTime":"06:30 AM","arrivalTime":"12:00 PM","pricePerSeat":18000,"totalSeats":50,"availableSeats":34,"scheduleDays":["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]}'),

('trans-2', 'transport', 'Lilongwe → Mzuzu Express Shuttle',
 'The fastest and most reliable shuttle service connecting Lilongwe to Mzuzu in Northern Malawi. Premium 14-seater minivans with professional drivers, fixed departure times, and door-to-door drop-off service in Mzuzu central.',
 'Area 3 Depot, Lilongwe',
 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=600&fit=crop&q=80',
 '["https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=600&fit=crop&q=80"]',
 'v-4', 'AXA Coach Services', 4.4, 0,
 '{"vehicleType":"Shuttle","routeFrom":"Lilongwe","routeTo":"Mzuzu","departureTime":"05:00 AM","arrivalTime":"11:30 AM","pricePerSeat":22000,"totalSeats":14,"availableSeats":9,"scheduleDays":["Monday","Wednesday","Friday","Saturday"]}');

-- ─── Reviews ──────────────────────────────────────────────────────────────────
INSERT INTO reviews (id, listing_id, user_name, rating, comment, review_date) VALUES
('rev-e1', 'evt-1', 'Grace Banda', 5, 'Absolutely the best festival in Africa! The beach vibe was amazing.', '2026-05-10'),
('rev-e2', 'evt-2', 'Desire Mwalwanda', 5, 'Highly impactful panel discussions and brilliant networking opportunities.', '2026-04-18'),
('rev-e4', 'evt-4', 'Chifundo Phiri', 5, 'Incredible energy! Bullets vs Wanderers is always the highlight of the season.', '2026-05-20'),
('rev-e5', 'evt-5', 'Memory Gondwe', 5, 'The Queens are a national treasure! Griffin Saenda Complex is a brilliant venue.', '2026-06-12'),
('rev-a1', 'acc-1', 'Limbani Chimwaza', 5, 'A slice of heaven. Service is incredible and the chalets are masterpieces.', '2026-05-20');

-- ─── Coupons ──────────────────────────────────────────────────────────────────
INSERT INTO coupons (code, discount_type, value, min_spend, expiry_date) VALUES
('WELCOME10', 'percentage', 10.00, 50000.00, '2026-12-31'),
('SAVE5K', 'fixed', 5000.00, 30000.00, '2026-09-30'),
('LAKESTAR25', 'percentage', 25.00, 100000.00, '2026-10-01');

-- ─── Blog Posts ───────────────────────────────────────────────────────────────
INSERT INTO blog_posts (id, title, excerpt, content, image, author, category, post_date) VALUES
('blog-1', '10 Reasons Why Lake Malawi Should Be On Your Bucket List',
 'From cichlid-filled crystal waters to pristine beaches that rival any in Africa, Lake Malawi offers one of the continent''s most spectacular travel experiences.',
 'Lake Malawi, known as the "Calendar Lake" due to its 365-mile length and 52-mile width, is the third largest lake in Africa and a UNESCO World Heritage Site. Its waters are home to more species of fish than any other lake on earth...',
 'https://images.unsplash.com/photo-1559827260-dc66d52bef19?w=600&fit=crop&q=80',
 'Desire Mwalwanda', 'Travel Guide', '2026-05-15'),
('blog-2', 'Exploring the Mystical Zomba Plateau: A Hiker''s Guide',
 'Rising dramatically from the Shire Highlands, the Zomba Plateau offers world-class hiking, mountain biking, trout fishing, and unrivalled panoramic views of southern Malawi.',
 'The Zomba Plateau sits at an elevation of 2,087 meters and covers an area of approximately 130 square kilometers. Once the colonial capital of Nyasaland, Zomba town sits at the base of this imposing plateau...',
 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&fit=crop&q=80',
 'Chisomo Phiri', 'Travel Guide', '2026-06-01'),
('blog-3', 'Malawi''s Hidden Gem: Nyika National Park',
 'Often overshadowed by East Africa''s famous game reserves, Nyika National Park stands apart with its rolling montane grasslands, rare orchids, and abundant wildlife.',
 'Located in northern Malawi, Nyika National Park is the country''s largest national park and one of Africa''s best-kept secrets for wildlife enthusiasts. The park''s unique high-altitude ecosystem...',
 'https://images.unsplash.com/photo-1523805009345-7448845a9e53?w=600&fit=crop&q=80',
 'Malawi Wildlife Safaris', 'Culture', '2026-06-10');

-- ─── Sample Bookings ──────────────────────────────────────────────────────────
INSERT INTO bookings (id, listing_id, listing_title, listing_image, listing_type, customer_id, customer_name, customer_email, booking_date, details, total_price, commission_paid, payment_status, booking_status, transaction_id, qr_code) VALUES
('BKG-10001', 'evt-1', 'Lake of Stars Festival 2026',
 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=600&fit=crop&q=80',
 'event', 'c-1', 'Grace Banda', 'grace.banda@gmail.com',
 '2026-06-20',
 '{"ticketType":"VIP","quantity":2}',
 240000.00, 24000.00, 'Paid', 'confirmed',
 'TXN-A1B2C3', 'UTHENGA-EV-BKG-10001-GRACE'),

('BKG-10002', 'tour-1', 'Nyika Plateau Wildlife & Wildflower Safari',
 'https://images.unsplash.com/photo-1523805009345-7448845a9e53?w=600&fit=crop&q=80',
 'tour', 'c-2', 'Limbani Chimwaza', 'limbani@outlook.com',
 '2026-06-22',
 '{"tourDate":"2026-07-20","quantity":1}',
 280000.00, 28000.00, 'Paid', 'confirmed',
 'TXN-D4E5F6', 'UTHENGA-TO-BKG-10002-LIMBANI');

-- ─── Sample Transactions ──────────────────────────────────────────────────────
INSERT INTO transactions (id, booking_id, customer_id, customer_name, amount, gateway, status, receipt_number) VALUES
('TXN-A1B2C3', 'BKG-10001', 'c-1', 'Grace Banda', 240000.00, 'Airtel Money', 'Success', 'REC-CT-1234567'),
('TXN-D4E5F6', 'BKG-10002', 'c-2', 'Limbani Chimwaza', 280000.00, 'TNM Mpamba', 'Success', 'REC-CT-7654321');

-- ─── Audit Logs ───────────────────────────────────────────────────────────────
INSERT INTO audit_logs (user_name, user_role, action, details, created_at) VALUES
('System', 'System', 'Database Initialized', 'Uthenga DB seeded with initial data.', NOW()),
('Grace Banda', 'Customer', 'Authorized Payment', 'Paid MK 240,000 via Airtel Money for Lake of Stars Festival VIP x2.', NOW()),
('Limbani Chimwaza', 'Customer', 'Authorized Payment', 'Paid MK 280,000 via TNM Mpamba for Nyika Safari.', NOW());

-- =============================================================================
-- Compatibility Tables & Columns
-- Keeps the base installer aligned with the current PHP application.
-- =============================================================================

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS loyalty_points INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS referral_code VARCHAR(32) NULL,
  ADD COLUMN IF NOT EXISTS push_notify TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS email_notify TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS sms_notify TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS login_alert_email TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS referral_codes (
  id            VARCHAR(30) NOT NULL PRIMARY KEY,
  user_id       VARCHAR(30) NOT NULL,
  code          VARCHAR(32) NOT NULL,
  reward_type   ENUM('loyalty_points','discount_percent','flat_discount') NOT NULL DEFAULT 'loyalty_points',
  reward_value  DECIMAL(15,2) NOT NULL DEFAULT 500.00,
  uses_count    INT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_referral_code (code),
  KEY idx_referral_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referral_uses (
  id                  VARCHAR(30) NOT NULL PRIMARY KEY,
  referral_code_id    VARCHAR(30) NOT NULL,
  referred_user_id    VARCHAR(30) NOT NULL,
  referrer_rewarded   TINYINT(1) NOT NULL DEFAULT 0,
  referee_rewarded    TINYINT(1) NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_referral_uses_ref_user (referral_code_id, referred_user_id),
  KEY idx_referral_uses_code (referral_code_id),
  KEY idx_referral_uses_user (referred_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_transactions (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     VARCHAR(30) NOT NULL,
  points      INT NOT NULL,
  reason      VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_loyalty_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_vouchers (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_code     VARCHAR(32) NOT NULL,
  purchased_by     VARCHAR(30) NULL,
  recipient_email  VARCHAR(180) NULL,
  recipient_name   VARCHAR(150) NULL,
  amount_mwk       DECIMAL(15,2) NOT NULL,
  balance_mwk      DECIMAL(15,2) NOT NULL,
  currency         CHAR(3) NOT NULL DEFAULT 'MWK',
  message          TEXT NULL,
  valid_from       DATE NOT NULL,
  valid_to         DATE NOT NULL,
  redeemed_by      VARCHAR(30) NULL,
  status           ENUM('active','partially_used','fully_redeemed','expired','cancelled') NOT NULL DEFAULT 'active',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gift_vouchers_code (voucher_code),
  KEY idx_gift_vouchers_purchaser (purchased_by),
  KEY idx_gift_vouchers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS two_factor_auth (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       VARCHAR(30) NOT NULL,
  secret        VARCHAR(64) NOT NULL,
  backup_codes  JSON NULL,
  enabled_at    DATETIME NULL,
  last_used_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_two_factor_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS device_sessions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         VARCHAR(30) NOT NULL,
  session_token   VARCHAR(128) NOT NULL,
  device_name     VARCHAR(255) NULL,
  device_type     ENUM('desktop','mobile','tablet','unknown') NOT NULL DEFAULT 'unknown',
  browser         VARCHAR(100) NULL,
  os              VARCHAR(100) NULL,
  ip_address      VARCHAR(45) NULL,
  country         VARCHAR(80) NULL,
  city            VARCHAR(80) NULL,
  is_current      TINYINT(1) NOT NULL DEFAULT 0,
  last_active_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_device_sessions_token (session_token),
  KEY idx_device_sessions_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_alerts (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     VARCHAR(30) NOT NULL,
  alert_type  VARCHAR(60) NOT NULL DEFAULT 'new_device',
  ip_address  VARCHAR(45) NULL,
  user_agent  VARCHAR(500) NULL,
  details     JSON NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_alerts_user (user_id),
  KEY idx_login_alerts_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fraud_alerts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      VARCHAR(30) NULL,
  booking_id   VARCHAR(20) NULL,
  alert_type   VARCHAR(80) NOT NULL,
  risk_score   TINYINT UNSIGNED NOT NULL DEFAULT 50,
  details      TEXT NULL,
  status       ENUM('open','reviewed','dismissed','escalated') NOT NULL DEFAULT 'open',
  reviewed_by  VARCHAR(30) NULL,
  reviewed_at  DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_fraud_alerts_user (user_id),
  KEY idx_fraud_alerts_status (status),
  KEY idx_fraud_alerts_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          VARCHAR(30) NULL,
  email            VARCHAR(180) NOT NULL,
  full_name        VARCHAR(150) NULL,
  preferences      JSON NULL,
  status           ENUM('subscribed','unsubscribed','bounced') NOT NULL DEFAULT 'subscribed',
  token            VARCHAR(64) NOT NULL,
  subscribed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at  DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_newsletter_email (email),
  UNIQUE KEY uq_newsletter_token (token),
  KEY idx_newsletter_user (user_id),
  KEY idx_newsletter_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_campaigns (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject     VARCHAR(255) NOT NULL,
  body_html   LONGTEXT NULL,
  body_text   TEXT NOT NULL,
  audience    VARCHAR(60) NOT NULL DEFAULT 'all',
  status      ENUM('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
  sent_count  INT UNSIGNED NOT NULL DEFAULT 0,
  scheduled_at DATETIME NULL,
  sent_at     DATETIME NULL,
  created_by  VARCHAR(30) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_newsletter_campaigns_status (status),
  KEY idx_newsletter_campaigns_creator (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
