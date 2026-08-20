-- 040: Event document workspace — a controlled document repository for the
--      event business. Documents are not plain blobs: every row carries the
--      metadata required by an enterprise document system (version, status,
--      category, event scope, creator, tags, retention) and a full audit
--      trail of versions, shares and activity.
--
--      Tenancy: vendor_id on every row; all access goes through the service
--      layer which enforces vendor scoping. File bytes live outside the
--      database in storage, keyed by a random storage_key that is never
--      exposed publicly — the API serves preview/download behind authentication.
--
--      Live-data principle: generated reports (attendance, finance, ticket
--      sales, customer summaries) are rendered from the operational tables at
--      generation time, then stored here with a source_label that records
--      which system produced them (analytics, finance, check-in, tickets…).

CREATE TABLE IF NOT EXISTS tie_docs_documents (
    id              CHAR(36)      NOT NULL PRIMARY KEY,
    vendor_id       VARCHAR(40)   NOT NULL,
    name            VARCHAR(220)  NOT NULL,
    doc_type        VARCHAR(12)   NOT NULL DEFAULT 'PDF',
    category        ENUM('EVENTS','FINANCE','TICKETS','VENUES','MARKETING','CUSTOMERS','STAFF','BUSINESS','REPORTS','OTHER') NOT NULL DEFAULT 'OTHER',
    event_id        VARCHAR(40)   NULL,
    listing_id      VARCHAR(40)   NULL,
    size_bytes      INT UNSIGNED  NOT NULL DEFAULT 0,
    mime            VARCHAR(120)  NOT NULL DEFAULT 'application/octet-stream',
    storage_key     VARCHAR(255)  NOT NULL,
    content_hash    CHAR(64)      NULL,
    status          ENUM('DRAFT','PENDING_REVIEW','APPROVED','FINAL','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
    version         INT UNSIGNED  NOT NULL DEFAULT 1,
    source_kind     ENUM('UPLOAD','GENERATED','TEMPLATE') NOT NULL DEFAULT 'UPLOAD',
    source_label    VARCHAR(160)  NULL,
    source_ref      VARCHAR(80)   NULL,
    template_id     CHAR(36)      NULL,
    tags            JSON          NULL,
    locked_by       VARCHAR(80)   NULL,
    locked_at       DATETIME      NULL,
    retention_months INT UNSIGNED NULL,
    legal_hold      TINYINT(1)    NOT NULL DEFAULT 0,
    created_by      VARCHAR(80)   NOT NULL DEFAULT 'Organizer',
    created_by_id   VARCHAR(40)   NULL,
    last_viewed_at  DATETIME      NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tdoc_vendor_status (vendor_id, status),
    INDEX idx_tdoc_vendor_category (vendor_id, category),
    INDEX idx_tdoc_vendor_event (vendor_id, event_id),
    INDEX idx_tdoc_vendor_updated (vendor_id, updated_at),
    INDEX idx_tdoc_vendor_type (vendor_id, doc_type),
    INDEX idx_tdoc_created_by (vendor_id, created_by_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_docs_versions (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id   CHAR(36)     NOT NULL,
    version       INT UNSIGNED NOT NULL,
    name          VARCHAR(220) NOT NULL,
    size_bytes    INT UNSIGNED NOT NULL DEFAULT 0,
    storage_key   VARCHAR(255) NOT NULL,
    note          VARCHAR(220) NULL,
    created_by    VARCHAR(80)  NOT NULL DEFAULT 'Organizer',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tdocv (document_id, version),
    INDEX idx_tdocv_doc (document_id, created_at),
    CONSTRAINT fk_tdocv_doc FOREIGN KEY (document_id)
        REFERENCES tie_docs_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_docs_activity (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id     VARCHAR(40)  NOT NULL,
    document_id   CHAR(36)     NOT NULL,
    action        VARCHAR(40)  NOT NULL,
    actor_name    VARCHAR(80)  NOT NULL DEFAULT 'Organizer',
    details       JSON         NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tdoca_vendor (vendor_id, created_at),
    INDEX idx_tdoca_doc (document_id, created_at),
    CONSTRAINT fk_tdoca_doc FOREIGN KEY (document_id)
        REFERENCES tie_docs_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_docs_shares (
    id            CHAR(36)     NOT NULL PRIMARY KEY,
    vendor_id     VARCHAR(40)  NOT NULL,
    document_id   CHAR(36)     NOT NULL,
    sharee_name   VARCHAR(120) NOT NULL,
    permission    ENUM('VIEW','COMMENT','EDIT') NOT NULL DEFAULT 'VIEW',
    created_by    VARCHAR(80)  NOT NULL DEFAULT 'Organizer',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tdocs_share (document_id, sharee_name),
    INDEX idx_tdocs_vendor (vendor_id),
    CONSTRAINT fk_tdocs_doc FOREIGN KEY (document_id)
        REFERENCES tie_docs_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_docs_templates (
    id            CHAR(36)     NOT NULL PRIMARY KEY,
    vendor_id     VARCHAR(40)  NOT NULL,
    title         VARCHAR(140) NOT NULL,
    category      VARCHAR(40)  NOT NULL DEFAULT 'EVENTS',
    doc_type      VARCHAR(12)  NOT NULL DEFAULT 'PDF',
    description   VARCHAR(240) NOT NULL DEFAULT '',
    body          MEDIUMTEXT   NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    usage_count   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tdoct_vendor (vendor_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;