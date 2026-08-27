-- Uthenga Payment Engine — Platform Settlement.
-- Where UTHENGA ITSELF banks its own commission revenue (distinct from
-- uthenga_vendor_payment_profiles, which is each vendor's own payout
-- destination). No real payout/disbursement API exists anywhere in this
-- codebase to build an automated "click to withdraw" on top of — this is
-- an honest manual-settlement record: an admin transfers the money outside
-- the app, then records that it happened, exactly mirroring the refund
-- system's own manual-settlement design.

CREATE TABLE IF NOT EXISTS uthenga_platform_settlement_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_type VARCHAR(20) NOT NULL DEFAULT 'bank',
    bank_name VARCHAR(120) NULL,
    account_name VARCHAR(160) NULL,
    account_number VARCHAR(60) NULL,
    mobile_money_provider VARCHAR(20) NULL,
    mobile_money_number VARCHAR(30) NULL,
    updated_by VARCHAR(30) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS uthenga_platform_settlements (
    id VARCHAR(40) PRIMARY KEY,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    destination_snapshot TEXT NOT NULL,
    reference_note VARCHAR(500) NULL,
    actor_id VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_platform_settlement_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
