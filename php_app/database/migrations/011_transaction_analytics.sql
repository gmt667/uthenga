-- ============================================================
-- Migration: 011_transaction_analytics.sql
-- Records payment outcomes, revenue, and booking activity for admin analytics.
-- ============================================================

CREATE TABLE IF NOT EXISTS transaction_analytics (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transaction_reference  VARCHAR(60)     NOT NULL,
  booking_id             VARCHAR(30)     NULL,
  user_id                VARCHAR(30)     NULL,
  payment_method         VARCHAR(100)    NOT NULL,
  payment_status         VARCHAR(30)     NOT NULL DEFAULT 'pending',
  amount                 DECIMAL(12,2)   NOT NULL DEFAULT 0,
  booking_count          INT UNSIGNED    NOT NULL DEFAULT 0,
  event_type             VARCHAR(30)     NOT NULL DEFAULT 'created',
  event_timestamp        DATETIME        NOT NULL,
  event_date             DATE            NOT NULL,
  event_month            CHAR(7)         NOT NULL,
  created_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_transaction_analytics_reference (transaction_reference),
  KEY idx_transaction_analytics_event_date (event_date),
  KEY idx_transaction_analytics_event_month (event_month),
  KEY idx_transaction_analytics_payment_method (payment_method),
  KEY idx_transaction_analytics_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
