-- Privacy-safe TIE operational telemetry. No user IDs, prompts, coordinates,
-- marketplace records, or payment data are stored in these tables.
CREATE TABLE IF NOT EXISTS tie_metric_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metric VARCHAR(80) NOT NULL,
    value DECIMAL(18,4) NOT NULL DEFAULT 0,
    request_id VARCHAR(100) NOT NULL,
    module_name VARCHAR(60) NULL,
    feature_name VARCHAR(60) NULL,
    provider_name VARCHAR(80) NULL,
    model_name VARCHAR(120) NULL,
    status_name VARCHAR(60) NULL,
    quality_name VARCHAR(40) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tie_metric_created (created_at),
    KEY idx_tie_metric_name_created (metric, created_at),
    KEY idx_tie_metric_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tie_request_traces (
    request_id VARCHAR(100) PRIMARY KEY,
    module_name VARCHAR(60) NULL,
    feature_name VARCHAR(60) NULL,
    status_name VARCHAR(60) NULL,
    provider_name VARCHAR(80) NULL,
    model_name VARCHAR(120) NULL,
    duration_ms DECIMAL(12,2) NULL,
    error_type VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tie_trace_created (created_at),
    KEY idx_tie_trace_module_created (module_name, created_at),
    KEY idx_tie_trace_status_created (status_name, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
