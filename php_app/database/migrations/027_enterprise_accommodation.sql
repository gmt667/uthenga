-- Enterprise Accommodation v2. Public listings/bookings/transactions remain
-- canonical marketplace records; these tables own nightly lodging operations.
CREATE TABLE IF NOT EXISTS tie_accommodation_properties (
  id CHAR(36) NOT NULL PRIMARY KEY,
  vendor_id VARCHAR(30) NOT NULL,
  service_profile_id CHAR(36) NULL,
  listing_id VARCHAR(30) NULL,
  name VARCHAR(180) NOT NULL,
  property_type ENUM('HOTEL','LODGE','GUESTHOUSE','HOSTEL','SERVICED_APARTMENT') NOT NULL DEFAULT 'HOTEL',
  description TEXT NULL,
  address VARCHAR(255) NOT NULL,
  city VARCHAR(120) NULL,
  country_code CHAR(2) NOT NULL DEFAULT 'MW',
  timezone VARCHAR(80) NOT NULL DEFAULT 'Africa/Blantyre',
  currency CHAR(3) NOT NULL DEFAULT 'MWK',
  phone VARCHAR(60) NULL,
  email VARCHAR(190) NULL,
  image_url VARCHAR(500) NULL,
  check_in_time TIME NOT NULL DEFAULT '14:00:00',
  check_out_time TIME NOT NULL DEFAULT '10:00:00',
  status ENUM('PRIVATE_DRAFT','SETUP_INCOMPLETE','READY_FOR_REVIEW','PUBLISHED','ACTIVE','PAUSED','ARCHIVED') NOT NULL DEFAULT 'PRIVATE_DRAFT',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_accommodation_property_listing (listing_id),
  KEY idx_tie_accommodation_property_vendor (vendor_id, status),
  KEY idx_tie_accommodation_property_profile (service_profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE room_types
  ADD COLUMN IF NOT EXISTS property_id CHAR(36) NULL AFTER listing_id,
  ADD COLUMN IF NOT EXISTS adults_capacity TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER max_occupancy,
  ADD COLUMN IF NOT EXISTS children_capacity TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER adults_capacity,
  ADD COLUMN IF NOT EXISTS version INT UNSIGNED NOT NULL DEFAULT 1 AFTER is_active,
  ADD KEY IF NOT EXISTS idx_room_types_property (property_id, is_active);

CREATE TABLE IF NOT EXISTS tie_accommodation_units (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  room_type_id BIGINT UNSIGNED NOT NULL,
  unit_code VARCHAR(80) NOT NULL,
  unit_name VARCHAR(160) NULL,
  floor_label VARCHAR(80) NULL,
  operational_status ENUM('CLEAN_READY','DIRTY','CLEANING','INSPECTION','MAINTENANCE','OUT_OF_SERVICE') NOT NULL DEFAULT 'CLEAN_READY',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_accommodation_unit_code (property_id, unit_code),
  KEY idx_tie_accommodation_unit_type (room_type_id, operational_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_cancellation_policies (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  name VARCHAR(160) NOT NULL,
  free_cancel_hours INT UNSIGNED NOT NULL DEFAULT 24,
  penalty_percent DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  no_show_percent DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_policy_property (property_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_rate_plans (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  room_type_id BIGINT UNSIGNED NOT NULL,
  cancellation_policy_id CHAR(36) NOT NULL,
  name VARCHAR(160) NOT NULL,
  base_rate DECIMAL(15,2) NOT NULL,
  booking_mode ENUM('INSTANT','REQUEST') NOT NULL DEFAULT 'INSTANT',
  payment_mode ENUM('FULL','DEPOSIT') NOT NULL DEFAULT 'FULL',
  deposit_percent DECIMAL(5,2) NULL,
  minimum_stay SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  maximum_stay SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_rate_property (property_id, room_type_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_inventory_nights (
  property_id CHAR(36) NOT NULL,
  room_type_id BIGINT UNSIGNED NOT NULL,
  stay_date DATE NOT NULL,
  capacity_rooms INT UNSIGNED NOT NULL,
  blocked_rooms INT UNSIGNED NOT NULL DEFAULT 0,
  held_rooms INT UNSIGNED NOT NULL DEFAULT 0,
  confirmed_rooms INT UNSIGNED NOT NULL DEFAULT 0,
  closed TINYINT(1) NOT NULL DEFAULT 0,
  rate_override DECIMAL(15,2) NULL,
  minimum_stay SMALLINT UNSIGNED NULL,
  maximum_stay SMALLINT UNSIGNED NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (room_type_id, stay_date),
  KEY idx_tie_accommodation_inventory_property (property_id, stay_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE tie_inventory_holds
  ADD COLUMN IF NOT EXISTS start_date DATE NULL AFTER quantity,
  ADD COLUMN IF NOT EXISTS end_date DATE NULL AFTER start_date,
  ADD COLUMN IF NOT EXISTS booking_id VARCHAR(20) NULL AFTER payment_intent_id,
  ADD COLUMN IF NOT EXISTS metadata JSON NULL AFTER booking_id;

CREATE TABLE IF NOT EXISTS tie_accommodation_hold_nights (
  hold_id CHAR(36) NOT NULL,
  property_id CHAR(36) NOT NULL,
  room_type_id BIGINT UNSIGNED NOT NULL,
  stay_date DATE NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  status ENUM('ACTIVE','CONSUMED','RELEASED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (hold_id, stay_date),
  KEY idx_tie_accommodation_hold_night_inventory (room_type_id, stay_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_reservations (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  booking_id VARCHAR(20) NULL,
  reservation_code VARCHAR(30) NOT NULL,
  source ENUM('UTHENGA','PHONE','FRONT_DESK','WALK_IN') NOT NULL,
  status ENUM('DRAFT','HOLD_PENDING','PENDING_APPROVAL','CONFIRMED','CHECKED_IN','CHECKED_OUT','NO_SHOW','CANCELLED','EXPIRED') NOT NULL,
  payment_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
  currency CHAR(3) NOT NULL DEFAULT 'MWK',
  subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  deposit_required DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  balance_due DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  check_in_date DATE NOT NULL,
  check_out_date DATE NOT NULL,
  adults SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  guest_name VARCHAR(180) NOT NULL,
  guest_email VARCHAR(190) NULL,
  guest_phone VARCHAR(60) NULL,
  guest_notes TEXT NULL,
  vendor_notes TEXT NULL,
  cancellation_policy_snapshot JSON NULL,
  cancelled_at DATETIME NULL,
  checked_in_at DATETIME NULL,
  checked_out_at DATETIME NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by VARCHAR(30) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_accommodation_reservation_code (reservation_code),
  UNIQUE KEY uq_tie_accommodation_reservation_booking (booking_id),
  KEY idx_tie_accommodation_reservation_property (property_id, check_in_date, status),
  KEY idx_tie_accommodation_reservation_guest (guest_email, guest_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_reservation_rooms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reservation_id CHAR(36) NOT NULL,
  room_type_id BIGINT UNSIGNED NOT NULL,
  rate_plan_id CHAR(36) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  nightly_rate DECIMAL(15,2) NOT NULL,
  line_total DECIMAL(15,2) NOT NULL,
  rate_snapshot JSON NOT NULL,
  KEY idx_tie_accommodation_reservation_room (reservation_id, room_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_assignments (
  id CHAR(36) NOT NULL PRIMARY KEY,
  reservation_id CHAR(36) NOT NULL,
  unit_id CHAR(36) NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  released_at DATETIME NULL,
  assigned_by VARCHAR(30) NOT NULL,
  UNIQUE KEY uq_tie_accommodation_active_assignment (reservation_id, unit_id),
  KEY idx_tie_accommodation_assignment_unit (unit_id, released_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_unit_tasks (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  unit_id CHAR(36) NOT NULL,
  reservation_id CHAR(36) NULL,
  task_kind ENUM('HOUSEKEEPING','MAINTENANCE','INSPECTION') NOT NULL,
  status ENUM('OPEN','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'OPEN',
  priority ENUM('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
  assigned_user_id VARCHAR(30) NULL,
  note VARCHAR(1000) NULL,
  due_at DATETIME NULL,
  completed_at DATETIME NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by VARCHAR(30) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_task_property (property_id, status, task_kind),
  KEY idx_tie_accommodation_task_unit (unit_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_staff_memberships (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  user_id VARCHAR(30) NULL,
  invited_email VARCHAR(190) NOT NULL,
  role_key ENUM('OWNER','GENERAL_MANAGER','FRONT_DESK','RESERVATIONS','HOUSEKEEPING','MAINTENANCE','FINANCE','AUDITOR') NOT NULL,
  status ENUM('INVITED','ACTIVE','SUSPENDED','REVOKED') NOT NULL DEFAULT 'INVITED',
  invited_by VARCHAR(30) NOT NULL,
  accepted_at DATETIME NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_accommodation_staff_email (property_id, invited_email),
  KEY idx_tie_accommodation_staff_user (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_refund_requests (
  id CHAR(36) NOT NULL PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  reservation_id CHAR(36) NOT NULL,
  requested_by VARCHAR(30) NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MWK',
  reason VARCHAR(1000) NOT NULL,
  risk_level ENUM('STANDARD','EXCEPTION') NOT NULL DEFAULT 'STANDARD',
  status ENUM('PENDING','APPROVED','REJECTED','EXECUTED','FAILED') NOT NULL DEFAULT 'PENDING',
  reviewed_by VARCHAR(30) NULL,
  review_note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_refund_property (property_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_audit_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id CHAR(36) NOT NULL,
  actor_id VARCHAR(30) NOT NULL,
  action_key VARCHAR(100) NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id VARCHAR(64) NOT NULL,
  correlation_id VARCHAR(80) NOT NULL,
  before_state JSON NULL,
  after_state JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_accommodation_audit_property (property_id, created_at),
  KEY idx_tie_accommodation_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_accommodation_idempotency (
  actor_id VARCHAR(30) NOT NULL,
  action_key VARCHAR(80) NOT NULL,
  idempotency_key VARCHAR(100) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  response_json JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (actor_id, action_key, idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO tie_accommodation_properties
  (id, vendor_id, service_profile_id, listing_id, name, description, address, image_url, status)
SELECT UUID(), l.vendor_id,
       (SELECT p.id FROM tie_vendor_service_profiles p WHERE p.vendor_id=l.vendor_id AND p.profile_type='accommodation' ORDER BY p.updated_at DESC LIMIT 1),
       l.id, l.title, l.description, l.location, l.image, IF(l.is_active=1,'ACTIVE','PAUSED')
FROM listings l WHERE l.listing_type='accommodation';

UPDATE room_types rt INNER JOIN tie_accommodation_properties p ON p.listing_id=rt.listing_id
SET rt.property_id=p.id WHERE rt.property_id IS NULL;

INSERT IGNORE INTO tie_accommodation_cancellation_policies
  (id, property_id, name, free_cancel_hours, penalty_percent, no_show_percent, is_active)
SELECT UUID(), p.id, 'Standard flexible policy', 24, 100.00, 100.00, 1
FROM tie_accommodation_properties p
WHERE NOT EXISTS (SELECT 1 FROM tie_accommodation_cancellation_policies cp WHERE cp.property_id=p.id);

INSERT INTO tie_accommodation_rate_plans
  (id, property_id, room_type_id, cancellation_policy_id, name, base_rate, booking_mode, payment_mode, minimum_stay, maximum_stay, is_active)
SELECT UUID(), p.id, rt.id, cp.id, 'Standard rate', rt.price_per_night, 'INSTANT', 'FULL', 1, 30, 1
FROM tie_accommodation_properties p
INNER JOIN room_types rt ON rt.listing_id=p.listing_id
INNER JOIN tie_accommodation_cancellation_policies cp ON cp.property_id=p.id AND cp.is_active=1
WHERE NOT EXISTS (SELECT 1 FROM tie_accommodation_rate_plans rp WHERE rp.property_id=p.id AND rp.room_type_id=rt.id);

INSERT IGNORE INTO tie_accommodation_staff_memberships
  (id, property_id, user_id, invited_email, role_key, status, invited_by, accepted_at)
SELECT UUID(), p.id, p.vendor_id, u.email, 'OWNER', 'ACTIVE', p.vendor_id, UTC_TIMESTAMP()
FROM tie_accommodation_properties p INNER JOIN users u ON u.id=p.vendor_id;
