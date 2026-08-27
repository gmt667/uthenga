-- Quick Taxi Operations: Vehicle. There is no existing "driver's assigned
-- vehicle" entity anywhere in this codebase — the legacy `vehicles` table
-- belongs to an unrelated transport-marketplace cluster (transport_providers/
-- routes/vehicles) that no TIE module joins against, and driver_profiles has
-- no vehicle detail columns beyond an unused JSON `vehicle_types` array. This
-- migration gives Quick Taxi drivers a real, self-managed vehicle profile.
-- There is no engine/brake/tyre telemetry system in this codebase, so this
-- deliberately does not fabricate a "vehicle health" score — only genuinely
-- driver-reported data (documents with real expiry dates, a mileage/service
-- log, issue reports) is modelled.

CREATE TABLE IF NOT EXISTS tie_driver_vehicles (
  driver_user_id VARCHAR(30) NOT NULL PRIMARY KEY,
  make_model VARCHAR(120) NOT NULL,
  plate_number VARCHAR(30) NOT NULL,
  colour VARCHAR(40) NULL,
  category VARCHAR(40) NULL,
  photo_url VARCHAR(500) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  current_mileage_km INT UNSIGNED NULL,
  mileage_updated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One row per document type — re-adding a document replaces the prior expiry
-- rather than accumulating history, since only the current expiry matters.
CREATE TABLE IF NOT EXISTS tie_vehicle_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_user_id VARCHAR(30) NOT NULL,
  document_type VARCHAR(40) NOT NULL,
  expiry_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tie_vehicle_documents (driver_user_id, document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_vehicle_maintenance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_user_id VARCHAR(30) NOT NULL,
  service_type VARCHAR(120) NOT NULL,
  serviced_at DATE NOT NULL,
  mileage_km INT UNSIGNED NULL,
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_vehicle_maintenance_driver (driver_user_id, serviced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_vehicle_issues (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_user_id VARCHAR(30) NOT NULL,
  category VARCHAR(40) NOT NULL,
  description VARCHAR(1000) NOT NULL,
  severity ENUM('low','medium','critical') NOT NULL DEFAULT 'low',
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  KEY idx_tie_vehicle_issues_driver (driver_user_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
