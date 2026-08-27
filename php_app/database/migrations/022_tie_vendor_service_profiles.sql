-- Phase 11 vendor operating profiles. A profile is the vendor-facing product
-- configuration; listings and inventory remain internal implementation details.
CREATE TABLE IF NOT EXISTS tie_vendor_service_profiles (
  id CHAR(36) NOT NULL PRIMARY KEY,
  vendor_id VARCHAR(30) NOT NULL,
  profile_type VARCHAR(30) NOT NULL,
  profile_name VARCHAR(180) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'DRAFT',
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  listing_id VARCHAR(30) NULL,
  configuration JSON NOT NULL,
  activated_at DATETIME NULL,
  deactivated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_vendor_profile_active (vendor_id, is_active, updated_at),
  KEY idx_tie_vendor_profile_type (vendor_id, profile_type, status),
  KEY idx_tie_vendor_profile_listing (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
