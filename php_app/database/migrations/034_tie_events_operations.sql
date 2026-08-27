-- Events Control Center: one authoritative event operations record per
-- vendor event, a venue directory, materialized schedule occurrences and
-- an audit trail. Mirrors the tie_accommodation_properties pattern:
-- listings stays the marketplace projection; drafts remain inactive
-- (listings.is_active = 0) until the publish gate succeeds.

CREATE TABLE IF NOT EXISTS tie_venues (
  id VARCHAR(30) NOT NULL PRIMARY KEY,
  vendor_id VARCHAR(30) NOT NULL,
  name VARCHAR(180) NOT NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  gps_lat DECIMAL(10,7) NULL,
  gps_lng DECIMAL(10,7) NULL,
  capacity INT UNSIGNED NULL,
  description VARCHAR(1000) NULL,
  contact_phone VARCHAR(30) NULL,
  contact_email VARCHAR(190) NULL,
  amenities JSON NULL,
  verification_status ENUM('UNVERIFIED','VERIFIED') NOT NULL DEFAULT 'UNVERIFIED',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_venues_vendor (vendor_id),
  KEY idx_tie_venues_city (city),
  CONSTRAINT fk_tie_venues_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_events_events (
  id VARCHAR(30) NOT NULL PRIMARY KEY,
  vendor_id VARCHAR(30) NOT NULL,
  listing_id VARCHAR(30) NULL,
  venue_id VARCHAR(30) NULL,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  category VARCHAR(80) NULL,
  event_type VARCHAR(80) NULL,
  short_description VARCHAR(300) NULL,
  description TEXT NULL,
  highlights JSON NULL,
  what_to_expect TEXT NULL,
  cover_image_url VARCHAR(500) NULL,
  gallery JSON NULL,
  schedule_mode ENUM('SINGLE','MULTI_DAY','RECURRING') NOT NULL DEFAULT 'SINGLE',
  start_date DATE NULL,
  start_time TIME NULL,
  end_date DATE NULL,
  end_time TIME NULL,
  doors_open_time TIME NULL,
  recurrence_rule JSON NULL,
  status ENUM('DRAFT','PUBLISHED','PAUSED','CANCELLED','COMPLETED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
  policies JSON NULL,
  organizer_display_name VARCHAR(150) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_by VARCHAR(30) NULL,
  updated_by VARCHAR(30) NULL,
  published_by VARCHAR(30) NULL,
  published_at DATETIME NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_events_slug (slug),
  KEY idx_tie_events_vendor_status (vendor_id, status),
  KEY idx_tie_events_listing (listing_id),
  KEY idx_tie_events_venue (venue_id),
  CONSTRAINT fk_tie_events_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tie_events_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  CONSTRAINT fk_tie_events_venue FOREIGN KEY (venue_id) REFERENCES tie_venues(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_events_schedule_occurrences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id VARCHAR(30) NOT NULL,
  occurrence_date DATE NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  doors_open_time TIME NULL,
  label VARCHAR(80) NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_events_occurrence_event (event_id, occurrence_date),
  CONSTRAINT fk_tie_events_occurrence_event FOREIGN KEY (event_id) REFERENCES tie_events_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_events_audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id VARCHAR(30) NOT NULL,
  actor_id VARCHAR(30) NULL,
  action VARCHAR(60) NOT NULL,
  field_changes JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_events_audit_event (event_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket type fields introduced by the Events wizard (Tickets & Policies
-- steps); ticket_types itself already exists (migration 006) and stays the
-- authoritative inventory table for both vendor and customer surfaces.
ALTER TABLE ticket_types
  ADD COLUMN IF NOT EXISTS access_scope VARCHAR(80) NULL AFTER description,
  ADD COLUMN IF NOT EXISTS transferable TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active,
  ADD COLUMN IF NOT EXISTS refundable TINYINT(1) NOT NULL DEFAULT 1 AFTER transferable,
  ADD COLUMN IF NOT EXISTS tier VARCHAR(20) NULL AFTER refundable;
