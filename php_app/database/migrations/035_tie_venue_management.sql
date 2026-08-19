-- Venue Management System: operational workspaces for the Events V2 module.
-- Extends tie_venues (identity, type, location, lifecycle) and adds spaces,
-- facilities, media, pricing, policies, an availability calendar and
-- event-venue assignments with backend-enforced conflict prevention.

ALTER TABLE tie_venues
  ADD COLUMN IF NOT EXISTS type VARCHAR(60) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS district VARCHAR(120) NULL AFTER city,
  ADD COLUMN IF NOT EXISTS country VARCHAR(120) NULL AFTER region,
  ADD COLUMN IF NOT EXISTS status ENUM('DRAFT','PENDING_REVIEW','ACTIVE','TEMPORARILY_UNAVAILABLE','MAINTENANCE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE' AFTER verification_status,
  ADD COLUMN IF NOT EXISTS cover_image VARCHAR(500) NULL AFTER status;

CREATE TABLE IF NOT EXISTS tie_venue_spaces (
  id VARCHAR(30) NOT NULL PRIMARY KEY,
  venue_id VARCHAR(30) NOT NULL,
  name VARCHAR(180) NOT NULL,
  type VARCHAR(60) NULL,
  capacity INT UNSIGNED NULL,
  description VARCHAR(1000) NULL,
  dimensions VARCHAR(120) NULL,
  status ENUM('ACTIVE','MAINTENANCE','BLOCKED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_vsp_venue (venue_id),
  CONSTRAINT fk_vsp_venue FOREIGN KEY (venue_id) REFERENCES tie_venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_venue_facilities (
  id VARCHAR(30) NOT NULL PRIMARY KEY,
  venue_id VARCHAR(30) NOT NULL,
  facility_group ENUM('GENERAL','TECHNOLOGY','ACCESSIBILITY','HOSPITALITY','SECURITY') NOT NULL DEFAULT 'GENERAL',
  name VARCHAR(180) NOT NULL,
  description VARCHAR(500) NULL,
  available TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_vfac_venue (venue_id),
  CONSTRAINT fk_vfac_venue FOREIGN KEY (venue_id) REFERENCES tie_venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_venue_media (
  id VARCHAR(30) NOT NULL PRIMARY KEY,
  venue_id VARCHAR(30) NOT NULL,
  space_id VARCHAR(30) NULL,
  media_type ENUM('COVER','GALLERY','FLOOR_PLAN') NOT NULL DEFAULT 'GALLERY',
  url VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_cover TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_vmed_venue (venue_id),
  CONSTRAINT fk_vmed_venue FOREIGN KEY (venue_id) REFERENCES tie_venues(id) ON DELETE CASCADE,
  CONSTRAINT fk_vmed_space FOREIGN KEY (space_id) REFERENCES tie_venue_spaces(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_venue_pricing (
  id VARCHAR(30) NOT NULL PRIMARY KEY,
  venue_id VARCHAR(30) NOT NULL,
  name VARCHAR(120) NOT NULL,
  price DECIMAL(14,2) NOT NULL,
  currency VARCHAR(3) NOT NULL DEFAULT 'MWK',
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_vpr_venue (venue_id),
  CONSTRAINT fk_vpr_venue FOREIGN KEY (venue_id) REFERENCES tie_venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_venue_policies (
  venue_id VARCHAR(30) NOT NULL PRIMARY KEY,
  cancellation_policy TEXT NULL,
  advance_booking_days INT UNSIGNED NULL,
  min_duration_hours INT UNSIGNED NULL,
  max_duration_hours INT UNSIGNED NULL,
  restrictions JSON NULL,
  opening_time VARCHAR(5) NULL,
  closing_time VARCHAR(5) NULL,
  setup_period_minutes INT UNSIGNED NOT NULL DEFAULT 120,
  teardown_period_minutes INT UNSIGNED NOT NULL DEFAULT 60,
  check_in_time VARCHAR(5) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_vpol_venue FOREIGN KEY (venue_id) REFERENCES tie_venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_venue_availability (
  id VARCHAR(30) NOT NULL PRIMARY KEY,
  venue_id VARCHAR(30) NOT NULL,
  space_id VARCHAR(30) NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  status ENUM('AVAILABLE','RESERVED','EVENT','SETUP','MAINTENANCE','BLOCKED') NOT NULL DEFAULT 'AVAILABLE',
  reason VARCHAR(500) NULL,
  event_id VARCHAR(30) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_va_venue_time (venue_id, start_at, end_at),
  KEY idx_va_space_time (space_id, start_at, end_at),
  CONSTRAINT fk_va_venue FOREIGN KEY (venue_id) REFERENCES tie_venues(id) ON DELETE CASCADE,
  CONSTRAINT fk_va_space FOREIGN KEY (space_id) REFERENCES tie_venue_spaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_event_venue_assignments (
  id VARCHAR(30) NOT NULL PRIMARY KEY,
  event_id VARCHAR(30) NOT NULL,
  venue_id VARCHAR(30) NOT NULL,
  space_id VARCHAR(30) NULL,
  setup_start DATETIME NOT NULL,
  event_start DATETIME NOT NULL,
  event_end DATETIME NOT NULL,
  teardown_end DATETIME NOT NULL,
  status ENUM('REQUESTED','CONFIRMED','CANCELLED') NOT NULL DEFAULT 'CONFIRMED',
  created_by VARCHAR(30) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_eva_event (event_id),
  KEY idx_eva_venue_time (venue_id, setup_start, teardown_end),
  CONSTRAINT fk_eva_event FOREIGN KEY (event_id) REFERENCES tie_events_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_eva_venue FOREIGN KEY (venue_id) REFERENCES tie_venues(id) ON DELETE CASCADE,
  CONSTRAINT fk_eva_space FOREIGN KEY (space_id) REFERENCES tie_venue_spaces(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_venue_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  venue_id VARCHAR(30) NOT NULL,
  action VARCHAR(80) NOT NULL,
  actor_id VARCHAR(30) NULL,
  actor_name VARCHAR(120) NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_vaud_venue (venue_id),
  CONSTRAINT fk_vaud_venue FOREIGN KEY (venue_id) REFERENCES tie_venues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;