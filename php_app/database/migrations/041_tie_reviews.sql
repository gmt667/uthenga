-- 041: Reviews intelligence workspace for the Events Control Center.
--      The organizer's reputation & customer-feedback command center.
--
--      Design contract:
--      * Reviews are individual customer feedback records; Analytics stays the
--        aggregate business-intelligence surface and derives its own numbers.
--      * Negative != Invalid. Organizers can respond, investigate and flag for
--        platform moderation, but can never erase a legitimate review.
--      * Verified Attendee is an earned badge: valid ticket + checked-in
--        attendance (derived from bookings / event_tickets at seed time and
--        re-verified live at read time).
--      * Every write (response, flag, config) lands in the audit log.

CREATE TABLE IF NOT EXISTS tie_reviews_reviews (
  id                 VARCHAR(30)   NOT NULL PRIMARY KEY,
  vendor_id          VARCHAR(30)   NOT NULL,
  event_id           VARCHAR(30)   NOT NULL,
  listing_id         VARCHAR(30)   NULL,
  request_id         VARCHAR(30)   NULL,
  customer_id        VARCHAR(30)   NOT NULL,
  customer_name      VARCHAR(120)  NOT NULL,
  customer_email     VARCHAR(180)  NULL,
  rating             TINYINT       NOT NULL,
  title              VARCHAR(200)  NULL,
  body               TEXT          NOT NULL,
  sentiment          ENUM('POSITIVE','NEUTRAL','NEGATIVE') NOT NULL DEFAULT 'NEUTRAL',
  themes             JSON          NULL COMMENT '[{"theme":"check-in","polarity":"positive"},...] deterministic keyword classification',
  verified_attendee  TINYINT(1)    NOT NULL DEFAULT 0,
  verification       JSON          NULL COMMENT 'booking/ticket/check-in evidence behind the Verified Attendee badge',
  helpful_count      INT UNSIGNED  NOT NULL DEFAULT 0,
  status             ENUM('PUBLISHED','PENDING','HIDDEN','REMOVED') NOT NULL DEFAULT 'PUBLISHED',
  moderation         ENUM('NORMAL','FLAGGED') NOT NULL DEFAULT 'NORMAL',
  flag_reason        VARCHAR(200)  NULL,
  responded_at       DATETIME      NULL,
  request_opened_at  DATETIME      NULL,
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_reviews_vendor_status (vendor_id, status),
  KEY idx_tie_reviews_event (event_id, rating),
  KEY idx_tie_reviews_customer (customer_id),
  KEY idx_tie_reviews_mod (moderation, status),
  CONSTRAINT fk_tie_reviews_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tie_reviews_event FOREIGN KEY (event_id) REFERENCES tie_events_events(id) ON DELETE CASCADE,
  CONSTRAINT chk_tie_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_reviews_responses (
  id              VARCHAR(30)   NOT NULL PRIMARY KEY,
  vendor_id       VARCHAR(30)   NOT NULL,
  review_id       VARCHAR(30)   NOT NULL,
  body            TEXT          NOT NULL,
  ai_drafted      TINYINT(1)    NOT NULL DEFAULT 0,
  status          ENUM('PUBLISHED','HIDDEN') NOT NULL DEFAULT 'PUBLISHED',
  created_by      VARCHAR(30)   NULL,
  created_by_name VARCHAR(120)  NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tie_review_resp_vendor (vendor_id),
  CONSTRAINT fk_tie_review_resp_review FOREIGN KEY (review_id) REFERENCES tie_reviews_reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_reviews_flags (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  review_id       VARCHAR(30)   NOT NULL,
  vendor_id       VARCHAR(30)   NOT NULL,
  flagged_by      VARCHAR(30)   NULL,
  flagged_by_name VARCHAR(120)  NULL,
  reason          VARCHAR(60)   NOT NULL,
  notes           TEXT          NULL,
  status          ENUM('PENDING','UNDER_REVIEW','DISMISSED','REMOVED') NOT NULL DEFAULT 'PENDING',
  decided_at      DATETIME      NULL,
  decided_by      VARCHAR(30)   NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_revflags_vendor_status (vendor_id, status),
  KEY idx_tie_revflags_review (review_id),
  CONSTRAINT fk_tie_revflags_review FOREIGN KEY (review_id) REFERENCES tie_reviews_reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_reviews_requests (
  id          VARCHAR(30)   NOT NULL PRIMARY KEY,
  vendor_id   VARCHAR(30)   NOT NULL,
  event_id    VARCHAR(30)   NULL,
  listing_id  VARCHAR(30)   NULL,
  customer_id VARCHAR(30)   NOT NULL,
  status      ENUM('SENT','OPENED','STARTED','SUBMITTED','SKIPPED') NOT NULL DEFAULT 'SENT',
  channel     ENUM('UTHENGA','EMAIL','SMS') NOT NULL DEFAULT 'UTHENGA',
  sent_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  opened_at   DATETIME      NULL,
  started_at  DATETIME      NULL,
  submitted_at DATETIME     NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_revreq_vendor_event (vendor_id, event_id),
  KEY idx_tie_revreq_customer (customer_id),
  CONSTRAINT fk_tie_revreq_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tie_revreq_event FOREIGN KEY (event_id) REFERENCES tie_events_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_reviews_config (
  vendor_id           VARCHAR(30)   NOT NULL PRIMARY KEY,
  collect_enabled     TINYINT(1)    NOT NULL DEFAULT 1,
  request_delay_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  channel_uthenga     TINYINT(1)    NOT NULL DEFAULT 1,
  channel_email       TINYINT(1)    NOT NULL DEFAULT 1,
  channel_sms         TINYINT(1)    NOT NULL DEFAULT 0,
  publish_mode        ENUM('AUTO','MODERATED') NOT NULL DEFAULT 'AUTO',
  notify_new          TINYINT(1)    NOT NULL DEFAULT 1,
  notify_negative     TINYINT(1)    NOT NULL DEFAULT 1,
  notify_reply        TINYINT(1)    NOT NULL DEFAULT 1,
  incentive_enabled   TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'incentives are for honest feedback, never for positive ratings',
  critical_max        TINYINT       NOT NULL DEFAULT 2,
  high_max            TINYINT       NOT NULL DEFAULT 3,
  normal_max          TINYINT       NOT NULL DEFAULT 4,
  low_max             TINYINT       NOT NULL DEFAULT 5,
  updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tie_reviews_cfg_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_reviews_audit_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_id   VARCHAR(30)   NOT NULL,
  actor_id    VARCHAR(30)   NULL,
  actor_name  VARCHAR(120)  NULL,
  action      VARCHAR(60)   NOT NULL,
  review_id   VARCHAR(30)   NULL,
  target_type VARCHAR(40)   NULL,
  target_id   VARCHAR(40)   NULL,
  details     JSON          NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_revaudit_vendor (vendor_id, created_at),
  KEY idx_tie_revaudit_review (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;