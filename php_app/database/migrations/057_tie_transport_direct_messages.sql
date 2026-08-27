-- Quick Taxi: persistent 1-1 driver <-> passenger messaging, independent of
-- any single run/session. Coordination chat (tie_transport_messages) is
-- scoped to one live session and closes when that session stops being
-- interactive; a driver and a real (non-walk-in) past passenger often need
-- to reach each other after a departure ends (e.g. a forgotten bag). A
-- thread may only be started between a vendor and a customer who have
-- actually shared a real Quick Taxi session together — enforced in
-- UthengaTieMessagingService::startDirectThread(), not at the schema level.

CREATE TABLE IF NOT EXISTS tie_transport_direct_threads (
  id CHAR(36) NOT NULL PRIMARY KEY,
  vendor_id CHAR(36) NOT NULL,
  customer_id CHAR(36) NOT NULL,
  last_message_at DATETIME NULL,
  last_message_preview VARCHAR(255) NULL,
  vendor_unread_count INT UNSIGNED NOT NULL DEFAULT 0,
  customer_unread_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tie_transport_direct_threads_pair (vendor_id, customer_id),
  KEY idx_tie_transport_direct_threads_customer (customer_id, last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS tie_transport_direct_messages (
  id CHAR(36) NOT NULL PRIMARY KEY,
  thread_id CHAR(36) NOT NULL,
  sender_role VARCHAR(20) NOT NULL,
  body VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_transport_direct_messages_thread (thread_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
