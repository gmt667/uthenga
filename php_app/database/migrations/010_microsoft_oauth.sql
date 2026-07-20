-- ================================================================
-- Migration: 010_microsoft_oauth.sql
-- Adds Microsoft OAuth support to social_accounts.
-- ================================================================

ALTER TABLE social_accounts
  MODIFY provider ENUM('google','facebook','microsoft') NOT NULL;
