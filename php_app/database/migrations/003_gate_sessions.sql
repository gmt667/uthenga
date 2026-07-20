-- ============================================================
-- Uthenga Marketplace — Migration 003
-- Gate Session & QR Scan Tracking Tables
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- Run: mysql -u root -p uthenga_db < 003_gate_sessions.sql
-- ============================================================


-- ─── Gate Sessions ────────────────────────────────────────────────────────────
-- Tracks the lifecycle of an entry gate session for an event
CREATE TABLE IF NOT EXISTS gate_sessions (
  id             VARCHAR(30)   NOT NULL PRIMARY KEY,
  listing_id     VARCHAR(30)   NOT NULL COMMENT 'References listings.id',
  listing_title  VARCHAR(200)  NOT NULL,
  started_by     VARCHAR(30)   NOT NULL COMMENT 'Admin/organiser user id',
  started_name   VARCHAR(120)  NOT NULL,
  status         ENUM('active','paused','stopped') NOT NULL DEFAULT 'active',
  started_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paused_at      DATETIME      NULL,
  stopped_at     DATETIME      NULL,
  total_scanned  INT UNSIGNED  NOT NULL DEFAULT 0,
  total_valid    INT UNSIGNED  NOT NULL DEFAULT 0,
  total_invalid  INT UNSIGNED  NOT NULL DEFAULT 0,
  total_duplicate INT UNSIGNED NOT NULL DEFAULT 0,
  notes          TEXT          NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_gs_listing (listing_id),
  INDEX idx_gs_status (status),
  INDEX idx_gs_started_by (started_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Gate Scans ───────────────────────────────────────────────────────────────
-- Records each individual QR scan during a gate session
CREATE TABLE IF NOT EXISTS gate_scans (
  id             INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_id     VARCHAR(30)   NOT NULL COMMENT 'References gate_sessions.id',
  qr_code        VARCHAR(200)  NOT NULL COMMENT 'The scanned QR/ticket code',
  booking_id     VARCHAR(20)   NULL COMMENT 'Matched booking id if found',
  customer_name  VARCHAR(120)  NULL,
  ticket_type    VARCHAR(80)   NULL,
  scan_result    ENUM('valid','invalid','duplicate') NOT NULL,
  scanned_by     VARCHAR(30)   NULL,
  scanned_name   VARCHAR(120)  NULL,
  scanned_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes          VARCHAR(500)  NULL,
  INDEX idx_scan_session (session_id),
  INDEX idx_scan_qr (qr_code),
  INDEX idx_scan_booking (booking_id),
  INDEX idx_scan_result (scan_result),
  CONSTRAINT fk_scans_session FOREIGN KEY (session_id) REFERENCES gate_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
