-- Accommodation Properties workspace: one authoritative property portfolio,
-- active-management context and versioned customer-facing configuration.
-- This migration deliberately keeps listings as the marketplace projection;
-- drafts remain inactive until the deterministic publication gate succeeds.

CREATE TABLE IF NOT EXISTS tie_accommodation_vendor_context (
  vendor_id VARCHAR(30) NOT NULL PRIMARY KEY,
  active_property_id CHAR(36) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_context_property (active_property_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_property_profiles (
  property_id CHAR(36) NOT NULL PRIMARY KEY,
  display_name VARCHAR(180) NULL,
  short_description VARCHAR(500) NULL,
  region VARCHAR(120) NULL,
  district VARCHAR(120) NULL,
  locality VARCHAR(120) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  location_source ENUM('MANUAL','MAP_PIN','GEOCODED','DEVICE') NOT NULL DEFAULT 'MANUAL',
  location_accuracy_m DECIMAL(10,2) NULL,
  location_captured_at DATETIME NULL,
  quality_classification ENUM('UNRATED','ONE','TWO','THREE','FOUR','FIVE') NOT NULL DEFAULT 'UNRATED',
  legal_business_name VARCHAR(190) NULL,
  trading_name VARCHAR(190) NULL,
  business_registration VARCHAR(120) NULL,
  tax_identifier VARCHAR(120) NULL,
  website_url VARCHAR(500) NULL,
  highlights JSON NULL,
  amenities JSON NULL,
  guest_policy JSON NULL,
  verification_status ENUM('NOT_SUBMITTED','SUBMITTED','UNDER_REVIEW','VERIFIED','REJECTED','EXPIRED') NOT NULL DEFAULT 'NOT_SUBMITTED',
  verification_note VARCHAR(1000) NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_profile_verification (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO tie_accommodation_property_profiles (property_id, display_name, short_description, verification_status)
SELECT id, name, LEFT(COALESCE(description, ''), 500), 'NOT_SUBMITTED'
FROM tie_accommodation_properties;

CREATE TABLE IF NOT EXISTS tie_accommodation_property_media (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  storage_name VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  media_category ENUM('EXTERIOR','INTERIOR','ROOMS','BATHROOM','DINING','FACILITIES','POOL','CONFERENCE','LANDSCAPE','OTHER') NOT NULL DEFAULT 'OTHER',
  caption VARCHAR(500) NULL,
  alt_text VARCHAR(255) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_cover TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_by VARCHAR(30) NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_accommodation_property_media_storage (storage_name),
  KEY idx_tie_accommodation_property_media (property_id, is_cover, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
