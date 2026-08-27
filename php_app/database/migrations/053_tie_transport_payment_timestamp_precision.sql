-- Same class of bug already fixed once for tie_transport_call_requests
-- (migration 050): tie_transport_payments.id is a random UUID (not
-- sequential), so two rows for the same session created within the same
-- second make "ORDER BY created_at DESC, id DESC" fall back to an arbitrary
-- UUID comparison instead of real recency — confirmed by a smoke test that
-- produced exactly this collision (a FAILED electronic attempt immediately
-- followed by a cash confirmation in the same second). Microsecond
-- precision removes the tie in every realistic case.
ALTER TABLE tie_transport_payments
  MODIFY COLUMN created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  MODIFY COLUMN updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
