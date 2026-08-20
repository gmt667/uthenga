-- 038: Analytics intelligence — stores organizer alert preferences only.
--      All facts are derived live from operational tables (bookings, event_tickets,
--      checkin_scans, event_analytics, events_marketing_*). No analytical dupes.

CREATE TABLE IF NOT EXISTS tie_analytics_alert_config (
    id               CHAR(36)     NOT NULL PRIMARY KEY,
    vendor_id        VARCHAR(40)  NOT NULL,
    sales_target     INT          NOT NULL DEFAULT 0,
    ticket_cap       INT          NOT NULL DEFAULT 0,
    attendance_rate  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    notify_sales     TINYINT(1)   NOT NULL DEFAULT 1,
    notify_velocity  TINYINT(1)   NOT NULL DEFAULT 1,
    notify_inventory TINYINT(1)   NOT NULL DEFAULT 1,
    notify_attendance TINYINT(1)  NOT NULL DEFAULT 1,
    notify_revenue   TINYINT(1)   NOT NULL DEFAULT 1,
    notify_customers TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_analert_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;