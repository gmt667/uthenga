-- Phase D2.4: adds processing detail without changing a potentially shared 082.
CREATE TABLE IF NOT EXISTS uthenga_provider_callback_processing (
    receipt_id BIGINT NOT NULL PRIMARY KEY,
    payment_intent_id VARCHAR(64) NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NULL,
    safe_metadata_json LONGTEXT NULL,
    KEY idx_callback_processing_intent (payment_intent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS uthenga_provider_callback_commits (
    payment_intent_id VARCHAR(64) NOT NULL,
    receipt_id BIGINT NOT NULL,
    committed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_intent_id),
    UNIQUE KEY uq_callback_commit_receipt (receipt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
