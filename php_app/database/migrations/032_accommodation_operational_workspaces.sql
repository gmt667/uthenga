-- Data-backed workspaces that complete the accommodation control centre.

CREATE TABLE IF NOT EXISTS tie_accommodation_guest_profiles (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  user_id VARCHAR(30) NULL,
  contact_key CHAR(64) NOT NULL,
  display_name VARCHAR(180) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  nationality_code CHAR(2) NULL,
  document_type VARCHAR(40) NULL,
  document_last4 CHAR(4) NULL,
  consent_status ENUM('NOT_RECORDED','GRANTED','WITHDRAWN') NOT NULL DEFAULT 'NOT_RECORDED',
  marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
  accessibility_notes VARCHAR(1000) NULL,
  first_stay_date DATE NULL,
  last_stay_date DATE NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_accommodation_guest_contact (property_id, contact_key),
  KEY idx_tie_accommodation_guest_name (property_id, display_name),
  KEY idx_tie_accommodation_guest_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_guest_notes (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  guest_id CHAR(36) NOT NULL,
  note_type ENUM('OPERATIONAL','ACCESSIBILITY','SERVICE_RECOVERY') NOT NULL DEFAULT 'OPERATIONAL',
  note_text VARCHAR(1000) NOT NULL,
  created_by VARCHAR(30) NOT NULL,
  retention_until DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_guest_note (guest_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_facilities (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  facility_type VARCHAR(50) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description VARCHAR(1000) NULL,
  capacity INT UNSIGNED NULL,
  opens_at TIME NULL,
  closes_at TIME NULL,
  status ENUM('ACTIVE','CLOSED','MAINTENANCE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  last_inspected_at DATETIME NULL,
  next_inspection_at DATETIME NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_facility (property_id, status, facility_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_documents (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  category ENUM('LICENSE','INSURANCE','SAFETY','TAX','POLICY','OTHER') NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  expires_on DATE NULL,
  status ENUM('ACTIVE','EXPIRED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  uploaded_by VARCHAR(30) NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_accommodation_document_storage (storage_name),
  KEY idx_tie_accommodation_document (property_id, category, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE tie_accommodation_promotions
  ADD COLUMN IF NOT EXISTS property_id CHAR(36) NULL AFTER id,
  ADD COLUMN IF NOT EXISTS promo_code VARCHAR(50) NULL AFTER description,
  ADD COLUMN IF NOT EXISTS minimum_nights SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER discount_percent,
  ADD COLUMN IF NOT EXISTS room_type_id BIGINT UNSIGNED NULL AFTER minimum_nights,
  ADD COLUMN IF NOT EXISTS maximum_redemptions INT UNSIGNED NULL AFTER room_type_id,
  ADD COLUMN IF NOT EXISTS redemption_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER maximum_redemptions,
  ADD COLUMN IF NOT EXISTS version INT UNSIGNED NOT NULL DEFAULT 1 AFTER status,
  ADD UNIQUE KEY IF NOT EXISTS uq_tie_accommodation_promo_code (property_id, promo_code),
  ADD KEY IF NOT EXISTS idx_tie_accommodation_promotion_property (property_id, status, starts_at, ends_at);

UPDATE tie_accommodation_promotions pr
INNER JOIN tie_accommodation_properties p ON p.listing_id=pr.listing_id
SET pr.property_id=p.id
WHERE pr.property_id IS NULL;

CREATE TABLE IF NOT EXISTS tie_accommodation_review_responses (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  review_id VARCHAR(30) NOT NULL,
  response_text VARCHAR(1500) NOT NULL,
  responded_by VARCHAR(30) NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_accommodation_review_response (property_id, review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_property_settings (
  property_id CHAR(36) NOT NULL PRIMARY KEY,
  tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  service_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  default_booking_mode ENUM('INSTANT','REQUEST') NOT NULL DEFAULT 'INSTANT',
  require_identity_at_check_in TINYINT(1) NOT NULL DEFAULT 1,
  allow_balance_at_check_out TINYINT(1) NOT NULL DEFAULT 0,
  guest_email_enabled TINYINT(1) NOT NULL DEFAULT 1,
  guest_sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
  housekeeping_sla_minutes INT UNSIGNED NOT NULL DEFAULT 90,
  audit_retention_days INT UNSIGNED NOT NULL DEFAULT 2555,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  updated_by VARCHAR(30) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO tie_accommodation_property_settings (property_id,updated_by)
SELECT id,vendor_id FROM tie_accommodation_properties;
