-- Trip Planning Assistant: real multi-user trip collaboration (Invite People)
-- and a shared trip-scoped group conversation (Messages). Trips
-- (trip_itineraries) were strictly single-owner before this; a collaborator
-- row grants read (viewer) or read+write (editor) access to one plan without
-- changing who owns it. Invites resolve immediately against a real Uthenga
-- account (found by email) — there is no separate pending/accept step yet.

CREATE TABLE IF NOT EXISTS trip_collaborators (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id VARCHAR(32) NOT NULL COMMENT 'trip_itineraries.itinerary_code',
  owner_user_id VARCHAR(30) NOT NULL,
  invited_user_id VARCHAR(30) NOT NULL,
  invited_email VARCHAR(180) NOT NULL,
  role VARCHAR(10) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'accepted',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_trip_collaborators_plan_user (plan_id, invited_user_id),
  KEY idx_trip_collaborators_user (invited_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS trip_conversation_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id VARCHAR(32) NOT NULL COMMENT 'trip_itineraries.itinerary_code',
  sender_user_id VARCHAR(30) NOT NULL,
  body VARCHAR(2000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_trip_conversation_messages_plan (plan_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
