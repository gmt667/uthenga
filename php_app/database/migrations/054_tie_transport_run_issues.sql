-- Quick Taxi: driver-reported operational issues during a departure
-- (vehicle problem, accident, passenger issue, route obstruction, medical
-- emergency, other). Deliberately run-scoped, not session-scoped —
-- tie_transport_session_events is structurally tied to one passenger's
-- session and isn't the right home for a whole-departure incident report.

CREATE TABLE IF NOT EXISTS tie_transport_run_issues (
  id CHAR(36) NOT NULL PRIMARY KEY,
  run_id CHAR(36) NOT NULL,
  vendor_id VARCHAR(30) NOT NULL,
  category VARCHAR(30) NOT NULL,
  description VARCHAR(1000) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tie_transport_run_issues_run (run_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
