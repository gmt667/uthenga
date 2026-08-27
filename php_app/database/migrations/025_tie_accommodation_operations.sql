-- Authoritative operations data for the accommodation control centre.
CREATE TABLE IF NOT EXISTS tie_accommodation_calendar (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id VARCHAR(30) NOT NULL,
  listing_id VARCHAR(30) NOT NULL,
  room_type_id BIGINT UNSIGNED NOT NULL,
  stay_date DATE NOT NULL,
  available_rooms INT UNSIGNED NOT NULL,
  blocked_rooms INT UNSIGNED NOT NULL DEFAULT 0,
  rate_override DECIMAL(15,2) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_accommodation_calendar (room_type_id, stay_date),
  KEY idx_tie_accommodation_calendar_vendor (vendor_id, listing_id, stay_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_stays (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id VARCHAR(30) NOT NULL,
  listing_id VARCHAR(30) NOT NULL,
  booking_id VARCHAR(64) NOT NULL,
  booking_item_id VARCHAR(64) NOT NULL,
  status ENUM('PENDING','CHECKED_IN','CHECKED_OUT','NO_SHOW') NOT NULL DEFAULT 'PENDING',
  checked_in_at DATETIME NULL,
  checked_out_at DATETIME NULL,
  note VARCHAR(1000) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_accommodation_stay_item (booking_item_id),
  KEY idx_tie_accommodation_stay_vendor (vendor_id, listing_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_room_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id VARCHAR(30) NOT NULL,
  listing_id VARCHAR(30) NOT NULL,
  room_type_id BIGINT UNSIGNED NOT NULL,
  task_kind ENUM('HOUSEKEEPING','MAINTENANCE') NOT NULL,
  status ENUM('CLEAN','CLEANING','INSPECTION','MAINTENANCE','OUT_OF_SERVICE') NOT NULL,
  room_count INT UNSIGNED NOT NULL DEFAULT 1,
  note VARCHAR(1000) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_task_vendor (vendor_id, listing_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_staff (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id VARCHAR(30) NOT NULL,
  profile_id CHAR(36) NOT NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  role_name VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_staff_vendor (vendor_id, profile_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id VARCHAR(30) NOT NULL,
  listing_id VARCHAR(30) NOT NULL,
  recipient_type ENUM('GUEST','STAFF') NOT NULL,
  recipient_reference VARCHAR(100) NOT NULL,
  body VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_message_vendor (vendor_id, listing_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_promotions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id VARCHAR(30) NOT NULL,
  listing_id VARCHAR(30) NOT NULL,
  title VARCHAR(160) NOT NULL,
  description VARCHAR(1000) NULL,
  discount_percent DECIMAL(5,2) NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  status ENUM('DRAFT','ACTIVE','PAUSED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_promotion_vendor (vendor_id, listing_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
