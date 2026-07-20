-- ============================================================
-- Migration: 005_core_schema_compatibility.sql
-- Adds missing core tables and compatibility columns for the
-- current Uthenga PHP application on MySQL/XAMPP.
-- Safe to run on an existing database.
-- ============================================================


-- -----------------------------------------------------------------
-- Users: profile/auth compatibility
-- -----------------------------------------------------------------
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS full_name VARCHAR(150) GENERATED ALWAYS AS (name) STORED,
  ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(500) GENERATED ALWAYS AS (avatar) STORED,
  ADD COLUMN IF NOT EXISTS account_status VARCHAR(20) NOT NULL DEFAULT 'active',
  ADD COLUMN IF NOT EXISTS notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS joined_date DATE NOT NULL DEFAULT (CURDATE()),
  ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS phone_verified_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) GENERATED ALWAYS AS (must_change_pw) STORED;

-- -----------------------------------------------------------------
-- Core RBAC and session tables
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

CREATE TABLE IF NOT EXISTS admin_permissions (
  user_id     VARCHAR(30) NOT NULL PRIMARY KEY,
  permissions JSON NOT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Vendor management
-- -----------------------------------------------------------------
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
  CONSTRAINT fk_vendor_profiles_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_vendor_profiles_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Marketplace content
-- -----------------------------------------------------------------
ALTER TABLE listings
  MODIFY COLUMN listing_type VARCHAR(30) NOT NULL,
  ADD COLUMN IF NOT EXISTS gallery JSON NULL AFTER image,
  ADD COLUMN IF NOT EXISTS rating DECIMAL(3,1) NOT NULL DEFAULT 0.0,
  ADD COLUMN IF NOT EXISTS featured TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS meta JSON NOT NULL AFTER is_active,
  ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS reviews (
  id          VARCHAR(20) NOT NULL PRIMARY KEY,
  listing_id  VARCHAR(30) NOT NULL,
  user_name   VARCHAR(120) NOT NULL,
  rating      TINYINT NOT NULL,
  comment     TEXT NOT NULL,
  review_date DATE NOT NULL DEFAULT (CURDATE()),
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reviews_listing (listing_id),
  CONSTRAINT fk_reviews_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wishlist (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    VARCHAR(30) NOT NULL,
  listing_id VARCHAR(30) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wishlist_user_listing (user_id, listing_id),
  CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Bookings and payments
-- -----------------------------------------------------------------
ALTER TABLE bookings
  MODIFY COLUMN booking_status VARCHAR(30) NOT NULL DEFAULT 'pending',
  MODIFY COLUMN payment_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
  ADD COLUMN IF NOT EXISTS booking_code VARCHAR(30) GENERATED ALWAYS AS (id) STORED,
  ADD COLUMN IF NOT EXISTS booked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'MWK',
  ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS commission_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS grand_total DECIMAL(15,2) GENERATED ALWAYS AS (total_price) STORED,
  ADD COLUMN IF NOT EXISTS reference_name VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS customer_notes TEXT NULL,
  ADD COLUMN IF NOT EXISTS vendor_notes TEXT NULL,
  ADD COLUMN IF NOT EXISTS confirmed_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS cancelled_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

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
  CONSTRAINT fk_booking_items_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_items_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE transactions
  MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Pending',
  MODIFY COLUMN gateway VARCHAR(100) NOT NULL,
  ADD COLUMN IF NOT EXISTS transaction_reference VARCHAR(60) GENERATED ALWAYS AS (id) STORED,
  ADD COLUMN IF NOT EXISTS gateway_name VARCHAR(100) GENERATED ALWAYS AS (gateway) STORED,
  ADD COLUMN IF NOT EXISTS user_id VARCHAR(30) GENERATED ALWAYS AS (customer_id) STORED,
  ADD COLUMN IF NOT EXISTS vendor_id VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS transaction_type VARCHAR(40) NULL,
  ADD COLUMN IF NOT EXISTS metadata JSON NULL,
  ADD COLUMN IF NOT EXISTS transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------
-- Support / admin ops
-- -----------------------------------------------------------------
ALTER TABLE support_tickets
  MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'open',
  ADD COLUMN IF NOT EXISTS ticket_code VARCHAR(32) GENERATED ALWAYS AS (id) STORED,
  ADD COLUMN IF NOT EXISTS requester_user_id VARCHAR(30) GENERATED ALWAYS AS (customer_id) STORED,
  ADD COLUMN IF NOT EXISTS requester_name VARCHAR(120) GENERATED ALWAYS AS (customer_name) STORED,
  ADD COLUMN IF NOT EXISTS requester_email VARCHAR(180) GENERATED ALWAYS AS (customer_email) STORED,
  ADD COLUMN IF NOT EXISTS priority VARCHAR(20) NOT NULL DEFAULT 'medium',
  ADD COLUMN IF NOT EXISTS assigned_admin_id VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS closed_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS ticket_responses (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id  VARCHAR(20) NOT NULL,
  sender     VARCHAR(120) NOT NULL,
  message    TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket_responses_ticket (ticket_id),
  CONSTRAINT fk_ticket_responses_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE audit_logs
  ADD COLUMN IF NOT EXISTS actor_user_id VARCHAR(30) GENERATED ALWAYS AS (user_id) STORED,
  ADD COLUMN IF NOT EXISTS entity_table VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS entity_id VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS before_data JSON NULL,
  ADD COLUMN IF NOT EXISTS after_data JSON NULL,
  ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL,
  ADD COLUMN IF NOT EXISTS user_agent VARCHAR(500) NULL;

-- -----------------------------------------------------------------
-- Platform configuration and notifications
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  `key`      VARCHAR(100) NOT NULL PRIMARY KEY,
  `value`    TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(30) NULL,
  CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
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
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
  CONSTRAINT fk_notification_logs_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL
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
  CONSTRAINT fk_system_announcements_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
  code          VARCHAR(30) NOT NULL PRIMARY KEY,
  discount_type VARCHAR(20) NOT NULL,
  value         DECIMAL(10,2) NOT NULL,
  min_spend     DECIMAL(10,2) NULL,
  expiry_date   DATE NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT fk_system_health_recorded_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Event / QR session tracking
-- -----------------------------------------------------------------
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
  CONSTRAINT fk_gate_sessions_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_gate_sessions_started_by FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE CASCADE
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
  CONSTRAINT fk_gate_scans_session FOREIGN KEY (session_id) REFERENCES gate_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_gate_scans_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_gate_scans_scanned_by FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
