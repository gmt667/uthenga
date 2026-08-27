-- Uthenga Payment Engine — Vendor Payment Profile.
-- The vendor supplies ONLY business-side settlement information (which bank/
-- mobile-money account their payouts land in). PayChangu secret keys, API
-- secrets, and webhook secrets are never collected here or anywhere vendor-
-- facing — those live solely in the platform's own centralized gateway
-- config (config.php / includes/tie/Payment.php), per the platform's
-- "vendors never see provider credentials" rule.
CREATE TABLE IF NOT EXISTS uthenga_vendor_payment_profiles (
    vendor_id VARCHAR(30) PRIMARY KEY,
    settlement_method VARCHAR(20) NOT NULL DEFAULT 'bank',
    bank_name VARCHAR(120) NULL,
    bank_account_name VARCHAR(160) NULL,
    bank_account_number VARCHAR(60) NULL,
    bank_branch VARCHAR(120) NULL,
    mobile_money_provider VARCHAR(20) NULL,
    mobile_money_number VARCHAR(30) NULL,
    verification_status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    verified_by VARCHAR(30) NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vendor_payment_profile_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
