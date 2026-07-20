-- =============================================================================
-- Migration 008: Feature Enhancements
-- Uthenga Marketplace — Additive only, no breaking changes
-- Adds tables/columns for: map points, weather cache, AI itineraries,
-- destination guides, airport transfers, car rentals, driver profiles,
-- event promo codes, seat maps, 2FA, device sessions, newsletter,
-- referrals, loyalty points, gift vouchers, sponsored listings,
-- local business listings, fraud detection, and payment extensions.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1) MAP POINTS (for interactive tourism map)
-- =============================================================================

CREATE TABLE IF NOT EXISTS map_points (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(200) NOT NULL,
  point_type   ENUM('attraction','hotel','restaurant','hospital','fuel_station','atm','transport','airport','marina','curio_shop','cafe','other') NOT NULL DEFAULT 'attraction',
  latitude     DECIMAL(10,8) NOT NULL,
  longitude    DECIMAL(11,8) NOT NULL,
  city         VARCHAR(120) NULL,
  address      VARCHAR(300) NULL,
  phone        VARCHAR(30) NULL,
  website      VARCHAR(500) NULL,
  description  TEXT NULL,
  image_url    VARCHAR(500) NULL,
  rating       DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  is_featured  TINYINT(1) NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_by   BIGINT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME NULL,
  KEY idx_map_points_type (point_type),
  KEY idx_map_points_lat_lng (latitude, longitude),
  KEY idx_map_points_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2) WEATHER CACHE
-- =============================================================================

CREATE TABLE IF NOT EXISTS weather_cache (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  city         VARCHAR(120) NOT NULL,
  latitude     DECIMAL(10,8) NULL,
  longitude    DECIMAL(11,8) NULL,
  weather_data JSON NOT NULL,
  fetched_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME NOT NULL,
  UNIQUE KEY uq_weather_cache_city (city),
  KEY idx_weather_cache_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3) TRIP ITINERARIES (AI-generated & manual)
-- =============================================================================

CREATE TABLE IF NOT EXISTS trip_itineraries (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  itinerary_code  VARCHAR(32) NOT NULL,
  user_id         VARCHAR(30) NULL,
  title           VARCHAR(220) NOT NULL,
  destination     VARCHAR(200) NOT NULL,
  duration_days   INT UNSIGNED NOT NULL DEFAULT 1,
  travel_date     DATE NULL,
  budget_mwk      DECIMAL(15,2) NULL,
  group_size      INT UNSIGNED NOT NULL DEFAULT 1,
  itinerary_data  JSON NOT NULL COMMENT 'Day-by-day schedule as JSON',
  ai_generated    TINYINT(1) NOT NULL DEFAULT 0,
  pdf_url         VARCHAR(500) NULL,
  share_token     VARCHAR(64) NULL,
  is_public       TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_trip_itineraries_code (itinerary_code),
  KEY idx_trip_itineraries_user (user_id),
  KEY idx_trip_itineraries_share (share_token),
  CONSTRAINT fk_trip_itineraries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4) DESTINATION GUIDES & TRAVEL TIPS
-- =============================================================================

CREATE TABLE IF NOT EXISTS destination_guides (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_id  BIGINT UNSIGNED NULL,
  city         VARCHAR(120) NOT NULL,
  title        VARCHAR(220) NOT NULL,
  slug         VARCHAR(240) NOT NULL,
  summary      VARCHAR(500) NULL,
  content      LONGTEXT NOT NULL,
  cover_image  VARCHAR(500) NULL,
  best_time    VARCHAR(200) NULL COMMENT 'Best time to visit description',
  travel_tips  JSON NULL COMMENT 'Array of tip strings',
  tags         JSON NULL,
  is_featured  TINYINT(1) NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_by   BIGINT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_destination_guides_slug (slug),
  KEY idx_destination_guides_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5) AIRPORT TRANSFERS
-- =============================================================================

CREATE TABLE IF NOT EXISTS airport_transfers (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id         VARCHAR(20) NULL,
  passenger_id       VARCHAR(30) NULL,
  transfer_type      ENUM('arrival','departure','round_trip') NOT NULL DEFAULT 'arrival',
  airport            VARCHAR(120) NOT NULL DEFAULT 'Chileka International Airport',
  destination_address VARCHAR(300) NOT NULL,
  pickup_datetime    DATETIME NOT NULL,
  return_datetime    DATETIME NULL,
  flight_number      VARCHAR(30) NULL,
  passengers         INT UNSIGNED NOT NULL DEFAULT 1,
  luggage_count      INT UNSIGNED NOT NULL DEFAULT 1,
  vehicle_type       ENUM('sedan','minivan','suv','bus') NOT NULL DEFAULT 'sedan',
  driver_id          VARCHAR(30) NULL,
  fare_mwk           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status             ENUM('pending','confirmed','driver_assigned','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  notes              TEXT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_airport_transfers_booking (booking_id),
  KEY idx_airport_transfers_passenger (passenger_id),
  KEY idx_airport_transfers_driver (driver_id),
  KEY idx_airport_transfers_datetime (pickup_datetime),
  CONSTRAINT fk_airport_transfers_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_airport_transfers_passenger FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6) CAR RENTALS
-- =============================================================================

CREATE TABLE IF NOT EXISTS car_rental_listings (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id      VARCHAR(30) NOT NULL,
  listing_code   VARCHAR(32) NOT NULL,
  vehicle_name   VARCHAR(180) NOT NULL,
  vehicle_type   ENUM('sedan','suv','minivan','4x4','bus','motorbike') NOT NULL DEFAULT 'sedan',
  make           VARCHAR(80) NULL,
  model          VARCHAR(80) NULL,
  year           YEAR NULL,
  color          VARCHAR(50) NULL,
  plate_number   VARCHAR(30) NULL,
  seats          INT UNSIGNED NOT NULL DEFAULT 4,
  transmission   ENUM('automatic','manual') NOT NULL DEFAULT 'manual',
  fuel_type      ENUM('petrol','diesel','electric','hybrid') NOT NULL DEFAULT 'petrol',
  with_driver    TINYINT(1) NOT NULL DEFAULT 0,
  price_per_day  DECIMAL(15,2) NOT NULL,
  currency       CHAR(3) NOT NULL DEFAULT 'MWK',
  image_url      VARCHAR(500) NULL,
  features       JSON NULL,
  location       VARCHAR(200) NULL,
  is_available   TINYINT(1) NOT NULL DEFAULT 1,
  status         ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_car_rental_code (listing_code),
  KEY idx_car_rental_vendor (vendor_id),
  CONSTRAINT fk_car_rental_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS car_rental_bookings (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id      VARCHAR(20) NULL,
  car_id          BIGINT UNSIGNED NOT NULL,
  renter_id       VARCHAR(30) NULL,
  driver_id       VARCHAR(30) NULL,
  pickup_date     DATE NOT NULL,
  return_date     DATE NOT NULL,
  pickup_location VARCHAR(300) NULL,
  total_days      INT UNSIGNED NOT NULL DEFAULT 1,
  total_fare      DECIMAL(15,2) NOT NULL,
  status          ENUM('pending','confirmed','active','returned','cancelled') NOT NULL DEFAULT 'pending',
  notes           TEXT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_car_rental_bookings_booking (booking_id),
  KEY idx_car_rental_bookings_car (car_id),
  KEY idx_car_rental_bookings_renter (renter_id),
  CONSTRAINT fk_car_rental_bookings_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_car_rental_bookings_car FOREIGN KEY (car_id) REFERENCES car_rental_listings(id) ON DELETE RESTRICT,
  CONSTRAINT fk_car_rental_bookings_renter FOREIGN KEY (renter_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7) DRIVER PROFILES (for transport, airport transfers, car rentals)
-- =============================================================================

CREATE TABLE IF NOT EXISTS driver_profiles (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          VARCHAR(30) NOT NULL,
  vendor_id        VARCHAR(30) NULL,
  driver_code      VARCHAR(32) NOT NULL,
  license_number   VARCHAR(80) NOT NULL,
  license_class    VARCHAR(30) NULL,
  license_expiry   DATE NULL,
  id_number        VARCHAR(50) NULL,
  years_experience INT UNSIGNED NOT NULL DEFAULT 0,
  languages        JSON NULL,
  bio              TEXT NULL,
  photo_url        VARCHAR(500) NULL,
  vehicle_types    JSON NULL COMMENT 'Array of vehicle types driver can operate',
  rating_average   DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  rating_count     INT UNSIGNED NOT NULL DEFAULT 0,
  total_trips      INT UNSIGNED NOT NULL DEFAULT 0,
  is_verified      TINYINT(1) NOT NULL DEFAULT 0,
  verified_at      DATETIME NULL,
  verified_by      VARCHAR(30) NULL,
  status           ENUM('pending','active','suspended','inactive') NOT NULL DEFAULT 'pending',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_driver_profiles_user (user_id),
  UNIQUE KEY uq_driver_profiles_code (driver_code),
  KEY idx_driver_profiles_vendor (vendor_id),
  KEY idx_driver_profiles_status (status),
  CONSTRAINT fk_driver_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_driver_profiles_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS driver_ratings (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_id   BIGINT UNSIGNED NOT NULL,
  rater_id    VARCHAR(30) NOT NULL,
  booking_id  VARCHAR(20) NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  comment     TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (rating BETWEEN 1 AND 5),
  KEY idx_driver_ratings_driver (driver_id),
  CONSTRAINT fk_driver_ratings_driver FOREIGN KEY (driver_id) REFERENCES driver_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_driver_ratings_rater FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 8) EVENT PROMO CODES
-- =============================================================================

CREATE TABLE IF NOT EXISTS event_promo_codes (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id        VARCHAR(30) NULL COMMENT 'NULL = applies to all events for vendor',
  vendor_id       VARCHAR(30) NOT NULL,
  code            VARCHAR(50) NOT NULL,
  description     VARCHAR(255) NULL,
  discount_type   ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
  discount_value  DECIMAL(10,2) NOT NULL,
  min_order_amount DECIMAL(15,2) NULL,
  max_discount    DECIMAL(15,2) NULL COMMENT 'Cap on percentage discounts',
  max_uses        INT UNSIGNED NULL COMMENT 'NULL = unlimited',
  used_count      INT UNSIGNED NOT NULL DEFAULT 0,
  max_uses_per_user INT UNSIGNED NOT NULL DEFAULT 1,
  valid_from      DATETIME NOT NULL,
  valid_to        DATETIME NOT NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_promo_codes_code (code),
  KEY idx_event_promo_codes_event (event_id),
  KEY idx_event_promo_codes_vendor (vendor_id),
  KEY idx_event_promo_codes_validity (valid_from, valid_to),
  CONSTRAINT fk_event_promo_codes_event FOREIGN KEY (event_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_event_promo_codes_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promo_code_uses (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  promo_code_id BIGINT UNSIGNED NOT NULL,
  user_id       VARCHAR(30) NOT NULL,
  booking_id    VARCHAR(20) NULL,
  discount_given DECIMAL(15,2) NOT NULL,
  used_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_promo_code_uses_code (promo_code_id),
  KEY idx_promo_code_uses_user (user_id),
  CONSTRAINT fk_promo_code_uses_code FOREIGN KEY (promo_code_id) REFERENCES event_promo_codes(id) ON DELETE CASCADE,
  CONSTRAINT fk_promo_code_uses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 9) EVENT SEAT MAPS
-- =============================================================================

CREATE TABLE IF NOT EXISTS event_seat_maps (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id    VARCHAR(30) NOT NULL,
  map_name    VARCHAR(120) NULL,
  seat_data   JSON NOT NULL COMMENT 'Seat layout: rows, columns, labels, status per seat',
  total_seats INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_seat_maps_event (event_id),
  CONSTRAINT fk_event_seat_maps_event FOREIGN KEY (event_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seat_reservations (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id      VARCHAR(30) NOT NULL,
  booking_id    VARCHAR(20) NULL,
  user_id       VARCHAR(30) NULL,
  seat_label    VARCHAR(20) NOT NULL,
  row_label     VARCHAR(10) NULL,
  section       VARCHAR(50) NULL,
  status        ENUM('available','reserved','booked','blocked') NOT NULL DEFAULT 'booked',
  reserved_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    DATETIME NULL COMMENT 'For temporary holds',
  KEY idx_seat_reservations_event (event_id),
  KEY idx_seat_reservations_booking (booking_id),
  UNIQUE KEY uq_seat_reservations_event_seat (event_id, seat_label),
  CONSTRAINT fk_seat_reservations_event FOREIGN KEY (event_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_seat_reservations_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_seat_reservations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 10) TWO-FACTOR AUTHENTICATION
-- =============================================================================

CREATE TABLE IF NOT EXISTS two_factor_auth (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       VARCHAR(30) NOT NULL,
  secret        VARCHAR(64) NOT NULL COMMENT 'Base32 TOTP secret',
  backup_codes  JSON NULL COMMENT 'Array of hashed backup codes',
  enabled_at    DATETIME NULL,
  last_used_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_2fa_user (user_id),
  CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 11) DEVICE / SESSION MANAGEMENT
-- =============================================================================

CREATE TABLE IF NOT EXISTS device_sessions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         VARCHAR(30) NOT NULL,
  session_token   VARCHAR(128) NOT NULL,
  device_name     VARCHAR(200) NULL COMMENT 'Browser + OS description',
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
  KEY idx_device_sessions_user (user_id),
  CONSTRAINT fk_device_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_alerts (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     VARCHAR(30) NOT NULL,
  alert_type  ENUM('new_device','new_location','failed_attempts','2fa_bypass','password_changed','account_locked') NOT NULL,
  ip_address  VARCHAR(45) NULL,
  user_agent  VARCHAR(500) NULL,
  details     JSON NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_alerts_user (user_id),
  KEY idx_login_alerts_type (alert_type),
  CONSTRAINT fk_login_alerts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 12) FRAUD DETECTION
-- =============================================================================

CREATE TABLE IF NOT EXISTS fraud_alerts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      VARCHAR(30) NULL,
  booking_id   VARCHAR(20) NULL,
  alert_type   ENUM('multiple_failed_payments','suspicious_booking_pattern','velocity_check','chargeback_risk','duplicate_booking') NOT NULL,
  risk_score   TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100 risk score',
  details      JSON NULL,
  status       ENUM('open','reviewed','dismissed','escalated') NOT NULL DEFAULT 'open',
  reviewed_by  VARCHAR(30) NULL,
  reviewed_at  DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fraud_alerts_user (user_id),
  KEY idx_fraud_alerts_booking (booking_id),
  KEY idx_fraud_alerts_status (status),
  CONSTRAINT fk_fraud_alerts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_fraud_alerts_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 13) NEWSLETTER
-- =============================================================================

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        VARCHAR(30) NULL,
  email          VARCHAR(180) NOT NULL,
  full_name      VARCHAR(150) NULL,
  preferences    JSON NULL COMMENT 'Topics: events,travel,transport,deals',
  status         ENUM('subscribed','unsubscribed','bounced') NOT NULL DEFAULT 'subscribed',
  confirmed_at   DATETIME NULL,
  unsubscribed_at DATETIME NULL,
  token          VARCHAR(64) NOT NULL COMMENT 'For unsubscribe link',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_newsletter_email (email),
  KEY idx_newsletter_user (user_id),
  KEY idx_newsletter_status (status),
  CONSTRAINT fk_newsletter_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_campaigns (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject     VARCHAR(200) NOT NULL,
  body_html   LONGTEXT NULL,
  body_text   LONGTEXT NOT NULL,
  audience    ENUM('all','events','travel','transport','deals') NOT NULL DEFAULT 'all',
  status      ENUM('draft','scheduled','sending','sent') NOT NULL DEFAULT 'draft',
  sent_count  INT UNSIGNED NOT NULL DEFAULT 0,
  opened_count INT UNSIGNED NOT NULL DEFAULT 0,
  scheduled_at DATETIME NULL,
  sent_at     DATETIME NULL,
  created_by  VARCHAR(30) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_newsletter_campaigns_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 14) REFERRAL SYSTEM
-- =============================================================================

CREATE TABLE IF NOT EXISTS referral_codes (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         VARCHAR(30) NOT NULL,
  code            VARCHAR(20) NOT NULL,
  reward_type     ENUM('loyalty_points','discount','cash') NOT NULL DEFAULT 'loyalty_points',
  reward_value    DECIMAL(10,2) NOT NULL DEFAULT 100.00,
  uses_count      INT UNSIGNED NOT NULL DEFAULT 0,
  max_uses        INT UNSIGNED NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_referral_codes_code (code),
  UNIQUE KEY uq_referral_codes_user (user_id),
  CONSTRAINT fk_referral_codes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referral_uses (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referral_code_id BIGINT UNSIGNED NOT NULL,
  referred_user_id VARCHAR(30) NOT NULL,
  referrer_rewarded TINYINT(1) NOT NULL DEFAULT 0,
  referee_rewarded  TINYINT(1) NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_referral_uses_referee (referred_user_id),
  KEY idx_referral_uses_code (referral_code_id),
  CONSTRAINT fk_referral_uses_code FOREIGN KEY (referral_code_id) REFERENCES referral_codes(id) ON DELETE CASCADE,
  CONSTRAINT fk_referral_uses_user FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 15) LOYALTY POINTS
-- =============================================================================

CREATE TABLE IF NOT EXISTS loyalty_transactions (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      VARCHAR(30) NOT NULL,
  booking_id   VARCHAR(20) NULL,
  points       INT NOT NULL COMMENT 'Positive = earned, Negative = redeemed',
  balance_after INT UNSIGNED NOT NULL DEFAULT 0,
  reason       ENUM('booking','referral','review','signup','redemption','bonus','expiry','adjustment') NOT NULL DEFAULT 'booking',
  description  VARCHAR(255) NULL,
  expires_at   DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_loyalty_tx_user (user_id),
  KEY idx_loyalty_tx_booking (booking_id),
  CONSTRAINT fk_loyalty_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_tx_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 16) GIFT VOUCHERS
-- =============================================================================

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
  transaction_id   BIGINT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gift_vouchers_code (voucher_code),
  KEY idx_gift_vouchers_purchaser (purchased_by),
  KEY idx_gift_vouchers_status (status),
  CONSTRAINT fk_gift_vouchers_purchaser FOREIGN KEY (purchased_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_gift_vouchers_redeemer FOREIGN KEY (redeemed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_voucher_redemptions (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_id   BIGINT UNSIGNED NOT NULL,
  booking_id   VARCHAR(20) NULL,
  user_id      VARCHAR(30) NOT NULL,
  amount_used  DECIMAL(15,2) NOT NULL,
  redeemed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gvr_voucher (voucher_id),
  CONSTRAINT fk_gvr_voucher FOREIGN KEY (voucher_id) REFERENCES gift_vouchers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_gvr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 17) SPONSORED / FEATURED LISTINGS
-- =============================================================================

CREATE TABLE IF NOT EXISTS sponsored_listings (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id       VARCHAR(30) NOT NULL,
  listing_type    ENUM('event','property','tour','transport','local_business') NOT NULL,
  reference_id    VARCHAR(64) NOT NULL,
  listing_title   VARCHAR(220) NOT NULL,
  placement       ENUM('homepage','category_top','search_results','sidebar') NOT NULL DEFAULT 'homepage',
  budget_mwk      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  cost_per_click  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  impressions     INT UNSIGNED NOT NULL DEFAULT 0,
  clicks          INT UNSIGNED NOT NULL DEFAULT 0,
  spend           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  starts_at       DATETIME NOT NULL,
  ends_at         DATETIME NOT NULL,
  status          ENUM('pending','active','paused','ended','rejected') NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sponsored_listings_vendor (vendor_id),
  KEY idx_sponsored_listings_type (listing_type),
  KEY idx_sponsored_listings_dates (starts_at, ends_at),
  CONSTRAINT fk_sponsored_listings_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 18) LOCAL BUSINESS LISTINGS
-- =============================================================================

CREATE TABLE IF NOT EXISTS local_businesses (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id        VARCHAR(30) NULL,
  business_code    VARCHAR(32) NOT NULL,
  name             VARCHAR(200) NOT NULL,
  slug             VARCHAR(240) NOT NULL,
  business_type    ENUM('restaurant','cafe','tour_guide','car_hire','photographer','curio_shop','boat_operator','spa','gym','pharmacy','supermarket','other') NOT NULL DEFAULT 'other',
  description      TEXT NULL,
  tagline          VARCHAR(255) NULL,
  cover_image      VARCHAR(500) NULL,
  logo_url         VARCHAR(500) NULL,
  images           JSON NULL,
  address          VARCHAR(300) NULL,
  city             VARCHAR(120) NULL,
  latitude         DECIMAL(10,8) NULL,
  longitude        DECIMAL(11,8) NULL,
  phone            VARCHAR(30) NULL,
  whatsapp         VARCHAR(30) NULL,
  email            VARCHAR(180) NULL,
  website          VARCHAR(500) NULL,
  opening_hours    JSON NULL COMMENT 'Mon-Sun hours',
  services         JSON NULL COMMENT 'List of service strings',
  price_range      ENUM('budget','mid','premium','luxury') NULL,
  rating_average   DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  review_count     INT UNSIGNED NOT NULL DEFAULT 0,
  is_featured      TINYINT(1) NOT NULL DEFAULT 0,
  is_verified      TINYINT(1) NOT NULL DEFAULT 0,
  status           ENUM('pending','active','suspended','archived') NOT NULL DEFAULT 'pending',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at       DATETIME NULL,
  UNIQUE KEY uq_local_businesses_code (business_code),
  UNIQUE KEY uq_local_businesses_slug (slug),
  KEY idx_local_businesses_type (business_type),
  KEY idx_local_businesses_city (city),
  KEY idx_local_businesses_vendor (vendor_id),
  CONSTRAINT fk_local_businesses_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 19) AI CHAT / RECOMMENDATION LOGS
-- =============================================================================

CREATE TABLE IF NOT EXISTS ai_chat_sessions (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_code VARCHAR(64) NOT NULL,
  user_id      VARCHAR(30) NULL,
  ip_address   VARCHAR(45) NULL,
  messages     JSON NOT NULL DEFAULT (JSON_ARRAY()),
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ai_chat_sessions_code (session_code),
  KEY idx_ai_chat_sessions_user (user_id),
  CONSTRAINT fk_ai_chat_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 20) COLUMN EXTENSIONS (ALTER TABLE — additive only)
-- =============================================================================

-- users: 2FA flag, loyalty balance, referral code
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS two_factor_enabled  TINYINT(1) NOT NULL DEFAULT 0 AFTER must_change_password,
  ADD COLUMN IF NOT EXISTS loyalty_points      INT UNSIGNED NOT NULL DEFAULT 0 AFTER two_factor_enabled,
  ADD COLUMN IF NOT EXISTS referral_code       VARCHAR(20) NULL AFTER loyalty_points,
  ADD COLUMN IF NOT EXISTS push_notify         TINYINT(1) NOT NULL DEFAULT 1 AFTER referral_code,
  ADD COLUMN IF NOT EXISTS email_notify        TINYINT(1) NOT NULL DEFAULT 1 AFTER push_notify,
  ADD COLUMN IF NOT EXISTS sms_notify          TINYINT(1) NOT NULL DEFAULT 0 AFTER email_notify,
  ADD COLUMN IF NOT EXISTS login_alert_email   TINYINT(1) NOT NULL DEFAULT 1 AFTER sms_notify;

-- vendors: business type, verification badge, social links
ALTER TABLE vendors
  ADD COLUMN IF NOT EXISTS business_type      VARCHAR(60) NULL AFTER description,
  ADD COLUMN IF NOT EXISTS verification_badge TINYINT(1) NOT NULL DEFAULT 0 AFTER business_type,
  ADD COLUMN IF NOT EXISTS facebook_url       VARCHAR(500) NULL AFTER website_url,
  ADD COLUMN IF NOT EXISTS instagram_url      VARCHAR(500) NULL AFTER facebook_url,
  ADD COLUMN IF NOT EXISTS whatsapp           VARCHAR(30) NULL AFTER instagram_url,
  ADD COLUMN IF NOT EXISTS city               VARCHAR(120) NULL AFTER whatsapp,
  ADD COLUMN IF NOT EXISTS logo_url           VARCHAR(500) NULL AFTER city;

-- events: countdown visibility, seat selection flag (Omitted - events is a listing_type in unified listings table)
-- ALTER TABLE events
--   ADD COLUMN IF NOT EXISTS has_seat_selection TINYINT(1) NOT NULL DEFAULT 0 AFTER featured,
--   ADD COLUMN IF NOT EXISTS countdown_visible  TINYINT(1) NOT NULL DEFAULT 1 AFTER has_seat_selection,
--   ADD COLUMN IF NOT EXISTS video_url          VARCHAR(500) NULL AFTER poster_image_url;

-- ticket_pricing: add early bird & promo support (Omitted - ticket pricing is handled via ticket_types table)
-- ALTER TABLE ticket_pricing
--   ADD COLUMN IF NOT EXISTS early_bird_price DECIMAL(15,2) NULL AFTER price,
--   ADD COLUMN IF NOT EXISTS early_bird_ends  DATETIME NULL AFTER early_bird_price;

-- bookings: add payment gateway, promo, voucher columns
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS payment_gateway   VARCHAR(60) NULL AFTER payment_status,
  ADD COLUMN IF NOT EXISTS promo_code        VARCHAR(50) NULL AFTER payment_gateway,
  ADD COLUMN IF NOT EXISTS voucher_code      VARCHAR(32) NULL AFTER promo_code,
  ADD COLUMN IF NOT EXISTS loyalty_points_used INT UNSIGNED NOT NULL DEFAULT 0 AFTER voucher_code,
  ADD COLUMN IF NOT EXISTS loyalty_points_earned INT UNSIGNED NOT NULL DEFAULT 0 AFTER loyalty_points_used;

-- transactions: add gateway column
ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS gateway          VARCHAR(60) NULL COMMENT 'paychangu|airtel|tnm|visa|mastercard' AFTER gateway_name,
  ADD COLUMN IF NOT EXISTS gateway_ref      VARCHAR(150) NULL AFTER gateway,
  ADD COLUMN IF NOT EXISTS gateway_response JSON NULL AFTER gateway_ref;

-- properties: add instant booking flag, check-in/out policy (Omitted - properties is a listing_type in unified listings table)
-- ALTER TABLE properties
--   ADD COLUMN IF NOT EXISTS instant_booking  TINYINT(1) NOT NULL DEFAULT 0 AFTER featured,
--   ADD COLUMN IF NOT EXISTS wifi_available   TINYINT(1) NOT NULL DEFAULT 0 AFTER instant_booking,
--   ADD COLUMN IF NOT EXISTS parking          TINYINT(1) NOT NULL DEFAULT 0 AFTER wifi_available,
--   ADD COLUMN IF NOT EXISTS pool             TINYINT(1) NOT NULL DEFAULT 0 AFTER parking;

-- transport_providers: add airport transfer, car hire support (Omitted - transport providers are listings)
-- ALTER TABLE transport_providers
--   ADD COLUMN IF NOT EXISTS offers_airport_transfer TINYINT(1) NOT NULL DEFAULT 0 AFTER provider_type,
--   ADD COLUMN IF NOT EXISTS offers_car_rental       TINYINT(1) NOT NULL DEFAULT 0 AFTER offers_airport_transfer;

-- =============================================================================
-- 21) SEED DATA — Map Points (Malawi Attractions)
-- =============================================================================

INSERT IGNORE INTO map_points (name, point_type, latitude, longitude, city, address, description, is_featured, is_active) VALUES
('Lake Malawi', 'attraction', -13.9833, 34.7333, 'Mangochi', 'Lake Malawi National Park', 'UNESCO World Heritage Site, the third largest lake in Africa with crystal clear waters.', 1, 1),
('Zomba Plateau', 'attraction', -15.3667, 35.3167, 'Zomba', 'Zomba Plateau, Zomba', 'A stunning flat-topped mountain offering hiking trails and panoramic views.', 1, 1),
('Mount Mulanje', 'attraction', -15.9333, 35.6333, 'Mulanje', 'Mount Mulanje, Mulanje', 'The highest mountain in Central Africa, popular for trekking and climbing.', 1, 1),
('Liwonde National Park', 'attraction', -15.0167, 35.3333, 'Liwonde', 'Liwonde, Machinga', 'Malawi premier game reserve with elephants, hippos, and diverse birdlife.', 1, 1),
('Nkhotakota Wildlife Reserve', 'attraction', -12.9237, 34.2968, 'Nkhotakota', 'Nkhotakota', 'The largest wildlife reserve in Malawi with pristine forests.', 1, 1),
('Chileka International Airport', 'airport', -15.6794, 34.9739, 'Blantyre', 'Chileka, Blantyre', 'Main international airport serving Blantyre and southern Malawi.', 1, 1),
('Kamuzu International Airport', 'airport', -13.7887, 33.7808, 'Lilongwe', 'Lilongwe', 'Main international airport serving Lilongwe and central Malawi.', 1, 1),
('Blantyre Bus Terminus', 'transport', -15.7861, 35.0058, 'Blantyre', 'Blantyre City Centre', 'Main long-distance bus terminal in Blantyre.', 0, 1),
('Lilongwe Bus Terminal', 'transport', -13.9626, 33.7741, 'Lilongwe', 'Lilongwe City Centre', 'Main bus terminal in the capital city.', 0, 1),
('Queens Elizabeth Central Hospital', 'hospital', -15.7845, 35.0085, 'Blantyre', 'Blantyre', 'Main referral hospital in Blantyre.', 0, 1),
('Kamuzu Central Hospital', 'hospital', -13.9688, 33.7832, 'Lilongwe', 'Lilongwe', 'Main referral hospital in Lilongwe.', 0, 1);

-- =============================================================================
-- 22) SEED DATA — Destination Guides
-- =============================================================================

INSERT IGNORE INTO destination_guides (city, title, slug, summary, content, cover_image, best_time, travel_tips, is_featured, is_active) VALUES
('Blantyre',
 'Blantyre Travel Guide',
 'blantyre-travel-guide',
 'Discover Blantyre, Malawi''s commercial capital and a gateway to southern adventures.',
 '<p>Blantyre is the commercial heart of Malawi and a vibrant city with colonial history, bustling markets, and easy access to some of the country''s most spectacular natural attractions.</p><h3>Getting Around</h3><p>Minibuses (matola) are the most common form of transport. Taxis are available but negotiate fares upfront. Ride-sharing through Mbanda is increasingly popular.</p><h3>Where to Eat</h3><p>The city offers a range of restaurants from local nsima joints to international cuisine. The Shoprite area has several good options.</p><h3>Day Trips</h3><p>Zomba Plateau (1 hour), Mount Mulanje (1.5 hours), and Majete Wildlife Reserve (2 hours) are all excellent day trip destinations.</p>',
 'https://images.unsplash.com/photo-1612892483236-52d32a0e0ac1?w=1200&fit=crop&q=80',
 'May to October (dry season)',
 JSON_ARRAY('Always carry small change for matola rides', 'Drink bottled or purified water', 'Respect local customs especially in rural areas', 'Mosquito repellent is essential', 'The local currency is Malawi Kwacha (MWK)'),
 1, 1),
('Lilongwe',
 'Lilongwe City Guide',
 'lilongwe-city-guide',
 'Explore Lilongwe, Malawi''s modern capital city with its unique split between Old Town and new City Centre.',
 '<p>Lilongwe is Malawi''s political capital and has a fascinating dual character — the bustling, chaotic Old Town market area contrasts with the planned, spacious City Centre with its wide boulevards and government buildings.</p><h3>Must Visit</h3><p>The Lilongwe Wildlife Centre rehabilitates primates and other wildlife. The Old Town Market is vibrant and authentic. Area 10 has excellent restaurants and nightlife.</p><h3>Nature</h3><p>Dzalanyama Forest Reserve is just 45 minutes from the city centre and offers hiking and birdwatching.</p>',
 'https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=1200&fit=crop&q=80',
 'April to October',
 JSON_ARRAY('The city is spread out — a taxi or Mbanda is recommended for longer distances', 'Area 47 and Area 10 have good supermarkets', 'Fridays are busy with week-end travelers heading to the lake', 'Keep valuables secured in Old Town market'),
 1, 1),
('Mangochi',
 'Lake Malawi & Mangochi Guide',
 'lake-malawi-mangochi-guide',
 'The jewel of Malawi — crystal clear freshwater lake with white sandy beaches and excellent water sports.',
 '<p>Lake Malawi, called the "Lake of Stars", stretches along Malawi''s eastern border and is one of Africa''s most spectacular lakes. The Mangochi area offers some of the best beach resorts and water sports.</p><h3>Activities</h3><p>Snorkeling and diving to see the famous cichlid fish, kayaking, sailing, and beach relaxation are the main draws. Many resorts offer boat trips to nearby islands.</p><h3>Accommodation</h3><p>Options range from budget backpacker lodges to luxury beach resorts. Senga Bay (near Salima) is also popular for its beaches.</p>',
 'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=1200&fit=crop&q=80',
 'May to November',
 JSON_ARRAY('Bilharzia risk is low on sandy beaches but avoid reedy areas', 'Sunscreen is essential — the sun reflects off the lake', 'Fish and chips made with fresh chambo (Lake Malawi fish) is a must-try', 'Book accommodation in advance during December-January peak season'),
 1, 1);

-- =============================================================================
-- 23) SEED DATA — System Settings for New Features
-- =============================================================================

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key         VARCHAR(120) NOT NULL PRIMARY KEY,
  setting_value       LONGTEXT NOT NULL,
  value_type          ENUM('string','number','boolean','json') NOT NULL DEFAULT 'string',
  description         VARCHAR(255) NULL,
  updated_by          VARCHAR(30) NULL,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_system_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value, value_type, description) VALUES
('ai_provider', 'gemini', 'string', 'AI provider: gemini, openai, or none'),
('ai_api_key', '', 'string', 'AI provider API key (store in .env for production)'),
('gemini_model', 'gemini-1.5-flash', 'string', 'Gemini model name'),
('weather_provider', 'open-meteo', 'string', 'Weather provider: open-meteo or openweathermap'),
('openweathermap_key', '', 'string', 'OpenWeatherMap API key if used'),
('map_provider', 'leaflet', 'string', 'Map provider: leaflet (OSM) or google'),
('google_maps_key', '', 'string', 'Google Maps API key if used'),
('paychangu_public_key', '', 'string', 'PayChangu public key'),
('paychangu_secret_key', '', 'string', 'PayChangu secret key'),
('airtel_money_api_key', '', 'string', 'Airtel Money API key'),
('tnm_mpamba_api_key', '', 'string', 'TNM Mpamba API key'),
('loyalty_points_per_mwk', '1', 'number', 'Loyalty points earned per 1 MWK spent'),
('loyalty_points_redemption_rate', '100', 'number', 'Points needed to redeem MK 1'),
('referral_points_reward', '500', 'number', 'Points awarded for successful referral'),
('newsletter_enabled', '1', 'boolean', 'Enable newsletter subscriptions'),
('2fa_enabled', '1', 'boolean', 'Allow users to enable 2FA'),
('sponsored_listings_enabled', '1', 'boolean', 'Enable sponsored listings feature');

SET FOREIGN_KEY_CHECKS = 1;
