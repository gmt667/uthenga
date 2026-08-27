-- ============================================================
-- Migration: 066_tie_bus_ticket_templates.sql
-- One company-wide ticket branding template per vendor — the
-- customer-facing bus ticket's visual style (logo, accent color,
-- footer message, contact info). Operational fields (ticket ID,
-- passenger, route, seat, QR) are never part of this table —
-- they always come straight from tie_bus_tickets/bookings.
-- ============================================================

CREATE TABLE IF NOT EXISTS tie_bus_ticket_templates (
  vendor_id       VARCHAR(30)  NOT NULL PRIMARY KEY,
  template_style  ENUM('classic_blue','modern_card','minimal_white','premium_dark','mobile_wallet') NOT NULL DEFAULT 'classic_blue',
  logo_url        VARCHAR(500) NULL,
  accent_color    VARCHAR(7)   NULL COMMENT 'Hex color override for template accents; NULL uses the template''s own default',
  footer_message  VARCHAR(300) NULL,
  contact_phone   VARCHAR(30)  NULL,
  contact_email   VARCHAR(180) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bus_ticket_templates_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
