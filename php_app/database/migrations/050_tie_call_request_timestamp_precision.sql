-- tie_transport_call_requests.id is a random UUID (not sequential), so when
-- two call requests for the same session are both updated within the same
-- second, ORDER BY updated_at DESC previously fell back to an arbitrary
-- UUID comparison instead of real recency. Microsecond precision removes
-- the tie in every realistic case (confirmed by a smoke test that produced
-- exactly this collision).
ALTER TABLE tie_transport_call_requests
  MODIFY COLUMN created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  MODIFY COLUMN updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
