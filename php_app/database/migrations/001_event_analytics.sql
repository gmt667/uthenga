-- =============================================================================
-- Migration 001: Event Analytics for AI-Powered Ranking
-- Run this against the uthenga_app database.
-- Safe to run multiple times (CREATE TABLE IF NOT EXISTS).
-- =============================================================================

CREATE TABLE IF NOT EXISTS event_analytics (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id         VARCHAR(64) NOT NULL,
  view_count       INT UNSIGNED NOT NULL DEFAULT 0,
  booking_count    INT UNSIGNED NOT NULL DEFAULT 0,
  wishlist_count   INT UNSIGNED NOT NULL DEFAULT 0,
  click_count      INT UNSIGNED NOT NULL DEFAULT 0,
  popularity_score DECIMAL(12,2) NOT NULL DEFAULT 0.00
                   COMMENT 'Weighted score: views*1 + bookings*5 + wishlist*3 + clicks*2',
  last_computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_analytics_event (event_id),
  KEY idx_event_analytics_score (popularity_score DESC),
  KEY idx_event_analytics_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks event interaction metrics for popularity-based ranking';

-- Seed analytics rows for all existing published events (score starts at 0).
-- The application will accumulate real data over time.
INSERT IGNORE INTO event_analytics (event_id, view_count, booking_count, wishlist_count, click_count, popularity_score)
SELECT
    l.id,
    0,
    COALESCE(b.booking_count, 0),  -- pre-seed with existing booking data
    COALESCE(w.wishlist_count, 0),
    0,
    (COALESCE(b.booking_count, 0) * 5) + (COALESCE(w.wishlist_count, 0) * 3)  -- initial score from existing bookings / wishlist
FROM listings l
LEFT JOIN (
    SELECT listing_id, COUNT(*) AS booking_count
    FROM bookings
    WHERE listing_type = 'event'
    GROUP BY listing_id
) b ON b.listing_id = l.id
LEFT JOIN (
    SELECT listing_id, COUNT(*) AS wishlist_count
    FROM wishlist
    GROUP BY listing_id
) w ON w.listing_id = l.id
WHERE l.listing_type = 'event'
  AND l.is_active = 1;

-- Update popularity_score for any rows already having booking data
UPDATE event_analytics ea
JOIN (
    SELECT listing_id, COUNT(*) AS cnt
    FROM bookings
    WHERE listing_type = 'event'
    GROUP BY listing_id
) b ON b.listing_id = ea.event_id
SET ea.booking_count = b.cnt,
    ea.popularity_score = (ea.view_count * 1) + (b.cnt * 5) + (ea.wishlist_count * 3) + (ea.click_count * 2);
