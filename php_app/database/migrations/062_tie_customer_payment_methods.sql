-- ============================================================
-- Migration: 062_tie_customer_payment_methods.sql
-- Real, customer-configurable payment credentials (PayChangu
-- Direct Charge Mobile Money + Bank Transfer) required before a
-- customer can complete a bus ticket purchase. No card data is
-- ever stored here — PayChangu has no card tokenization, so card
-- charges stay out of scope entirely (would require raw PAN/CVV
-- every time, putting us in PCI-DSS scope we don't have).
-- ============================================================

CREATE TABLE IF NOT EXISTS tie_customer_payment_methods (
  id                 CHAR(36)      NOT NULL PRIMARY KEY,
  customer_id        VARCHAR(30)   NOT NULL,
  channel            ENUM('mobile_money','bank_transfer') NOT NULL,
  mobile_number      VARCHAR(30)   NULL COMMENT 'E.164-ish, mobile_money channel only',
  operator_ref_id    VARCHAR(64)   NULL COMMENT 'PayChangu mobile-money operator ref_id (GET /mobile-money), mobile_money only',
  operator_name      VARCHAR(80)   NULL COMMENT 'Display label snapshot, e.g. Airtel Money, TNM Mpamba',
  bank_name          VARCHAR(120)  NULL COMMENT 'bank_transfer only — populated once PayChangu provisions the real virtual account',
  account_number     VARCHAR(60)   NULL COMMENT 'bank_transfer only — PayChangu virtual account number',
  account_name       VARCHAR(120)  NULL COMMENT 'bank_transfer only — PayChangu virtual account holder name',
  provider_reference VARCHAR(120)  NULL COMMENT 'PayChangu customer_ref or charge reference tied to this method',
  status             ENUM('pending_verification','pending_provision','active','disabled') NOT NULL DEFAULT 'pending_verification',
  is_default         TINYINT(1)    NOT NULL DEFAULT 0,
  verified_at        DATETIME      NULL COMMENT 'Set the first time a real charge through this method succeeds — never fabricated up front',
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cpm_customer (customer_id),
  KEY idx_cpm_customer_status (customer_id, status),
  UNIQUE KEY uniq_cpm_mobile (customer_id, channel, mobile_number),
  CONSTRAINT fk_cpm_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Lets reconcilePayment()/receiveWebhook() dispatch to the right PayChangu
-- verification call per transaction without parsing JSON metadata.
ALTER TABLE transactions
  ADD COLUMN payment_channel VARCHAR(30) NULL DEFAULT NULL COMMENT 'mobile_money|bank_transfer|hosted_checkout — which PayChangu rail this transaction used' AFTER gateway_ref;
