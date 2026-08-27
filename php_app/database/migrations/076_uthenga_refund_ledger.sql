-- Uthenga Payment Engine — Refund Ledger
-- Source of truth for how much has been refunded against a payment intent so
-- far (SUM(amount) WHERE intent_ref = ?), plus the intent_ref link needed on
-- the two existing (pre-engine) refund-request tables so a refund approval
-- can resolve back to the payment intent it must reverse.

CREATE TABLE IF NOT EXISTS uthenga_refund_ledger (
    id VARCHAR(40) PRIMARY KEY,
    intent_ref VARCHAR(32) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    actor_id VARCHAR(30) NOT NULL,
    source_type VARCHAR(20) NOT NULL,
    source_request_id VARCHAR(60) NULL,
    receipt_number VARCHAR(64) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_refund_intent (intent_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE tie_accommodation_refund_requests ADD COLUMN IF NOT EXISTS intent_ref VARCHAR(32) NULL AFTER reservation_id;

-- event_ticket_refunds was never a tracked migration (created ad hoc by
-- api/tie/vendor/events/_schema_tickets.php) — formalize it here so a fresh
-- environment gets it, mirroring that script's existing columns exactly.
CREATE TABLE IF NOT EXISTS event_ticket_refunds (
    id VARCHAR(30) NOT NULL,
    listing_id VARCHAR(30) NOT NULL,
    booking_id VARCHAR(20) DEFAULT NULL,
    ticket_id VARCHAR(40) DEFAULT NULL,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'MWK',
    reason VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    requested_by VARCHAR(30) DEFAULT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME DEFAULT NULL,
    decided_by VARCHAR(30) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_refund_listing (listing_id),
    KEY idx_refund_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE event_ticket_refunds ADD COLUMN IF NOT EXISTS intent_ref VARCHAR(32) NULL AFTER booking_id;
