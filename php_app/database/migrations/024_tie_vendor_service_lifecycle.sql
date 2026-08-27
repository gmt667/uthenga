-- A shared lifecycle for every vendor operating profile. Category-specific
-- configuration remains in the profile JSON; publication is always explicit.
ALTER TABLE tie_vendor_service_profiles
  MODIFY COLUMN status VARCHAR(24) NOT NULL DEFAULT 'PRIVATE_DRAFT';

UPDATE tie_vendor_service_profiles
SET status = CASE
  WHEN status IN ('DRAFT', 'NEW', '') THEN 'PRIVATE_DRAFT'
  WHEN status = 'INACTIVE' THEN 'PAUSED'
  ELSE status
END;

CREATE TABLE IF NOT EXISTS tie_vendor_service_profile_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profile_id CHAR(36) NOT NULL,
  vendor_id VARCHAR(30) NOT NULL,
  actor_id VARCHAR(30) NULL,
  event_type VARCHAR(50) NOT NULL,
  from_status VARCHAR(24) NULL,
  to_status VARCHAR(24) NOT NULL,
  note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_vendor_profile_event_profile (profile_id, created_at),
  KEY idx_tie_vendor_profile_event_vendor (vendor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
