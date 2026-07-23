-- ============================================================
-- Migration 013: Security Schema Compatibility
-- Adds missing security tables and columns for installs where
-- the 2FA / device-session features were not applied yet.
-- Compatible with the current Uthenga users.id VARCHAR schema.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS device_sessions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         VARCHAR(30) NOT NULL,
  session_token   VARCHAR(128) NOT NULL,
  device_name     VARCHAR(255) NULL,
  device_type     ENUM('desktop','mobile','tablet','unknown') NOT NULL DEFAULT 'unknown',
  os              VARCHAR(100) NULL,
  browser         VARCHAR(100) NULL,
  ip_address      VARCHAR(45) NULL,
  country         VARCHAR(80) NULL,
  city            VARCHAR(80) NULL,
  is_current      TINYINT(1) NOT NULL DEFAULT 0,
  last_active_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_device_sessions_token (session_token),
  KEY idx_device_sessions_user (user_id),
  KEY idx_device_sessions_current (user_id, is_current),
  CONSTRAINT fk_device_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_alerts (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     VARCHAR(30) NOT NULL,
  alert_type  VARCHAR(60) NOT NULL DEFAULT 'new_device',
  ip_address  VARCHAR(45) NULL,
  user_agent  VARCHAR(500) NULL,
  details     TEXT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_alerts_user (user_id),
  KEY idx_login_alerts_read (is_read),
  KEY idx_login_alerts_type (alert_type),
  CONSTRAINT fk_login_alerts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS two_factor_auth (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       VARCHAR(30) NOT NULL,
  secret        VARCHAR(64) NOT NULL,
  is_enabled    TINYINT(1) NOT NULL DEFAULT 0,
  backup_codes  JSON NULL,
  enabled_at    DATETIME NULL,
  last_used_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_two_factor_user (user_id),
  KEY idx_two_factor_enabled (is_enabled),
  CONSTRAINT fk_two_factor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER must_change_pw;

SET FOREIGN_KEY_CHECKS = 1;
