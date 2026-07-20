-- ============================================================
-- Migration: 009_marketing_security_phase.sql
-- Tables for: Newsletter, Referrals, Fraud Alerts,
-- Login Anomalies, Newsletter Campaigns, Admin System Monitor.
-- Safe to re-run (all statements use IF NOT EXISTS / IF NOT EXISTS).
-- ============================================================

-- -----------------------------------------------------------------
-- Newsletter Subscribers
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          BIGINT UNSIGNED NULL,
  email            VARCHAR(180) NOT NULL,
  full_name        VARCHAR(150) NULL,
  preferences      JSON NULL COMMENT 'e.g. ["events","travel","deals"]',
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

-- -----------------------------------------------------------------
-- Newsletter Campaigns
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS newsletter_campaigns (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject     VARCHAR(255) NOT NULL,
  body_html   LONGTEXT NULL,
  body_text   TEXT NOT NULL,
  audience    VARCHAR(60) NOT NULL DEFAULT 'all' COMMENT 'all | events | travel | deals | transport',
  status      ENUM('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
  sent_count  INT UNSIGNED NOT NULL DEFAULT 0,
  scheduled_at DATETIME NULL,
  sent_at     DATETIME NULL,
  created_by  BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_newsletter_campaigns_status (status),
  KEY idx_newsletter_campaigns_creator (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Referral Codes
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS referral_codes (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       BIGINT UNSIGNED NOT NULL,
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

-- -----------------------------------------------------------------
-- Referral Uses
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS referral_uses (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referral_code_id    BIGINT UNSIGNED NOT NULL,
  referred_user_id    BIGINT UNSIGNED NOT NULL,
  referrer_rewarded   TINYINT(1) NOT NULL DEFAULT 0,
  referee_rewarded    TINYINT(1) NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_referral_uses_ref_user (referral_code_id, referred_user_id),
  KEY idx_referral_uses_code (referral_code_id),
  KEY idx_referral_uses_user (referred_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Fraud Alerts  
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fraud_alerts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NULL,
  booking_id   BIGINT UNSIGNED NULL,
  alert_type   VARCHAR(80) NOT NULL COMMENT 'e.g. velocity_check, large_amount, new_device_payment',
  risk_score   TINYINT UNSIGNED NOT NULL DEFAULT 50 COMMENT '0-100',
  details      TEXT NULL,
  status       ENUM('open','reviewed','dismissed','escalated') NOT NULL DEFAULT 'open',
  reviewed_by  BIGINT UNSIGNED NULL,
  reviewed_at  DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_fraud_alerts_user (user_id),
  KEY idx_fraud_alerts_status (status),
  KEY idx_fraud_alerts_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Login Alerts (Anomaly tracking per device/IP)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_alerts (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  alert_type  VARCHAR(60) NOT NULL DEFAULT 'new_device' COMMENT 'new_device | geo_anomaly | brute_force',
  ip_address  VARCHAR(45) NULL,
  user_agent  VARCHAR(500) NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_alerts_user (user_id),
  KEY idx_login_alerts_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Add referral_code column to users if not already present
-- -----------------------------------------------------------------
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS referral_code VARCHAR(32) NULL,
  ADD COLUMN IF NOT EXISTS loyalty_points INT NOT NULL DEFAULT 0;

-- -----------------------------------------------------------------
-- Device Sessions (for 2FA / Multi-device security)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS device_sessions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
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
  KEY idx_device_sessions_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Two-Factor Auth
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS two_factor_auth (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       BIGINT UNSIGNED NOT NULL,
  secret        VARCHAR(64) NOT NULL,
  is_enabled    TINYINT(1) NOT NULL DEFAULT 0,
  backup_codes  JSON NULL COMMENT 'JSON array of hashed backup codes',
  enabled_at    DATETIME NULL,
  last_used_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_two_factor_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Loyalty Transactions
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loyalty_transactions (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  points      INT NOT NULL COMMENT 'Positive = credit, Negative = debit',
  reason      VARCHAR(80) NOT NULL COMMENT 'e.g. referral | signup | booking | redemption',
  description VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_loyalty_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Gift Vouchers (ensure table exists - backfill if 008 missed)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gift_vouchers (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_code     VARCHAR(32) NOT NULL,
  purchased_by     BIGINT UNSIGNED NULL,
  recipient_email  VARCHAR(180) NULL,
  recipient_name   VARCHAR(150) NULL,
  amount_mwk       DECIMAL(15,2) NOT NULL,
  balance_mwk      DECIMAL(15,2) NOT NULL,
  currency         CHAR(3) NOT NULL DEFAULT 'MWK',
  message          TEXT NULL,
  valid_from       DATE NOT NULL,
  valid_to         DATE NOT NULL,
  redeemed_by      BIGINT UNSIGNED NULL,
  status           ENUM('active','partially_used','fully_redeemed','expired','cancelled') NOT NULL DEFAULT 'active',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gift_vouchers_code (voucher_code),
  KEY idx_gift_vouchers_purchaser (purchased_by),
  KEY idx_gift_vouchers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
