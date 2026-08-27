-- ============================================================
-- Migration: 061_tie_bus_fleet.sql
-- Bus Operations Center Phase 3: a real, vendor-owned multi-bus
-- fleet + driver roster, re-keyed from Quick Taxi's Vehicle.php
-- (tie_driver_vehicles/tie_vehicle_documents/tie_vehicle_maintenance/
-- tie_vehicle_issues) which is one-vehicle-per-driver-user by
-- design and cannot hold a bus company's fleet. Same honest-data
-- discipline: only real expiry dates, a plain service log, an
-- issue log — no fabricated "vehicle health" score.
-- ============================================================

CREATE TABLE IF NOT EXISTS tie_bus_fleet_vehicles (
  id           CHAR(36)      NOT NULL PRIMARY KEY,
  vendor_id    VARCHAR(30)   NOT NULL,
  reg_number   VARCHAR(30)   NOT NULL,
  make_model   VARCHAR(120)  NOT NULL,
  capacity     INT UNSIGNED  NOT NULL DEFAULT 0,
  status       ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
  photo_url    VARCHAR(500)  NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bus_fleet_vehicles_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One row per document type — re-saving a document replaces the prior
-- expiry rather than accumulating history, matching tie_vehicle_documents.
CREATE TABLE IF NOT EXISTS tie_bus_fleet_documents (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id    CHAR(36)      NOT NULL,
  document_type VARCHAR(40)   NOT NULL,
  expiry_date   DATE          NOT NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bus_fleet_documents (vehicle_id, document_type),
  CONSTRAINT fk_bus_fleet_documents_vehicle FOREIGN KEY (vehicle_id) REFERENCES tie_bus_fleet_vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_bus_fleet_maintenance (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id   CHAR(36)      NOT NULL,
  service_type VARCHAR(120)  NOT NULL,
  serviced_at  DATE          NOT NULL,
  mileage_km   INT UNSIGNED  NULL,
  notes        VARCHAR(500)  NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_bus_fleet_maintenance_vehicle (vehicle_id, serviced_at),
  CONSTRAINT fk_bus_fleet_maintenance_vehicle FOREIGN KEY (vehicle_id) REFERENCES tie_bus_fleet_vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_bus_fleet_issues (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id   CHAR(36)      NOT NULL,
  category     VARCHAR(40)   NOT NULL,
  description  VARCHAR(1000) NOT NULL,
  severity     ENUM('low','medium','critical') NOT NULL DEFAULT 'low',
  status       ENUM('open','resolved') NOT NULL DEFAULT 'open',
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at  DATETIME      NULL,
  KEY idx_bus_fleet_issues_vehicle (vehicle_id, status, created_at),
  CONSTRAINT fk_bus_fleet_issues_vehicle FOREIGN KEY (vehicle_id) REFERENCES tie_bus_fleet_vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- A plain operational roster — not a platform login (bus drivers have no
-- reason to need a Uthenga user account), unlike Events' tie_staff system.
CREATE TABLE IF NOT EXISTS tie_bus_drivers (
  id              CHAR(36)      NOT NULL PRIMARY KEY,
  vendor_id       VARCHAR(30)   NOT NULL,
  name            VARCHAR(120)  NOT NULL,
  phone           VARCHAR(30)   NULL,
  license_number  VARCHAR(60)   NULL,
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bus_drivers_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Optional assignment of a real bus + driver to a scheduled departure.
ALTER TABLE tie_bus_departures
  ADD COLUMN IF NOT EXISTS vehicle_id CHAR(36) NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS driver_id  CHAR(36) NULL AFTER vehicle_id;
