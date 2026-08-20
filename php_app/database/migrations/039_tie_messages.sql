-- 039: Event communication workspace — conversations, messages, templates,
--      broadcasts/announcements, automations, internal notes and conversation tags.
--      No customer/event/order data is duplicated: conversation participants are
--      snapshots (denormalized name/email for resilience), while live business
--      facts (tickets, payments, check-in status, spend) are derived from the
--      operational tables at read time, mirroring the Analytics convention.
--
--      Conversations belong to a vendor. A conversation may be scoped to an event
--      (event_id) and/or a specific ticket/order (payload cards happen at the
--      message level). Tenancy is enforced by requiring vendor_id on every query.

CREATE TABLE IF NOT EXISTS tie_msg_conversations (
    id                 CHAR(36)      NOT NULL PRIMARY KEY,
    vendor_id          VARCHAR(40)   NOT NULL,
    customer_id        VARCHAR(40)   NOT NULL,
    customer_name      VARCHAR(120)  NOT NULL DEFAULT '',
    customer_email     VARCHAR(180)  NOT NULL DEFAULT '',
    channel            ENUM('UTHENGA','EMAIL','SMS') NOT NULL DEFAULT 'UTHENGA',
    subject            VARCHAR(200)  NOT NULL DEFAULT '',
    event_id           VARCHAR(40)   NULL,
    listing_id         VARCHAR(40)   NULL,
    status             ENUM('OPEN','PENDING','RESOLVED','ARCHIVED') NOT NULL DEFAULT 'OPEN',
    priority           ENUM('NORMAL','PRIORITY','URGENT') NOT NULL DEFAULT 'NORMAL',
    assigned_to        VARCHAR(80)   NULL,
    detected_topic     VARCHAR(60)   NULL,
    is_muted           TINYINT(1)    NOT NULL DEFAULT 0,
    unread_count       INT UNSIGNED  NOT NULL DEFAULT 0,
    last_message_at    DATETIME      NULL,
    last_message_preview VARCHAR(220) NOT NULL DEFAULT '',
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tmsg_conv_vendor_status (vendor_id, status),
    INDEX idx_tmsg_conv_vendor_priority (vendor_id, priority),
    INDEX idx_tmsg_conv_customer (vendor_id, customer_id),
    INDEX idx_tmsg_conv_event (vendor_id, event_id),
    INDEX idx_tmsg_conv_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_msg_messages (
    id               CHAR(36)   NOT NULL PRIMARY KEY,
    conversation_id  CHAR(36)   NOT NULL,
    sender_type      ENUM('CUSTOMER','ORGANIZER','SYSTEM') NOT NULL DEFAULT 'CUSTOMER',
    sender_name      VARCHAR(120) NOT NULL DEFAULT '',
    body             TEXT       NULL,
    payload          JSON       NULL,
    attachments      JSON       NULL,
    is_read          TINYINT(1) NOT NULL DEFAULT 0,
    created_at       DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tmsg_msg_conv (conversation_id, created_at),
    CONSTRAINT fk_tmsg_msg_conv FOREIGN KEY (conversation_id)
        REFERENCES tie_msg_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_msg_internal_notes (
    id               CHAR(36)   NOT NULL PRIMARY KEY,
    conversation_id  CHAR(36)   NOT NULL,
    author_name      VARCHAR(120) NOT NULL DEFAULT 'Organizer',
    body             TEXT       NOT NULL,
    created_at       DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tmsg_note_conv (conversation_id, created_at),
    CONSTRAINT fk_tmsg_note_conv FOREIGN KEY (conversation_id)
        REFERENCES tie_msg_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_msg_tags (
    id               CHAR(36)   NOT NULL PRIMARY KEY,
    vendor_id        VARCHAR(40) NOT NULL,
    conversation_id  CHAR(36)   NOT NULL,
    tag              VARCHAR(40) NOT NULL,
    created_at       DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tmsg_tag (vendor_id, conversation_id, tag),
    INDEX idx_tmsg_tags_vendor (vendor_id, tag),
    CONSTRAINT fk_tmsg_tags_conv FOREIGN KEY (conversation_id)
        REFERENCES tie_msg_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_msg_templates (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    vendor_id    VARCHAR(40)  NOT NULL,
    title        VARCHAR(120) NOT NULL,
    category     VARCHAR(60)  NOT NULL DEFAULT 'General',
    subject      VARCHAR(200) NOT NULL DEFAULT '',
    body         TEXT         NOT NULL,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    usage_count  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tmsg_tpl_vendor (vendor_id, category),
    INDEX idx_tmsg_tpl_vendor_active (vendor_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_msg_broadcasts (
    id               CHAR(36)      NOT NULL PRIMARY KEY,
    vendor_id        VARCHAR(40)   NOT NULL,
    kind             ENUM('BROADCAST','ANNOUNCEMENT') NOT NULL DEFAULT 'BROADCAST',
    event_id         VARCHAR(40)   NULL,
    listing_id       VARCHAR(40)   NULL,
    title            VARCHAR(200)  NOT NULL,
    subject          VARCHAR(200)  NOT NULL DEFAULT '',
    body             TEXT          NOT NULL,
    audience_config  JSON          NULL,
    recipient_count  INT UNSIGNED  NOT NULL DEFAULT 0,
    sent_count       INT UNSIGNED  NOT NULL DEFAULT 0,
    delivered_count  INT UNSIGNED  NOT NULL DEFAULT 0,
    opened_count     INT UNSIGNED  NOT NULL DEFAULT 0,
    failed_count     INT UNSIGNED  NOT NULL DEFAULT 0,
    channel          ENUM('UTHENGA','EMAIL','SMS') NOT NULL DEFAULT 'UTHENGA',
    status           ENUM('DRAFT','SCHEDULED','SENT','FAILED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    scheduled_at     DATETIME      NULL,
    sent_at          DATETIME      NULL,
    created_by       VARCHAR(80)   NOT NULL DEFAULT 'Organizer',
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tmsg_bc_vendor_status (vendor_id, status),
    INDEX idx_tmsg_bc_vendor_kind (vendor_id, kind),
    INDEX idx_tmsg_bc_event (vendor_id, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_msg_automations (
    id               CHAR(36)   NOT NULL PRIMARY KEY,
    vendor_id        VARCHAR(40) NOT NULL,
    event_id         VARCHAR(40) NULL,
    trigger_type     VARCHAR(40) NOT NULL,
    audience         VARCHAR(40) NOT NULL DEFAULT 'ALL_TICKET_HOLDERS',
    offset_hours     INT         NOT NULL DEFAULT 0,
    subject          VARCHAR(200) NOT NULL DEFAULT '',
    body             TEXT        NOT NULL,
    is_active        TINYINT(1)  NOT NULL DEFAULT 0,
    run_count        INT UNSIGNED NOT NULL DEFAULT 0,
    last_run_at      DATETIME    NULL,
    created_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tmsg_auto_vendor (vendor_id, is_active),
    INDEX idx_tmsg_auto_trigger (vendor_id, trigger_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_msg_audit_log (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id        VARCHAR(40)  NOT NULL,
    actor_id         VARCHAR(40)  NULL,
    actor_name       VARCHAR(120) NOT NULL DEFAULT 'Organizer',
    action           VARCHAR(60)  NOT NULL,
    conversation_id  CHAR(36)     NULL,
    target_type      VARCHAR(20)  NULL,
    target_id        VARCHAR(80)  NULL,
    details          JSON         NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tmsg_audit_vendor (vendor_id, created_at),
    INDEX idx_tmsg_audit_conv (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;