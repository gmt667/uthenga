-- ============================================================
-- Migration: 004_admin_permissions_and_system_logs.sql
-- Adds permission granularity plus system/notification audit logs
-- ============================================================


CREATE TABLE IF NOT EXISTS permissions (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  permission_key VARCHAR(120) NOT NULL,
  label         VARCHAR(180) NOT NULL,
  module        VARCHAR(120) NOT NULL,
  description   VARCHAR(255) NULL,
  is_system     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at    DATETIME NULL,
  UNIQUE KEY uq_permissions_key (permission_key),
  KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id       BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  assigned_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_permissions (
  user_id       BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  permissions   JSON NOT NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id BIGINT UNSIGNED NULL,
  channel         ENUM('email','sms','push','in_app') NOT NULL DEFAULT 'in_app',
  recipient       VARCHAR(180) NOT NULL,
  status          ENUM('queued','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
  provider_response JSON NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at         DATETIME NULL,
  KEY idx_notification_logs_notification (notification_id),
  KEY idx_notification_logs_channel (channel),
  CONSTRAINT fk_notification_logs_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_health_logs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  health_key      VARCHAR(120) NOT NULL,
  status          ENUM('healthy','warning','critical','unknown') NOT NULL DEFAULT 'unknown',
  value_payload   JSON NULL,
  notes           TEXT NULL,
  recorded_by     BIGINT UNSIGNED NULL,
  recorded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_system_health_key (health_key),
  KEY idx_system_health_status (status),
  CONSTRAINT fk_system_health_logged_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, label, module, description) VALUES
('admin_users', 'Admin Management', 'admin', 'Create, edit, suspend, reset, and delete administrator accounts'),
('vendor_review', 'Vendor Review', 'admin', 'Approve or reject vendors and service providers'),
('listings', 'Listings', 'content', 'Manage events, stays, tours, and transport listings'),
('bookings', 'Bookings', 'operations', 'Review and manage customer bookings'),
('support', 'Support', 'operations', 'Handle support tickets and replies'),
('reports', 'Reports', 'analytics', 'View and export platform reports'),
('settings', 'System Settings', 'system', 'Update platform configuration'),
('logs', 'Audit Logs', 'system', 'Review audit and security logs')
ON DUPLICATE KEY UPDATE label = VALUES(label), module = VALUES(module), description = VALUES(description);
