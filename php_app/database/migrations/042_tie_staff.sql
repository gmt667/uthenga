-- 042_tie_staff.sql
-- Staff: organization membership, RBAC roles/permissions, invitations,
-- event-scoped assignments with time-bounded access, and staff activity audit.
--
-- Design: staff rows reference the platform `users` table (no separate identity
-- system). Accesses are resolved as user -> role -> permissions -> event scope.

CREATE TABLE IF NOT EXISTS tie_staff_roles (
  id            VARCHAR(36)  NOT NULL PRIMARY KEY,
  vendor_id     VARCHAR(30)  NOT NULL,
  role_key      VARCHAR(60)  NOT NULL,
  name          VARCHAR(120) NOT NULL,
  description   VARCHAR(400) NULL,
  scope_type    VARCHAR(30)  NOT NULL DEFAULT 'organization', -- organization|events|event_operations|marketing|finance|checkin|support|viewer|custom
  permissions   JSON         NOT NULL,                          -- {"module": "level", ...}
  is_system     TINYINT(1)   NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_by    VARCHAR(30)  NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staff_role_vendor_key (vendor_id, role_key),
  KEY idx_staff_role_vendor (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_staff (
  id                 VARCHAR(36)  NOT NULL PRIMARY KEY,
  vendor_id          VARCHAR(30)  NOT NULL,
  user_id            VARCHAR(30)  NOT NULL,
  role_id            VARCHAR(36)  NOT NULL,
  status             VARCHAR(20)  NOT NULL DEFAULT 'pending', -- active|pending|suspended|expired|removed
  department         VARCHAR(120) NULL,
  position_title     VARCHAR(120) NULL,
  phone              VARCHAR(30)  NULL,
  added_by           VARCHAR(30)  NULL,
  added_at           DATETIME     NULL,
  removed_at         DATETIME     NULL,
  last_active_at     DATETIME     NULL,
  timezone           VARCHAR(60)  NULL,
  notes              VARCHAR(500) NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staff_vendor_user (vendor_id, user_id),
  KEY idx_staff_vendor_status (vendor_id, status),
  KEY idx_staff_user (user_id),
  KEY idx_staff_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_staff_invitations (
  id             VARCHAR(36)  NOT NULL PRIMARY KEY,
  vendor_id      VARCHAR(30)  NOT NULL,
  email          VARCHAR(180) NOT NULL,
  first_name     VARCHAR(120) NULL,
  last_name      VARCHAR(120) NULL,
  role_id        VARCHAR(36)  NOT NULL,
  scope_type     VARCHAR(30)  NOT NULL DEFAULT 'organization',
  event_ids      JSON         NULL,
  token          VARCHAR(96)  NOT NULL,
  status         VARCHAR(20)  NOT NULL DEFAULT 'pending', -- pending|accepted|expired|revoked
  sent_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at     DATETIME     NOT NULL,
  accepted_at    DATETIME     NULL,
  accepted_user_id VARCHAR(30) NULL,
  created_by     VARCHAR(30)  NULL,
  resend_count   INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uq_staff_invite_token (token),
  UNIQUE KEY uq_staff_invite_vendor_email (vendor_id, email),
  KEY idx_staff_invite_vendor_status (vendor_id, status),
  KEY idx_staff_invite_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_staff_assignments (
  id             VARCHAR(36)  NOT NULL PRIMARY KEY,
  vendor_id      VARCHAR(30)  NOT NULL,
  staff_id       VARCHAR(36)  NOT NULL,
  event_id       VARCHAR(40)  NOT NULL,
  role_id        VARCHAR(36)  NOT NULL,
  status         VARCHAR(20)  NOT NULL DEFAULT 'active', -- active|scheduled|expired|removed
  access_start_at DATETIME    NULL,
  access_end_at  DATETIME     NULL,
  assigned_by    VARCHAR(30)  NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staff_assignment (staff_id, event_id),
  KEY idx_staff_assign_vendor (vendor_id),
  KEY idx_staff_assign_event (event_id),
  KEY idx_staff_assign_staff (staff_id),
  KEY idx_staff_assign_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tie_staff_activity (
  id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vendor_id   VARCHAR(30)  NOT NULL,
  actor_id    VARCHAR(30)  NOT NULL,
  actor_name  VARCHAR(120) NULL,
  staff_id    VARCHAR(36)  NULL,
  event_id    VARCHAR(40)  NULL,
  module      VARCHAR(40)  NULL,
  action      VARCHAR(60)  NOT NULL,   -- invited|invitation_accepted|invitation_revoked|role_changed|permission_granted|permission_revoked|suspended|reactivated|removed|assigned|assignment_changed|assignment_removed|access_started|access_expired|password_changed|mfa_enabled|profile_updated|message_sent|...
  security    TINYINT(1)   NOT NULL DEFAULT 0,
  detail      JSON         NULL,
  ip_address  VARCHAR(45)  NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_staff_act_vendor (vendor_id, created_at),
  KEY idx_staff_act_staff (staff_id),
  KEY idx_staff_act_security (vendor_id, security, created_at),
  KEY idx_staff_act_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;