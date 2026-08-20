-- 037: Event finance operations — settlements, withdrawals, payout accounts,
--      finance documents, and reconciliation tracking for the organizers' finance console.
--      Money facts continue to live in bookings / transactions / event_ticket_refunds;
--      these tables record the settlement layer and financial operations on top of them.

CREATE TABLE IF NOT EXISTS tie_payment_accounts (
    id               CHAR(36)     NOT NULL PRIMARY KEY,
    vendor_id        VARCHAR(40)  NOT NULL,
    method           ENUM('BANK','MOBILE_MONEY') NOT NULL DEFAULT 'BANK',
    label            VARCHAR(80)  NOT NULL DEFAULT 'Payout account',
    account_name     VARCHAR(120) NOT NULL DEFAULT '',
    account_number   VARCHAR(40)  NOT NULL DEFAULT '',
    provider         VARCHAR(40)  NULL,
    is_default       TINYINT(1)   NOT NULL DEFAULT 0,
    is_verified      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fin_acc_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tie_event_settlement_batches (
    id               CHAR(36)     NOT NULL PRIMARY KEY,
    vendor_id        VARCHAR(40)  NOT NULL,
    period_start     DATE         NOT NULL,
    period_end       DATE         NOT NULL,
    gross_amount     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    platform_fee     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    processing_fee   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    refunds_total    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    net_amount       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status           ENUM('PENDING','ELIGIBLE','PAID','CANCELLED') NOT NULL DEFAULT 'PENDING',
    paid_at          DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_settle_vendor (vendor_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tie_event_withdrawals (
    id                    CHAR(36)     NOT NULL PRIMARY KEY,
    vendor_id             VARCHAR(40)  NOT NULL,
    settlement_batch_id   CHAR(36)     NULL,
    amount                DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    method                VARCHAR(20)  NOT NULL DEFAULT 'BANK',
    destination_label     VARCHAR(120) NOT NULL DEFAULT '',
    account_number_masked VARCHAR(40)  NOT NULL DEFAULT '',
    status                ENUM('REQUESTED','PROCESSING','PAID','REJECTED') NOT NULL DEFAULT 'REQUESTED',
    reference             VARCHAR(64)  NULL,
    notes                 VARCHAR(255) NULL,
    requested_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at          DATETIME     NULL,
    processed_by          VARCHAR(40)  NULL,
    INDEX idx_with_vendor (vendor_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tie_finance_documents (
    id            CHAR(36)     NOT NULL PRIMARY KEY,
    vendor_id     VARCHAR(40)  NOT NULL,
    doc_type      ENUM('SETTLEMENT','COMMISSION','REFUND','EVENT_STATEMENT','RECEIPT') NOT NULL,
    reference     VARCHAR(64)  NOT NULL DEFAULT '',
    event_id      VARCHAR(40)  NULL,
    period_start  DATE         NULL,
    period_end    DATE         NULL,
    payload       LONGTEXT     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fin_doc_vendor (vendor_id, doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tie_reconciliation_runs (
    id               CHAR(36)     NOT NULL PRIMARY KEY,
    vendor_id        VARCHAR(40)  NOT NULL,
    result_status    ENUM('BALANCED','ISSUES') NOT NULL DEFAULT 'BALANCED',
    expected_amount  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    recorded_amount  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    difference       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    exception_count  INT          NOT NULL DEFAULT 0,
    summary          LONGTEXT     NULL,
    checked_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recon_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tie_reconciliation_exceptions (
    id               CHAR(36)     NOT NULL PRIMARY KEY,
    vendor_id        VARCHAR(40)  NOT NULL,
    run_id           CHAR(36)     NULL,
    category         ENUM('TICKET','PAYMENT','REFUND','FEE','SETTLEMENT') NOT NULL,
    reference        VARCHAR(64)  NOT NULL DEFAULT '',
    expected_amount  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    recorded_amount  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status           ENUM('OPEN','RESOLVED','IGNORED') NOT NULL DEFAULT 'OPEN',
    resolution_note  VARCHAR(255) NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at      DATETIME     NULL,
    INDEX idx_recon_exc_vendor (vendor_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;