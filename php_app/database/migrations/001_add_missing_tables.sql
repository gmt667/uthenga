-- ============================================================
-- Migration: 001_add_missing_tables.sql
-- Adds missing tables and columns to complete Uthenga restructures
-- ============================================================


-- 1. Alter users table to add phone and notification preferences
ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER email;
ALTER TABLE users ADD COLUMN notifications_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER must_change_pw;

-- 2. Alter bookings table to support 'completed' status
ALTER TABLE bookings MODIFY COLUMN booking_status ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending';

-- 3. Create vendor_profiles table
CREATE TABLE IF NOT EXISTS vendor_profiles (
  vendor_id       VARCHAR(30)   NOT NULL PRIMARY KEY,
  phone           VARCHAR(30)   NULL,
  address         VARCHAR(255)  NULL,
  city            VARCHAR(100)  NULL,
  description     TEXT          NULL,
  category        VARCHAR(50)   NULL,
  approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  approved_at     DATETIME      NULL,
  approved_by     VARCHAR(30)   NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. Create wishlist table
CREATE TABLE IF NOT EXISTS wishlist (
  id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  user_id         VARCHAR(30)   NOT NULL,
  listing_id      VARCHAR(30)   NOT NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_listing (user_id, listing_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
  id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  user_id         VARCHAR(30)   NOT NULL,
  type            VARCHAR(50)   NOT NULL COMMENT 'info, booking, payment, system',
  title           VARCHAR(200)  NOT NULL,
  message         TEXT          NOT NULL,
  is_read         TINYINT(1)    NOT NULL DEFAULT 0,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_unread (user_id, is_read),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Create settings table
CREATE TABLE IF NOT EXISTS settings (
  `key`           VARCHAR(100)  NOT NULL PRIMARY KEY,
  `value`         TEXT          NULL,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by      VARCHAR(30)   NULL,
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed default settings
INSERT INTO settings (`key`, `value`) VALUES
('commission_rate', '10'),
('platform_name', 'Uthenga'),
('platform_email', 'support@uthenga.co'),
('allow_vendor_registration', '1')
ON DUPLICATE KEY UPDATE `key` = `key`;
