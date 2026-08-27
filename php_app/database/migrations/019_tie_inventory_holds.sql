-- Phase 15 compatibility inventory resources and atomic holds.
-- The legacy listings table uses a deployment-specific collation, so these
-- resources deliberately validate listing ownership in application code rather
-- than adding incompatible foreign-key collations.
CREATE TABLE IF NOT EXISTS ticket_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id VARCHAR(30) NOT NULL,
  name VARCHAR(80) NOT NULL,
  description TEXT NULL,
  price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  remaining_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  sale_start DATETIME NULL,
  sale_end DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_ticket_types_listing (listing_id), KEY idx_ticket_types_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS seat_classes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id VARCHAR(30) NOT NULL,
  class_name VARCHAR(80) NOT NULL,
  description TEXT NULL,
  price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_seats INT UNSIGNED NOT NULL DEFAULT 0,
  remaining_seats INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_seat_classes_listing (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS room_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id VARCHAR(30) NOT NULL,
  room_name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  price_per_night DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total_rooms INT UNSIGNED NOT NULL DEFAULT 0,
  available_rooms INT UNSIGNED NOT NULL DEFAULT 0,
  max_occupancy TINYINT UNSIGNED NOT NULL DEFAULT 2,
  amenities JSON NULL,
  room_images JSON NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_room_types_listing (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_inventory_holds (
  id CHAR(36) NOT NULL PRIMARY KEY,
  user_id VARCHAR(30) NOT NULL,
  plan_id VARCHAR(32) NOT NULL,
  resource_type VARCHAR(20) NOT NULL,
  resource_id BIGINT UNSIGNED NOT NULL,
  listing_id VARCHAR(30) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL,
  payment_intent_id CHAR(36) NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  released_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_hold_expiry (status, expires_at), KEY idx_tie_hold_plan (plan_id),
  KEY idx_tie_hold_payment (payment_intent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
