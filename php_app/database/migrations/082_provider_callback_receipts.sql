-- Phase D2.3: durable receipt and replay-control store for provider callbacks.
-- Apply through the normal migration runner. Web requests never run migrations.
CREATE TABLE IF NOT EXISTS uthenga_provider_callback_receipts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(64) NOT NULL,
    event_identity VARCHAR(191) NOT NULL,
    event_type VARCHAR(80) NULL,
    payment_reference VARCHAR(191) NULL,
    payload_digest CHAR(64) NOT NULL,
    processing_status VARCHAR(24) NOT NULL DEFAULT 'RECEIVED',
    failure_code VARCHAR(80) NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    UNIQUE KEY uq_provider_callback_event (provider, event_identity),
    KEY idx_provider_callback_reference (provider, payment_reference),
    KEY idx_provider_callback_status (processing_status, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
