-- =============================================================================
-- Migration 002: Promotional Popups for Homepage
-- Run this against the uthenga_app database.
-- Safe to run multiple times (CREATE TABLE IF NOT EXISTS).
-- =============================================================================

CREATE TABLE IF NOT EXISTS promotional_popups (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) NOT NULL,
  description TEXT NULL,
  image_url   VARCHAR(500) NULL         COMMENT 'URL or relative path to popup image',
  cta_text    VARCHAR(100) NOT NULL DEFAULT 'Learn More',
  cta_url     VARCHAR(500) NOT NULL DEFAULT '#',
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  start_date  DATE NULL                 COMMENT 'NULL means no start restriction',
  end_date    DATE NULL                 COMMENT 'NULL means no end restriction',
  display_delay_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 3
              COMMENT 'Seconds to wait after page load before showing popup',
  created_by  BIGINT UNSIGNED NULL,
  updated_by  BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_popup_active_dates (is_active, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Homepage promotional popups managed by admins';

-- Insert a sample welcome popup so admins can see it immediately
INSERT IGNORE INTO promotional_popups
  (title, description, cta_text, cta_url, is_active, start_date, end_date)
VALUES (
  'Welcome to Uthenga!',
  'Discover events, stays, transport and tours across Malawi. Book in seconds.',
  'Explore Now',
  'events.php',
  1,
  CURDATE(),
  DATE_ADD(CURDATE(), INTERVAL 30 DAY)
);
