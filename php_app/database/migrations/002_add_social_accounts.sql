-- ================================================================
-- Migration: 002_add_social_accounts.sql
-- Adds the social_accounts table for Google / Facebook / Microsoft OAuth
-- Compatible with the existing setup.sql users.id VARCHAR(30) schema
-- ================================================================


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
