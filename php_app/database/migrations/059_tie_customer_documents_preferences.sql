-- Trip Planning Assistant: Documents (a personal/trip document wallet, not
-- the separate vendor-tenant Events Documents workspace) and Preferences (a
-- travel-style profile). File bytes never live in this table — only a
-- random storage_name pointing into storage/customer-documents/, served
-- back through an authenticated endpoint, same pattern as
-- tie_accommodation_documents / accommodation/media.php.

CREATE TABLE IF NOT EXISTS customer_documents (
  id CHAR(36) NOT NULL PRIMARY KEY,
  customer_id VARCHAR(30) NOT NULL,
  category VARCHAR(20) NOT NULL DEFAULT 'other',
  label VARCHAR(120) NOT NULL,
  trip_id VARCHAR(32) NULL COMMENT 'trip_itineraries.itinerary_code',
  visibility VARCHAR(10) NOT NULL DEFAULT 'personal',
  original_name VARCHAR(255) NOT NULL,
  storage_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  expiry_date DATE NULL,
  is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_customer_documents_customer (customer_id, created_at),
  KEY idx_customer_documents_trip (trip_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One row per customer. Notification channel toggles live on `users`
-- already (push_notify/email_notify/sms_notify, wired to profile.php and
-- UthengaTieNotificationService) — this table is only for the genuinely new
-- travel-style fields that have nowhere else to live.
CREATE TABLE IF NOT EXISTS customer_travel_preferences (
  customer_id VARCHAR(30) NOT NULL PRIMARY KEY,
  preferences JSON NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
