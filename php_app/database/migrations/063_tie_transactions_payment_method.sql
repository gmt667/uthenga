-- ============================================================
-- Migration: 063_tie_transactions_payment_method.sql
-- Tracks exactly which saved tie_customer_payment_methods row a
-- transaction was charged against, so a successful mobile-money
-- charge can flip that specific method from pending_verification
-- to active/verified.
-- ============================================================

ALTER TABLE transactions
  ADD COLUMN payment_method_id CHAR(36) NULL DEFAULT NULL COMMENT 'tie_customer_payment_methods.id used for this charge, if any' AFTER payment_channel;
