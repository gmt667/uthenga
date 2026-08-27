-- Quick Taxi: real in-app calling on the existing Coordination call-request
-- flow (tie_transport_call_requests already models consent — this migration
-- only adds the signal exchange a real WebRTC call needs, and two lifecycle
-- statuses the existing REQUESTED/ACCEPTED/DECLINED set didn't cover:
-- CANCELLED (requester hangs up before the recipient decides) and ENDED
-- (either side hangs up an active call). No phone number is ever stored or
-- returned here — only display name, via the existing users table.

CREATE TABLE IF NOT EXISTS tie_transport_call_signals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  call_request_id CHAR(36) NOT NULL,
  sender_role VARCHAR(20) NOT NULL,
  kind VARCHAR(20) NOT NULL,
  payload JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_transport_call_signals_call (call_request_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
