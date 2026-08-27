-- ============================================================
-- Migration: 060_tie_bus_operations.sql
-- Bus Operations Center: scheduled departures on top of the
-- existing listings('transport')/seat_classes tables, real
-- per-ticket QR issuance (mirrors event_tickets), and a
-- vendor-scoped boarding scan session (mirrors gate_sessions/
-- gate_scans, which are admin+event only).
-- ============================================================

-- booking_items is part of install/setup.sql's schema (and is already the
-- table transport.php's real upcoming-tickets query joins against) but is
-- missing from this environment's live database — create it here since bus
-- ticket issuance depends on it.
CREATE TABLE IF NOT EXISTS booking_items (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id   VARCHAR(20) NOT NULL,
  vendor_id    VARCHAR(30) NULL,
  item_type    VARCHAR(40) NOT NULL,
  reference_id VARCHAR(64) NOT NULL,
  item_name    VARCHAR(255) NOT NULL,
  quantity     INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price   DECIMAL(15,2) NOT NULL,
  subtotal     DECIMAL(15,2) NOT NULL,
  service_date DATE NULL,
  metadata     JSON NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_booking_items_booking (booking_id),
  KEY idx_booking_items_vendor (vendor_id),
  KEY idx_booking_items_type (item_type),
  KEY idx_booking_items_reference (reference_id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_bus_departures (
  id                CHAR(36)      NOT NULL PRIMARY KEY,
  listing_id        VARCHAR(30)   NOT NULL,
  departure_at      DATETIME      NOT NULL,
  arrival_estimate  DATETIME      NULL,
  status            ENUM('scheduled','boarding','departed','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  notes             VARCHAR(500)  NULL,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bus_departures_listing (listing_id, departure_at),
  KEY idx_bus_departures_status (status),
  CONSTRAINT fk_bus_departures_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_bus_departure_seats (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  departure_id    CHAR(36)      NOT NULL,
  seat_class_id   BIGINT UNSIGNED NOT NULL,
  class_name      VARCHAR(80)   NOT NULL COMMENT 'Snapshot of seat_classes.class_name at creation',
  price           DECIMAL(15,2) NOT NULL COMMENT 'Snapshot of seat_classes.price at creation — never retroactively changes a sold ticket',
  total_seats     INT UNSIGNED  NOT NULL DEFAULT 0,
  remaining_seats INT UNSIGNED  NOT NULL DEFAULT 0,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bus_dep_seats_departure (departure_id),
  KEY idx_bus_dep_seats_class (seat_class_id),
  CONSTRAINT fk_bus_dep_seats_departure FOREIGN KEY (departure_id) REFERENCES tie_bus_departures(id) ON DELETE CASCADE,
  CONSTRAINT fk_bus_dep_seats_class FOREIGN KEY (seat_class_id) REFERENCES seat_classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_bus_tickets (
  id                     VARCHAR(40)   NOT NULL PRIMARY KEY COMMENT 'Human-readable code, e.g. UTH-BUS-A1B2C3',
  booking_id             VARCHAR(20)   NOT NULL,
  departure_id           CHAR(36)      NOT NULL,
  departure_seat_id      BIGINT UNSIGNED NOT NULL,
  passenger_name         VARCHAR(150)  NOT NULL,
  passenger_phone        VARCHAR(30)   NULL,
  seat_label             VARCHAR(20)   NULL,
  qr_token               CHAR(48)      NOT NULL,
  verification_signature VARCHAR(64)   NOT NULL,
  status                 ENUM('issued','boarded','cancelled') NOT NULL DEFAULT 'issued',
  boarded_at             DATETIME      NULL,
  boarded_by             VARCHAR(30)   NULL,
  created_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_bus_tickets_booking (booking_id),
  KEY idx_bus_tickets_departure (departure_id),
  KEY idx_bus_tickets_qr (qr_token),
  KEY idx_bus_tickets_status (status),
  CONSTRAINT fk_bus_tickets_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_bus_tickets_departure FOREIGN KEY (departure_id) REFERENCES tie_bus_departures(id) ON DELETE CASCADE,
  CONSTRAINT fk_bus_tickets_departure_seat FOREIGN KEY (departure_seat_id) REFERENCES tie_bus_departure_seats(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_bus_boarding_sessions (
  id              CHAR(36)      NOT NULL PRIMARY KEY,
  departure_id    CHAR(36)      NOT NULL,
  vendor_id       VARCHAR(30)   NOT NULL,
  started_by      VARCHAR(30)   NOT NULL,
  status          ENUM('active','stopped') NOT NULL DEFAULT 'active',
  started_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  stopped_at      DATETIME      NULL,
  total_scanned   INT UNSIGNED  NOT NULL DEFAULT 0,
  total_valid     INT UNSIGNED  NOT NULL DEFAULT 0,
  total_invalid   INT UNSIGNED  NOT NULL DEFAULT 0,
  total_duplicate INT UNSIGNED  NOT NULL DEFAULT 0,
  KEY idx_bus_boarding_sessions_departure (departure_id),
  KEY idx_bus_boarding_sessions_vendor (vendor_id, status),
  CONSTRAINT fk_bus_boarding_sessions_departure FOREIGN KEY (departure_id) REFERENCES tie_bus_departures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_bus_boarding_scans (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id    CHAR(36)      NOT NULL,
  code_entered  VARCHAR(100)  NOT NULL,
  ticket_id     VARCHAR(40)   NULL,
  scan_result   ENUM('valid','invalid','duplicate') NOT NULL,
  method        ENUM('manual','qr') NOT NULL DEFAULT 'manual',
  scanned_by    VARCHAR(30)   NOT NULL,
  scanned_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes         VARCHAR(255)  NULL,
  KEY idx_bus_boarding_scans_session (session_id),
  KEY idx_bus_boarding_scans_ticket (ticket_id),
  CONSTRAINT fk_bus_boarding_scans_session FOREIGN KEY (session_id) REFERENCES tie_bus_boarding_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
