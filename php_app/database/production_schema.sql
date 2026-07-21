-- =============================================================================
-- Uthenga Marketplace Production Schema
-- MySQL 8.0+
-- Supports marketplace, booking, ticketing, transport, payments, analytics,
-- vendor approval, notifications, WhatsApp requests, and admin auditing.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1) LOCATION AND MAPS
-- =============================================================================

CREATE TABLE countries (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(120) NOT NULL,
  iso2_code    CHAR(2) NOT NULL,
  iso3_code    CHAR(3) NULL,
  phone_code   VARCHAR(12) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME NULL,
  UNIQUE KEY uq_countries_name (name),
  UNIQUE KEY uq_countries_iso2 (iso2_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cities (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_id   BIGINT UNSIGNED NOT NULL,
  name         VARCHAR(120) NOT NULL,
  latitude     DECIMAL(10,8) NULL,
  longitude    DECIMAL(11,8) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME NULL,
  UNIQUE KEY uq_cities_country_name (country_id, name),
  KEY idx_cities_country (country_id),
  CONSTRAINT fk_cities_country FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE locations (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_id         BIGINT UNSIGNED NOT NULL,
  city_id            BIGINT UNSIGNED NOT NULL,
  label              VARCHAR(150) NOT NULL,
  address_line1      VARCHAR(255) NOT NULL,
  address_line2      VARCHAR(255) NULL,
  postal_code        VARCHAR(30) NULL,
  latitude           DECIMAL(10,8) NULL,
  longitude          DECIMAL(11,8) NULL,
  google_place_id    VARCHAR(255) NULL,
  osm_place_id       VARCHAR(255) NULL,
  map_provider       ENUM('google','osm','manual') NOT NULL DEFAULT 'manual',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  UNIQUE KEY uq_locations_google_place (google_place_id),
  KEY idx_locations_city (city_id),
  KEY idx_locations_country (country_id),
  CONSTRAINT fk_locations_country FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE RESTRICT,
  CONSTRAINT fk_locations_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic map records for Google Maps now, OSM later.
CREATE TABLE event_maps (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id           BIGINT UNSIGNED NOT NULL,
  location_id        BIGINT UNSIGNED NOT NULL,
  venue_name         VARCHAR(200) NULL,
  google_map_url     VARCHAR(500) NULL,
  osm_map_url        VARCHAR(500) NULL,
  map_embed_url      VARCHAR(500) NULL,
  zoom_level         TINYINT UNSIGNED NOT NULL DEFAULT 15,
  path_geometry      JSON NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_maps_event_location (event_id, location_id),
  KEY idx_event_maps_location (location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_maps (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id        BIGINT UNSIGNED NOT NULL,
  location_id        BIGINT UNSIGNED NOT NULL,
  venue_name         VARCHAR(200) NULL,
  google_map_url     VARCHAR(500) NULL,
  osm_map_url        VARCHAR(500) NULL,
  map_embed_url      VARCHAR(500) NULL,
  zoom_level         TINYINT UNSIGNED NOT NULL DEFAULT 15,
  path_geometry      JSON NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_property_maps_property_location (property_id, location_id),
  KEY idx_property_maps_location (location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE route_maps (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_id           BIGINT UNSIGNED NOT NULL,
  origin_location_id BIGINT UNSIGNED NOT NULL,
  destination_location_id BIGINT UNSIGNED NOT NULL,
  path_geometry      JSON NULL,
  distance_km        DECIMAL(10,2) NULL,
  duration_minutes   INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_route_maps_route (route_id),
  KEY idx_route_maps_origin (origin_location_id),
  KEY idx_route_maps_destination (destination_location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2) AUTHENTICATION AND ROLES
-- =============================================================================

CREATE TABLE roles (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(60) NOT NULL,
  description VARCHAR(255) NULL,
  is_system   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at  DATETIME NULL,
  UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  permission_key VARCHAR(120) NOT NULL,
  label         VARCHAR(180) NOT NULL,
  module        VARCHAR(120) NOT NULL,
  description   VARCHAR(255) NULL,
  is_system     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    DATETIME NULL,
  UNIQUE KEY uq_permissions_key (permission_key),
  KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_code           VARCHAR(32) NOT NULL,
  name                VARCHAR(150) NOT NULL,
  full_name           VARCHAR(150) GENERATED ALWAYS AS (name) STORED,
  email               VARCHAR(180) NOT NULL,
  phone               VARCHAR(30) NULL,
  password_hash       VARCHAR(255) NULL,
  avatar              VARCHAR(500) NULL,
  avatar_url          VARCHAR(500) GENERATED ALWAYS AS (avatar) STORED,
  role                ENUM('Super Administrator','Administrator','Vendor','Event Organizer','Hotel/Lodge Manager','Tour Operator','Transport Provider','Customer') NOT NULL DEFAULT 'Customer',
  is_approved         TINYINT(1) NOT NULL DEFAULT 1,
  balance             DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  must_change_pw      TINYINT(1) NOT NULL DEFAULT 0,
  account_status      ENUM('active','pending','blocked','suspended','deleted') NOT NULL DEFAULT 'active',
  email_verified_at   DATETIME NULL,
  phone_verified_at   DATETIME NULL,
  last_login_at       DATETIME NULL,
  must_change_password TINYINT(1) GENERATED ALWAYS AS (must_change_pw) STORED,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,
  UNIQUE KEY uq_users_code (user_code),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone),
  KEY idx_users_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
  user_id     BIGINT UNSIGNED NOT NULL,
  role_id     BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id       BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  assigned_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NULL,
  session_token   VARCHAR(128) NOT NULL,
  ip_address      VARCHAR(45) NULL,
  user_agent      VARCHAR(500) NULL,
  last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at      DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_sessions_token (session_token),
  KEY idx_user_sessions_user (user_id),
  CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
  reset_token_hash VARCHAR(255) NOT NULL,
  expires_at      DATETIME NOT NULL,
  used_at         DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_password_resets_token (reset_token_hash),
  KEY idx_password_resets_user (user_id),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE social_accounts (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id            BIGINT UNSIGNED NOT NULL,
  provider           ENUM('google','facebook','microsoft') NOT NULL,
  provider_user_id   VARCHAR(255) NOT NULL,
  provider_email     VARCHAR(180) NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_social_provider_user (provider, provider_user_id),
  KEY idx_social_user (user_id),
  CONSTRAINT fk_social_accounts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_permissions (
  user_id       BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  permissions   JSON NOT NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3) VENDOR MANAGEMENT
-- =============================================================================

CREATE TABLE vendors (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_code         VARCHAR(32) NOT NULL,
  user_id             BIGINT UNSIGNED NOT NULL,
  business_name       VARCHAR(180) NOT NULL,
  display_name        VARCHAR(180) NULL,
  description         TEXT NULL,
  business_email      VARCHAR(180) NULL,
  business_phone      VARCHAR(30) NULL,
  website_url         VARCHAR(500) NULL,
  payout_email        VARCHAR(180) NULL,
  status              ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  approved_at         DATETIME NULL,
  rejected_at         DATETIME NULL,
  suspended_at        DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,
  UNIQUE KEY uq_vendors_code (vendor_code),
  UNIQUE KEY uq_vendors_user (user_id),
  KEY idx_vendors_status (status),
  CONSTRAINT fk_vendors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendor_categories (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id       BIGINT UNSIGNED NOT NULL,
  category_name   VARCHAR(120) NOT NULL,
  is_primary      TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vendor_category (vendor_id, category_name),
  KEY idx_vendor_categories_vendor (vendor_id),
  CONSTRAINT fk_vendor_categories_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendor_documents (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id       BIGINT UNSIGNED NOT NULL,
  document_type   VARCHAR(120) NOT NULL,
  document_number VARCHAR(150) NULL,
  file_url        VARCHAR(500) NOT NULL,
  verification_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by     BIGINT UNSIGNED NULL,
  reviewed_at     DATETIME NULL,
  notes           TEXT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_vendor_documents_vendor (vendor_id),
  CONSTRAINT fk_vendor_documents_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  CONSTRAINT fk_vendor_documents_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendor_verification_logs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id       BIGINT UNSIGNED NOT NULL,
  reviewed_by     BIGINT UNSIGNED NOT NULL,
  previous_status ENUM('pending','approved','rejected','suspended') NOT NULL,
  new_status      ENUM('pending','approved','rejected','suspended') NOT NULL,
  action_notes    TEXT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_vendor_verification_vendor (vendor_id),
  CONSTRAINT fk_vendor_verification_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  CONSTRAINT fk_vendor_verification_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendor_wallets (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id       BIGINT UNSIGNED NOT NULL,
  currency        CHAR(3) NOT NULL DEFAULT 'MWK',
  balance         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  pending_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vendor_wallet_vendor (vendor_id),
  CONSTRAINT fk_vendor_wallet_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE merchant_accounts (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id          BIGINT UNSIGNED NULL,
  account_name       VARCHAR(150) NOT NULL,
  provider_name      VARCHAR(100) NOT NULL,
  account_identifier VARCHAR(150) NOT NULL,
  account_type       VARCHAR(60) NULL,
  currency           CHAR(3) NOT NULL DEFAULT 'MWK',
  is_primary         TINYINT(1) NOT NULL DEFAULT 0,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_merchant_accounts_vendor (vendor_id),
  CONSTRAINT fk_merchant_accounts_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendor_payouts (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id       BIGINT UNSIGNED NOT NULL,
  wallet_id       BIGINT UNSIGNED NULL,
  amount          DECIMAL(15,2) NOT NULL,
  charges         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  currency        CHAR(3) NOT NULL DEFAULT 'MWK',
  status          ENUM('pending','processed','failed','reversed') NOT NULL DEFAULT 'pending',
  payout_method   VARCHAR(100) NULL,
  transaction_reference VARCHAR(150) NULL,
  processed_at    DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_vendor_payouts_vendor (vendor_id),
  KEY idx_vendor_payouts_status (status),
  CONSTRAINT fk_vendor_payouts_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  CONSTRAINT fk_vendor_payouts_wallet FOREIGN KEY (wallet_id) REFERENCES vendor_wallets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE withdrawal_requests (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_reference VARCHAR(150) NULL,
  vendor_id       BIGINT UNSIGNED NOT NULL,
  wallet_id       BIGINT UNSIGNED NULL,
  amount          DECIMAL(15,2) NOT NULL,
  charges         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  currency        CHAR(3) NOT NULL DEFAULT 'MWK',
  request_method  VARCHAR(100) NOT NULL,
  destination     VARCHAR(255) NULL,
  status          ENUM('pending','approved','rejected','processed') NOT NULL DEFAULT 'pending',
  reviewed_by     BIGINT UNSIGNED NULL,
  reviewed_at     DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_withdrawals_vendor (vendor_id),
  KEY idx_withdrawals_status (status),
  CONSTRAINT fk_withdrawals_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  CONSTRAINT fk_withdrawals_wallet FOREIGN KEY (wallet_id) REFERENCES vendor_wallets(id) ON DELETE SET NULL,
  CONSTRAINT fk_withdrawals_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4) EVENTS, TICKETING, AND ADVERTISING
-- =============================================================================

CREATE TABLE event_categories (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  description TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at  DATETIME NULL,
  UNIQUE KEY uq_event_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_code          VARCHAR(32) NOT NULL,
  vendor_id           BIGINT UNSIGNED NOT NULL,
  category_id         BIGINT UNSIGNED NOT NULL,
  title               VARCHAR(220) NOT NULL,
  slug                VARCHAR(240) NOT NULL,
  description         LONGTEXT NOT NULL,
  short_description   VARCHAR(500) NULL,
  event_mode          ENUM('physical','online','hybrid') NOT NULL DEFAULT 'physical',
  timezone            VARCHAR(80) NOT NULL DEFAULT 'Africa/Blantyre',
  status              ENUM('draft','pending_review','published','cancelled','completed','archived') NOT NULL DEFAULT 'draft',
  featured            TINYINT(1) NOT NULL DEFAULT 0,
  total_capacity      INT UNSIGNED NULL,
  age_limit           TINYINT UNSIGNED NULL,
  poster_image_url    VARCHAR(500) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,
  UNIQUE KEY uq_events_code (event_code),
  UNIQUE KEY uq_events_slug (slug),
  KEY idx_events_vendor (vendor_id),
  KEY idx_events_category (category_id),
  KEY idx_events_status (status),
  CONSTRAINT fk_events_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  CONSTRAINT fk_events_category FOREIGN KEY (category_id) REFERENCES event_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_locations (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id        BIGINT UNSIGNED NOT NULL,
  location_id     BIGINT UNSIGNED NOT NULL,
  venue_name      VARCHAR(200) NOT NULL,
  venue_notes     VARCHAR(500) NULL,
  is_primary      TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_locations (event_id, location_id),
  KEY idx_event_locations_location (location_id),
  CONSTRAINT fk_event_locations_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_event_locations_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_images (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id    BIGINT UNSIGNED NOT NULL,
  image_url   VARCHAR(500) NOT NULL,
  alt_text    VARCHAR(255) NULL,
  sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_event_images_event (event_id),
  CONSTRAINT fk_event_images_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_schedules (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id        BIGINT UNSIGNED NOT NULL,
  starts_at       DATETIME NOT NULL,
  ends_at         DATETIME NOT NULL,
  timezone        VARCHAR(80) NOT NULL DEFAULT 'Africa/Blantyre',
  schedule_label  VARCHAR(120) NULL,
  capacity        INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_event_schedules_event (event_id),
  CONSTRAINT fk_event_schedules_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_organizers (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id        BIGINT UNSIGNED NOT NULL,
  name            VARCHAR(150) NOT NULL,
  email           VARCHAR(180) NULL,
  phone           VARCHAR(30) NULL,
  organization    VARCHAR(180) NULL,
  is_primary      TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_event_organizers_event (event_id),
  CONSTRAINT fk_event_organizers_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE featured_events (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id        BIGINT UNSIGNED NOT NULL,
  placement       VARCHAR(60) NOT NULL DEFAULT 'homepage',
  starts_at       DATETIME NOT NULL,
  ends_at         DATETIME NOT NULL,
  display_order   INT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_featured_events_event (event_id),
  CONSTRAINT fk_featured_events_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_ads (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id        BIGINT UNSIGNED NOT NULL,
  ad_name         VARCHAR(150) NOT NULL,
  placement       VARCHAR(60) NOT NULL,
  start_date      DATE NOT NULL,
  end_date        DATE NOT NULL,
  clicks          INT UNSIGNED NOT NULL DEFAULT 0,
  impressions     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_event_ads_event (event_id),
  CONSTRAINT fk_event_ads_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_types (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id            BIGINT UNSIGNED NOT NULL,
  name                VARCHAR(80) NOT NULL,
  description         VARCHAR(255) NULL,
  total_quantity      INT UNSIGNED NOT NULL,
  sold_quantity       INT UNSIGNED NOT NULL DEFAULT 0,
  min_per_order       INT UNSIGNED NOT NULL DEFAULT 1,
  max_per_order       INT UNSIGNED NULL,
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ticket_types_event_name (event_id, name),
  KEY idx_ticket_types_event (event_id),
  CONSTRAINT fk_ticket_types_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_pricing (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_type_id  BIGINT UNSIGNED NOT NULL,
  price           DECIMAL(15,2) NOT NULL,
  currency        CHAR(3) NOT NULL DEFAULT 'MWK',
  valid_from      DATETIME NULL,
  valid_to        DATETIME NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ticket_pricing_type (ticket_type_id),
  CONSTRAINT fk_ticket_pricing_type FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code        VARCHAR(32) NOT NULL,
  customer_id         BIGINT UNSIGNED NOT NULL,
  booking_channel     ENUM('web','whatsapp','admin','vendor') NOT NULL DEFAULT 'web',
  booking_status      ENUM('pending','confirmed','cancelled','completed','refunded','expired') NOT NULL DEFAULT 'pending',
  payment_status      ENUM('pending','authorized','paid','failed','refunded','partially_paid') NOT NULL DEFAULT 'pending',
  currency            CHAR(3) NOT NULL DEFAULT 'MWK',
  total_amount        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  discount_amount     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  tax_amount          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  commission_amount   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  grand_total         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  reference_name      VARCHAR(150) NULL,
  customer_notes      TEXT NULL,
  vendor_notes        TEXT NULL,
  booked_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_at        DATETIME NULL,
  cancelled_at        DATETIME NULL,
  completed_at        DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,
  UNIQUE KEY uq_bookings_code (booking_code),
  KEY idx_bookings_customer (customer_id),
  KEY idx_bookings_status (booking_status),
  KEY idx_bookings_payment (payment_status),
  CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_items (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id          BIGINT UNSIGNED NOT NULL,
  vendor_id           BIGINT UNSIGNED NULL,
  item_type           ENUM('event_ticket','property_room','transport_seat','tour_package','vendor_service') NOT NULL,
  reference_id        VARCHAR(64) NOT NULL,
  item_name           VARCHAR(255) NOT NULL,
  quantity            INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price          DECIMAL(15,2) NOT NULL,
  subtotal            DECIMAL(15,2) NOT NULL,
  service_date        DATE NULL,
  metadata            JSON NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_booking_items_booking (booking_id),
  KEY idx_booking_items_vendor (vendor_id),
  KEY idx_booking_items_type (item_type),
  KEY idx_booking_items_reference (reference_id),
  CONSTRAINT fk_booking_items_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_items_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_status_history (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id          BIGINT UNSIGNED NOT NULL,
  old_status          VARCHAR(50) NOT NULL,
  new_status          VARCHAR(50) NOT NULL,
  changed_by          BIGINT UNSIGNED NULL,
  notes               TEXT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_booking_status_history_booking (booking_id),
  CONSTRAINT fk_booking_status_history_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_status_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_notes (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id          BIGINT UNSIGNED NOT NULL,
  author_id           BIGINT UNSIGNED NULL,
  note                TEXT NOT NULL,
  visibility          ENUM('internal','customer','vendor') NOT NULL DEFAULT 'internal',
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_booking_notes_booking (booking_id),
  CONSTRAINT fk_booking_notes_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_notes_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_sales (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id          BIGINT UNSIGNED NULL,
  ticket_type_id      BIGINT UNSIGNED NOT NULL,
  buyer_id            BIGINT UNSIGNED NOT NULL,
  transaction_id      BIGINT UNSIGNED NULL,
  quantity            INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price          DECIMAL(15,2) NOT NULL,
  subtotal            DECIMAL(15,2) NOT NULL,
  discount_amount     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_amount        DECIMAL(15,2) NOT NULL,
  sale_status         ENUM('pending','paid','cancelled','refunded') NOT NULL DEFAULT 'pending',
  sold_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket_sales_booking (booking_id),
  KEY idx_ticket_sales_buyer (buyer_id),
  KEY idx_ticket_sales_type (ticket_type_id),
  KEY idx_ticket_sales_transaction (transaction_id),
  CONSTRAINT fk_ticket_sales_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_sales_type FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ticket_sales_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tickets (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_sale_id      BIGINT UNSIGNED NOT NULL,
  ticket_code         VARCHAR(32) NOT NULL,
  qr_token            VARCHAR(255) NOT NULL,
  owner_user_id       BIGINT UNSIGNED NULL,
  holder_name         VARCHAR(150) NOT NULL,
  holder_email        VARCHAR(180) NULL,
  status              ENUM('issued','active','used','cancelled','refunded','expired') NOT NULL DEFAULT 'issued',
  issued_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  redeemed_at         DATETIME NULL,
  deleted_at          DATETIME NULL,
  UNIQUE KEY uq_tickets_code (ticket_code),
  UNIQUE KEY uq_tickets_qr_token (qr_token),
  KEY idx_tickets_sale (ticket_sale_id),
  KEY idx_tickets_owner (owner_user_id),
  CONSTRAINT fk_tickets_sale FOREIGN KEY (ticket_sale_id) REFERENCES ticket_sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_validations (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id           BIGINT UNSIGNED NOT NULL,
  validated_by        BIGINT UNSIGNED NOT NULL,
  validation_result   ENUM('valid','invalid','duplicate','revoked') NOT NULL,
  notes               VARCHAR(500) NULL,
  validated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket_validations_ticket (ticket_id),
  CONSTRAINT fk_ticket_validations_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_validations_user FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_scans (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id           BIGINT UNSIGNED NOT NULL,
  scanned_by          BIGINT UNSIGNED NULL,
  scan_result         ENUM('valid','invalid','duplicate') NOT NULL,
  device_info         VARCHAR(500) NULL,
  scanned_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket_scans_ticket (ticket_id),
  CONSTRAINT fk_ticket_scans_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_scans_user FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE advertisements (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title               VARCHAR(180) NOT NULL,
  ad_type             ENUM('banner','popup','sidebar','hero','sponsored') NOT NULL DEFAULT 'banner',
  target_type         VARCHAR(60) NULL,
  target_reference_id VARCHAR(64) NULL,
  image_url           VARCHAR(500) NOT NULL,
  link_url            VARCHAR(500) NULL,
  start_date          DATE NOT NULL,
  end_date            DATE NOT NULL,
  status              ENUM('draft','active','paused','ended') NOT NULL DEFAULT 'draft',
  clicks              INT UNSIGNED NOT NULL DEFAULT 0,
  impressions         INT UNSIGNED NOT NULL DEFAULT 0,
  created_by          BIGINT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,
  KEY idx_advertisements_dates (start_date, end_date),
  KEY idx_advertisements_status (status),
  CONSTRAINT fk_advertisements_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sponsored_events (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id            BIGINT UNSIGNED NOT NULL,
  advertisement_id    BIGINT UNSIGNED NOT NULL,
  starts_at           DATETIME NOT NULL,
  ends_at             DATETIME NOT NULL,
  priority            INT UNSIGNED NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sponsored_events (event_id, advertisement_id),
  CONSTRAINT fk_sponsored_events_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_sponsored_events_ad FOREIGN KEY (advertisement_id) REFERENCES advertisements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE banner_slides (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title               VARCHAR(150) NOT NULL,
  subtitle            VARCHAR(255) NULL,
  image_url           VARCHAR(500) NOT NULL,
  link_url            VARCHAR(500) NULL,
  sort_order          INT UNSIGNED NOT NULL DEFAULT 0,
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE homepage_campaigns (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_code       VARCHAR(32) NOT NULL,
  name                VARCHAR(150) NOT NULL,
  objective           VARCHAR(255) NULL,
  budget              DECIMAL(15,2) NULL,
  start_date          DATE NOT NULL,
  end_date            DATE NOT NULL,
  campaign_meta       JSON NULL,
  status              ENUM('draft','scheduled','active','ended') NOT NULL DEFAULT 'draft',
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_homepage_campaigns_code (campaign_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5) STAY BOOKING
-- =============================================================================

CREATE TABLE property_categories (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  description TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at  DATETIME NULL,
  UNIQUE KEY uq_property_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE properties (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_code       VARCHAR(32) NOT NULL,
  vendor_id           BIGINT UNSIGNED NOT NULL,
  category_id         BIGINT UNSIGNED NOT NULL,
  title               VARCHAR(220) NOT NULL,
  slug                VARCHAR(240) NOT NULL,
  description         LONGTEXT NOT NULL,
  check_in_time       TIME NULL,
  check_out_time      TIME NULL,
  total_rooms         INT UNSIGNED NULL,
  base_price          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  currency            CHAR(3) NOT NULL DEFAULT 'MWK',
  rating_average      DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  review_count        INT UNSIGNED NOT NULL DEFAULT 0,
  status              ENUM('draft','pending_review','published','suspended','archived') NOT NULL DEFAULT 'draft',
  featured            TINYINT(1) NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,
  UNIQUE KEY uq_properties_code (property_code),
  UNIQUE KEY uq_properties_slug (slug),
  KEY idx_properties_vendor (vendor_id),
  KEY idx_properties_category (category_id),
  CONSTRAINT fk_properties_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  CONSTRAINT fk_properties_category FOREIGN KEY (category_id) REFERENCES property_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_locations (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id     BIGINT UNSIGNED NOT NULL,
  location_id     BIGINT UNSIGNED NOT NULL,
  is_primary      TINYINT(1) NOT NULL DEFAULT 1,
  directions_note VARCHAR(500) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_property_locations (property_id, location_id),
  CONSTRAINT fk_property_locations_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_property_locations_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_images (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id     BIGINT UNSIGNED NOT NULL,
  image_url       VARCHAR(500) NOT NULL,
  alt_text        VARCHAR(255) NULL,
  sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_property_images_property (property_id),
  CONSTRAINT fk_property_images_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_rooms (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id     BIGINT UNSIGNED NOT NULL,
  room_name       VARCHAR(150) NOT NULL,
  room_type       VARCHAR(120) NOT NULL,
  description     VARCHAR(500) NULL,
  price_per_night DECIMAL(15,2) NOT NULL,
  currency        CHAR(3) NOT NULL DEFAULT 'MWK',
  max_occupancy   INT UNSIGNED NOT NULL DEFAULT 2,
  total_units     INT UNSIGNED NOT NULL DEFAULT 1,
  amenities       JSON NULL,
  status          ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_property_rooms_property (property_id),
  CONSTRAINT fk_property_rooms_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_availability (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_room_id BIGINT UNSIGNED NOT NULL,
  stay_date       DATE NOT NULL,
  total_units     INT UNSIGNED NOT NULL,
  available_units INT UNSIGNED NOT NULL,
  blocked_units   INT UNSIGNED NOT NULL DEFAULT 0,
  rate_override   DECIMAL(15,2) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_room_availability (property_room_id, stay_date),
  CONSTRAINT fk_room_availability_room FOREIGN KEY (property_room_id) REFERENCES property_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_reviews (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id     BIGINT UNSIGNED NOT NULL,
  user_id         BIGINT UNSIGNED NOT NULL,
  booking_id      BIGINT UNSIGNED NULL,
  rating          TINYINT UNSIGNED NOT NULL,
  review_title    VARCHAR(150) NULL,
  comment         TEXT NOT NULL,
  status          ENUM('pending','published','hidden') NOT NULL DEFAULT 'published',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (rating BETWEEN 1 AND 5),
  KEY idx_property_reviews_property (property_id),
  KEY idx_property_reviews_user (user_id),
  CONSTRAINT fk_property_reviews_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_property_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_property_reviews_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6) TRANSPORT
-- =============================================================================

CREATE TABLE transport_providers (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id       BIGINT UNSIGNED NOT NULL,
  provider_code   VARCHAR(32) NOT NULL,
  name            VARCHAR(180) NOT NULL,
  provider_type   ENUM('bus','taxi','shuttle','coach') NOT NULL,
  status          ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL,
  UNIQUE KEY uq_transport_providers_code (provider_code),
  UNIQUE KEY uq_transport_providers_vendor (vendor_id),
  CONSTRAINT fk_transport_providers_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE routes (
  id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id             BIGINT UNSIGNED NOT NULL,
  route_code              VARCHAR(32) NOT NULL,
  origin_location_id      BIGINT UNSIGNED NOT NULL,
  destination_location_id BIGINT UNSIGNED NOT NULL,
  route_name              VARCHAR(180) NOT NULL,
  distance_km             DECIMAL(10,2) NULL,
  estimated_duration_min  INT UNSIGNED NULL,
  base_fare               DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  currency                CHAR(3) NOT NULL DEFAULT 'MWK',
  status                  ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at              DATETIME NULL,
  UNIQUE KEY uq_routes_code (route_code),
  KEY idx_routes_provider (provider_id),
  CONSTRAINT fk_routes_provider FOREIGN KEY (provider_id) REFERENCES transport_providers(id) ON DELETE CASCADE,
  CONSTRAINT fk_routes_origin FOREIGN KEY (origin_location_id) REFERENCES locations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_routes_destination FOREIGN KEY (destination_location_id) REFERENCES locations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicles (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id     BIGINT UNSIGNED NOT NULL,
  route_id        BIGINT UNSIGNED NULL,
  vehicle_code    VARCHAR(32) NOT NULL,
  vehicle_type    ENUM('bus','minibus','coach','taxi','car') NOT NULL,
  plate_number    VARCHAR(30) NOT NULL,
  model           VARCHAR(120) NULL,
  seat_capacity   INT UNSIGNED NOT NULL,
  status          ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL,
  UNIQUE KEY uq_vehicles_code (vehicle_code),
  UNIQUE KEY uq_vehicles_plate (plate_number),
  KEY idx_vehicles_provider (provider_id),
  KEY idx_vehicles_route (route_id),
  CONSTRAINT fk_vehicles_provider FOREIGN KEY (provider_id) REFERENCES transport_providers(id) ON DELETE CASCADE,
  CONSTRAINT fk_vehicles_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schedules (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_id            BIGINT UNSIGNED NOT NULL,
  vehicle_id          BIGINT UNSIGNED NOT NULL,
  departure_at        DATETIME NOT NULL,
  arrival_at          DATETIME NOT NULL,
  seat_price          DECIMAL(15,2) NOT NULL,
  currency            CHAR(3) NOT NULL DEFAULT 'MWK',
  seats_total         INT UNSIGNED NOT NULL,
  seats_available     INT UNSIGNED NOT NULL,
  status              ENUM('scheduled','departed','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,
  KEY idx_schedules_route (route_id),
  KEY idx_schedules_vehicle (vehicle_id),
  CONSTRAINT fk_schedules_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_schedules_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seat_allocations (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schedule_id     BIGINT UNSIGNED NOT NULL,
  seat_number     VARCHAR(10) NOT NULL,
  seat_class      VARCHAR(50) NULL,
  allocation_status ENUM('available','reserved','booked','blocked') NOT NULL DEFAULT 'available',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seat_allocations (schedule_id, seat_number),
  CONSTRAINT fk_seat_allocations_schedule FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transport_bookings (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id          BIGINT UNSIGNED NULL,
  schedule_id         BIGINT UNSIGNED NOT NULL,
  seat_allocation_id  BIGINT UNSIGNED NOT NULL,
  passenger_name      VARCHAR(150) NOT NULL,
  passenger_phone     VARCHAR(30) NULL,
  passenger_email     VARCHAR(180) NULL,
  travel_date         DATE NOT NULL,
  fare_amount         DECIMAL(15,2) NOT NULL,
  status              ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_transport_bookings_booking (booking_id),
  UNIQUE KEY uq_transport_bookings_seat_allocation (seat_allocation_id),
  CONSTRAINT fk_transport_bookings_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_transport_bookings_schedule FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE RESTRICT,
  CONSTRAINT fk_transport_bookings_seat FOREIGN KEY (seat_allocation_id) REFERENCES seat_allocations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7) EXPLORE / TOUR PACKAGES
-- =============================================================================

CREATE TABLE destination_categories (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  description TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at  DATETIME NULL,
  UNIQUE KEY uq_destination_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE featured_destinations (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id     BIGINT UNSIGNED NOT NULL,
  location_id     BIGINT UNSIGNED NOT NULL,
  name            VARCHAR(180) NOT NULL,
  slug            VARCHAR(220) NOT NULL,
  description     TEXT NOT NULL,
  image_url       VARCHAR(500) NOT NULL,
  rating          DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  is_featured     TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME NULL,
  UNIQUE KEY uq_featured_destinations_slug (slug),
  CONSTRAINT fk_featured_destinations_category FOREIGN KEY (category_id) REFERENCES destination_categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_featured_destinations_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tour_packages (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  package_code        VARCHAR(32) NOT NULL,
  vendor_id           BIGINT UNSIGNED NOT NULL,
  location_id         BIGINT UNSIGNED NOT NULL,
  title               VARCHAR(220) NOT NULL,
  slug                VARCHAR(240) NOT NULL,
  description         LONGTEXT NOT NULL,
  duration_days       INT UNSIGNED NOT NULL DEFAULT 1,
  max_group_size      INT UNSIGNED NULL,
  base_price          DECIMAL(15,2) NOT NULL,
  currency            CHAR(3) NOT NULL DEFAULT 'MWK',
  status              ENUM('draft','pending_review','published','suspended','archived') NOT NULL DEFAULT 'draft',
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,
  UNIQUE KEY uq_tour_packages_code (package_code),
  UNIQUE KEY uq_tour_packages_slug (slug),
  KEY idx_tour_packages_vendor (vendor_id),
  CONSTRAINT fk_tour_packages_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
  CONSTRAINT fk_tour_packages_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tour_bookings (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id      BIGINT UNSIGNED NULL,
  tour_package_id BIGINT UNSIGNED NOT NULL,
  travel_date     DATE NOT NULL,
  group_size      INT UNSIGNED NOT NULL DEFAULT 1,
  pickup_location_id BIGINT UNSIGNED NULL,
  status          ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tour_bookings_booking (booking_id),
  CONSTRAINT fk_tour_bookings_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_tour_bookings_package FOREIGN KEY (tour_package_id) REFERENCES tour_packages(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tour_bookings_pickup FOREIGN KEY (pickup_location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 8) CUSTOMER FEATURES
-- =============================================================================

CREATE TABLE favorites (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             BIGINT UNSIGNED NOT NULL,
  favorite_type       ENUM('event','property','tour','transport','vendor','destination') NOT NULL,
  reference_id        VARCHAR(64) NOT NULL,
  notes               VARCHAR(255) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_favorites_unique (user_id, favorite_type, reference_id),
  CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recent_views (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             BIGINT UNSIGNED NULL,
  session_id          BIGINT UNSIGNED NULL,
  view_type           ENUM('event','property','tour','transport','vendor','destination') NOT NULL,
  reference_id        VARCHAR(64) NOT NULL,
  viewed_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_recent_views_user (user_id),
  CONSTRAINT fk_recent_views_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_recent_views_session FOREIGN KEY (session_id) REFERENCES user_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_reviews (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             BIGINT UNSIGNED NOT NULL,
  review_type         ENUM('event','property','tour','transport','vendor','destination') NOT NULL,
  reference_id        VARCHAR(64) NOT NULL,
  rating              TINYINT UNSIGNED NOT NULL,
  title               VARCHAR(150) NULL,
  comment             TEXT NOT NULL,
  status              ENUM('pending','published','hidden') NOT NULL DEFAULT 'published',
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (rating BETWEEN 1 AND 5),
  KEY idx_customer_reviews_user (user_id),
  KEY idx_customer_reviews_type (review_type),
  KEY idx_customer_reviews_reference (reference_id),
  CONSTRAINT fk_customer_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_notifications (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             BIGINT UNSIGNED NOT NULL,
  notification_id     BIGINT UNSIGNED NOT NULL,
  is_read             TINYINT(1) NOT NULL DEFAULT 0,
  read_at             DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_notifications (user_id, notification_id),
  CONSTRAINT fk_customer_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 9) WHATSAPP BOOKING SUPPORT
-- =============================================================================

CREATE TABLE whatsapp_booking_requests (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_name       VARCHAR(150) NOT NULL,
  customer_phone      VARCHAR(30) NOT NULL,
  customer_email      VARCHAR(180) NULL,
  request_type        ENUM('event','property','transport','tour','general') NOT NULL DEFAULT 'general',
  reference_type      VARCHAR(60) NULL,
  reference_id        VARCHAR(64) NULL,
  request_details     TEXT NOT NULL,
  status              ENUM('new','processing','converted','cancelled') NOT NULL DEFAULT 'new',
  converted_booking_id BIGINT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_whatsapp_booking_phone (customer_phone),
  KEY idx_whatsapp_booking_status (status),
  CONSTRAINT fk_whatsapp_booking_converted FOREIGN KEY (converted_booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE whatsapp_message_logs (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id          BIGINT UNSIGNED NOT NULL,
  message_direction   ENUM('inbound','outbound') NOT NULL,
  message_body        TEXT NOT NULL,
  external_message_id VARCHAR(150) NULL,
  delivery_status     ENUM('queued','sent','delivered','failed') NOT NULL DEFAULT 'queued',
  sent_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_whatsapp_logs_request (request_id),
  CONSTRAINT fk_whatsapp_logs_request FOREIGN KEY (request_id) REFERENCES whatsapp_booking_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 10) COMMUNICATION
-- =============================================================================

CREATE TABLE notification_templates (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key    VARCHAR(100) NOT NULL,
  channel         ENUM('email','sms','whatsapp','in_app') NOT NULL,
  subject         VARCHAR(200) NULL,
  body_html       LONGTEXT NULL,
  body_text       LONGTEXT NOT NULL,
  variables       JSON NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notification_templates_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id         BIGINT UNSIGNED NULL,
  user_id             BIGINT UNSIGNED NULL,
  recipient_address   VARCHAR(200) NULL,
  channel             ENUM('email','sms','whatsapp','in_app') NOT NULL,
  subject             VARCHAR(200) NULL,
  body                LONGTEXT NOT NULL,
  payload             JSON NULL,
  status              ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  scheduled_at        DATETIME NULL,
  sent_at             DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_notifications_template (template_id),
  KEY idx_notifications_user (user_id),
  KEY idx_notifications_status (status),
  CONSTRAINT fk_notifications_template FOREIGN KEY (template_id) REFERENCES notification_templates(id) ON DELETE SET NULL,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_logs (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id     BIGINT UNSIGNED NULL,
  channel             ENUM('email','sms','push','in_app') NOT NULL DEFAULT 'in_app',
  recipient           VARCHAR(180) NOT NULL,
  status              ENUM('queued','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
  provider_response   JSON NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at             DATETIME NULL,
  KEY idx_notification_logs_notification (notification_id),
  KEY idx_notification_logs_channel (channel),
  CONSTRAINT fk_notification_logs_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_logs (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id     BIGINT UNSIGNED NULL,
  user_id             BIGINT UNSIGNED NULL,
  email_address       VARCHAR(180) NOT NULL,
  subject             VARCHAR(200) NOT NULL,
  status              ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  provider_response   JSON NULL,
  sent_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_logs_user (user_id),
  KEY idx_email_logs_notification (notification_id),
  CONSTRAINT fk_email_logs_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL,
  CONSTRAINT fk_email_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sms_logs (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id     BIGINT UNSIGNED NULL,
  user_id             BIGINT UNSIGNED NULL,
  phone_number        VARCHAR(30) NOT NULL,
  message_body        TEXT NOT NULL,
  status              ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  provider_response   JSON NULL,
  sent_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sms_logs_user (user_id),
  KEY idx_sms_logs_notification (notification_id),
  CONSTRAINT fk_sms_logs_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL,
  CONSTRAINT fk_sms_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 11) PAYMENT READY STRUCTURE
-- =============================================================================

CREATE TABLE transactions (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_reference VARCHAR(60) NOT NULL,
  booking_id          BIGINT UNSIGNED NULL,
  user_id             BIGINT UNSIGNED NOT NULL,
  vendor_id           BIGINT UNSIGNED NULL,
  amount              DECIMAL(15,2) NOT NULL,
  currency            CHAR(3) NOT NULL DEFAULT 'MWK',
  gateway_name        VARCHAR(100) NULL,
  transaction_type    ENUM('booking_payment','wallet_topup','payout','refund','commission') NOT NULL,
  status              ENUM('pending','success','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  metadata            JSON NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_transactions_reference (transaction_reference),
  KEY idx_transactions_booking (booking_id),
  KEY idx_transactions_user (user_id),
  KEY idx_transactions_vendor (vendor_id),
  KEY idx_transactions_status (status),
  CONSTRAINT fk_transactions_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_transactions_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ticket_sales
  ADD CONSTRAINT fk_ticket_sales_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL;

CREATE TABLE payment_attempts (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_id      BIGINT UNSIGNED NOT NULL,
  attempt_no          INT UNSIGNED NOT NULL DEFAULT 1,
  request_payload     JSON NULL,
  response_payload    JSON NULL,
  status              ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  attempted_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payment_attempts_transaction (transaction_id),
  CONSTRAINT fk_payment_attempts_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refunds (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_id      BIGINT UNSIGNED NOT NULL,
  requested_by        BIGINT UNSIGNED NULL,
  processed_by        BIGINT UNSIGNED NULL,
  amount              DECIMAL(15,2) NOT NULL,
  reason              VARCHAR(255) NOT NULL,
  status              ENUM('pending','approved','rejected','processed','failed') NOT NULL DEFAULT 'pending',
  processed_at        DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_refunds_transaction (transaction_id),
  KEY idx_refunds_status (status),
  CONSTRAINT fk_refunds_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_refunds_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_refunds_processed_by FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commissions (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id          BIGINT UNSIGNED NOT NULL,
  vendor_id           BIGINT UNSIGNED NULL,
  gross_amount        DECIMAL(15,2) NOT NULL,
  commission_rate     DECIMAL(5,2) NOT NULL,
  commission_amount   DECIMAL(15,2) NOT NULL,
  net_vendor_amount   DECIMAL(15,2) NOT NULL,
  settlement_status   ENUM('pending','settled','reversed') NOT NULL DEFAULT 'pending',
  settled_at          DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_commissions_booking (booking_id),
  KEY idx_commissions_vendor (vendor_id),
  CONSTRAINT fk_commissions_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
  CONSTRAINT fk_commissions_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 12) ANALYTICS
-- =============================================================================

CREATE TABLE page_views (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id          BIGINT UNSIGNED NULL,
  user_id             BIGINT UNSIGNED NULL,
  page_url            VARCHAR(500) NOT NULL,
  page_title          VARCHAR(255) NULL,
  referrer_url        VARCHAR(500) NULL,
  ip_address          VARCHAR(45) NULL,
  user_agent          VARCHAR(500) NULL,
  viewed_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_page_views_page (page_url),
  KEY idx_page_views_session (session_id),
  KEY idx_page_views_user (user_id),
  CONSTRAINT fk_page_views_session FOREIGN KEY (session_id) REFERENCES user_sessions(id) ON DELETE SET NULL,
  CONSTRAINT fk_page_views_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_views (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id            BIGINT UNSIGNED NOT NULL,
  user_id             BIGINT UNSIGNED NULL,
  session_id          BIGINT UNSIGNED NULL,
  source              VARCHAR(80) NULL,
  viewed_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_event_views_event (event_id),
  KEY idx_event_views_session (session_id),
  CONSTRAINT fk_event_views_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_event_views_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_event_views_session FOREIGN KEY (session_id) REFERENCES user_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_analytics (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id          BIGINT UNSIGNED NULL,
  user_id             BIGINT UNSIGNED NULL,
  listing_type        ENUM('event','property','tour','transport') NOT NULL,
  listing_reference_id VARCHAR(64) NULL,
  metric_date         DATE NOT NULL,
  views               INT UNSIGNED NOT NULL DEFAULT 0,
  bookings_count      INT UNSIGNED NOT NULL DEFAULT 0,
  total_revenue       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  commission_revenue  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  refunds_amount      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_booking_analytics (listing_type, listing_reference_id, metric_date),
  KEY idx_booking_analytics_booking (booking_id),
  KEY idx_booking_analytics_user (user_id),
  KEY idx_booking_analytics_date (metric_date),
  CONSTRAINT fk_booking_analytics_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_booking_analytics_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendor_analytics (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id           BIGINT UNSIGNED NOT NULL,
  metric_date         DATE NOT NULL,
  page_views          INT UNSIGNED NOT NULL DEFAULT 0,
  event_views         INT UNSIGNED NOT NULL DEFAULT 0,
  bookings_count      INT UNSIGNED NOT NULL DEFAULT 0,
  gross_revenue       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  net_revenue         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  rating_average      DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vendor_analytics (vendor_id, metric_date),
  KEY idx_vendor_analytics_date (metric_date),
  CONSTRAINT fk_vendor_analytics_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE search_logs (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             BIGINT UNSIGNED NULL,
  session_id          BIGINT UNSIGNED NULL,
  search_query        VARCHAR(255) NOT NULL,
  search_type         VARCHAR(80) NULL,
  filters             JSON NULL,
  result_count        INT UNSIGNED NOT NULL DEFAULT 0,
  searched_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_search_logs_query (search_query),
  CONSTRAINT fk_search_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_search_logs_session FOREIGN KEY (session_id) REFERENCES user_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_activity_logs (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             BIGINT UNSIGNED NULL,
  activity_type       VARCHAR(120) NOT NULL,
  entity_type         VARCHAR(120) NULL,
  entity_id           VARCHAR(64) NULL,
  details             JSON NULL,
  ip_address          VARCHAR(45) NULL,
  user_agent          VARCHAR(500) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_activity_user (user_id),
  CONSTRAINT fk_user_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 13) ADMIN, SUPPORT, AND SYSTEM
-- =============================================================================

CREATE TABLE audit_logs (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id       BIGINT UNSIGNED NULL,
  action              VARCHAR(150) NOT NULL,
  entity_table        VARCHAR(120) NOT NULL,
  entity_id           VARCHAR(64) NOT NULL,
  before_data         JSON NULL,
  after_data          JSON NULL,
  ip_address          VARCHAR(45) NULL,
  user_agent          VARCHAR(500) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_logs_actor (actor_user_id),
  KEY idx_audit_logs_action (action),
  CONSTRAINT fk_audit_logs_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_actions (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id       BIGINT UNSIGNED NOT NULL,
  action_type         VARCHAR(150) NOT NULL,
  target_type         VARCHAR(120) NULL,
  target_id           VARCHAR(64) NULL,
  notes               TEXT NULL,
  metadata            JSON NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin_actions_admin (admin_user_id),
  CONSTRAINT fk_admin_actions_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_health_logs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  health_key      VARCHAR(120) NOT NULL,
  status          ENUM('healthy','warning','critical','unknown') NOT NULL DEFAULT 'unknown',
  value_payload   JSON NULL,
  notes           TEXT NULL,
  recorded_by     BIGINT UNSIGNED NULL,
  recorded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_system_health_key (health_key),
  KEY idx_system_health_status (status),
  CONSTRAINT fk_system_health_logged_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_settings (
  setting_key         VARCHAR(120) NOT NULL PRIMARY KEY,
  setting_value       LONGTEXT NOT NULL,
  value_type          ENUM('string','number','boolean','json') NOT NULL DEFAULT 'string',
  description         VARCHAR(255) NULL,
  updated_by          BIGINT UNSIGNED NULL,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_system_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_announcements (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title               VARCHAR(180) NOT NULL,
  message             TEXT NOT NULL,
  audience            ENUM('all','customers','vendors','admins') NOT NULL DEFAULT 'all',
  priority            ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  starts_at           DATETIME NOT NULL,
  ends_at             DATETIME NULL,
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  created_by          BIGINT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_system_announcements_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_tickets (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_code         VARCHAR(32) NOT NULL,
  requester_user_id   BIGINT UNSIGNED NULL,
  requester_name      VARCHAR(150) NOT NULL,
  requester_email     VARCHAR(180) NULL,
  subject             VARCHAR(200) NOT NULL,
  category            VARCHAR(80) NOT NULL,
  priority            ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  status              ENUM('open','in_progress','waiting_customer','resolved','closed') NOT NULL DEFAULT 'open',
  assigned_admin_id   BIGINT UNSIGNED NULL,
  message             TEXT NOT NULL,
  closed_at           DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at          DATETIME NULL,
  UNIQUE KEY uq_support_tickets_code (ticket_code),
  KEY idx_support_tickets_user (requester_user_id),
  KEY idx_support_tickets_status (status),
  CONSTRAINT fk_support_tickets_user FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_support_tickets_admin FOREIGN KEY (assigned_admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_responses (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id           BIGINT UNSIGNED NOT NULL,
  sender              VARCHAR(150) NOT NULL,
  message             TEXT NOT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket_responses_ticket (ticket_id),
  CONSTRAINT fk_ticket_responses_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 14) FINAL FOREIGN KEY PASS-THROUGH
-- =============================================================================

ALTER TABLE event_maps
  ADD CONSTRAINT fk_event_maps_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_event_maps_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT;

ALTER TABLE property_maps
  ADD CONSTRAINT fk_property_maps_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_property_maps_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT;

ALTER TABLE route_maps
  ADD CONSTRAINT fk_route_maps_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_route_maps_origin FOREIGN KEY (origin_location_id) REFERENCES locations(id) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_route_maps_destination FOREIGN KEY (destination_location_id) REFERENCES locations(id) ON DELETE RESTRICT;

ALTER TABLE customer_notifications
  ADD CONSTRAINT fk_customer_notifications_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
