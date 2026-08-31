-- Canonical administrator permission storage. Run through the normal migration process.
-- Existing legacy permission arrays remain valid and are mapped conservatively in PHP.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER email;

CREATE TABLE IF NOT EXISTS admin_permissions (
  user_id VARCHAR(30) NOT NULL PRIMARY KEY,
  permissions JSON NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE vendor_profiles
  ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER approval_status,
  ADD COLUMN IF NOT EXISTS business_reg_number VARCHAR(80) NULL AFTER category;
