-- Additive migration for vendor withdrawal requests and payout tracking.
-- Safe to run on existing databases; columns are only added.

ALTER TABLE withdrawal_requests
  ADD COLUMN request_reference VARCHAR(150) NULL AFTER id,
  ADD COLUMN charges DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER amount;

ALTER TABLE vendor_payouts
  ADD COLUMN charges DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER amount;
