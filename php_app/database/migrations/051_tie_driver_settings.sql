-- Quick Taxi Operations: Settings. Deliberately narrow — no payout-method
-- selection (no payout gateway exists), no 2FA/session management (that's
-- the platform-wide account security system, out of scope for a driver
-- console), and no fake "SOS dispatch" (there is no emergency-services
-- integration in this codebase; an emergency contact is just a stored
-- number the driver can call directly). Everything here is either real
-- driver-entered data or a preference that actually changes app behavior.
CREATE TABLE IF NOT EXISTS tie_driver_settings (
  driver_user_id VARCHAR(30) NOT NULL PRIMARY KEY,
  notification_sound TINYINT(1) NOT NULL DEFAULT 1,
  emergency_contact_name VARCHAR(120) NULL,
  emergency_contact_phone VARCHAR(30) NULL,
  deactivation_requested_at DATETIME NULL,
  deactivation_reason VARCHAR(300) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
