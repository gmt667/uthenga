# Booking Orchestration Engine

Phase 10 turns an approved, freshly validated TIE plan into independently
tracked marketplace booking operations. It does not insert into `bookings`,
`booking_items`, transactions, payment tables, or inventory tables. Instead,
the legacy provider calls the existing authenticated `request_api.php` actions
for creation and cancellation; that route remains responsible for final price,
inventory, booking, transaction, and cancellation behaviour.

Execution records live in `tie_booking_executions` and
`tie_booking_operations`. They contain idempotency, lifecycle, safe diagnostic,
and journey-readiness metadata—not payment secrets or raw payment references.

The existing deployment marks bookings paid immediately in its legacy booking
route. Therefore `TIE_BOOKING_LEGACY_IMMEDIATE_CAPTURE_ENABLED` is false by
default. While false, execute requests persist an auditable failed execution
with `PAYMENT_HANDOFF_REQUIRED` and do not invoke the marketplace route. Turn
it on only after the current payment semantics have been explicitly approved.

Every execution requires an `APPROVED` plan, a customer session, CSRF, an
idempotency key, a payment correlation reference, and final availability
revalidation. The orchestration policy is configurable as `continue`, `stop`,
`rollback`, or `manual_review`. Rollback requests the existing cancellation
route and never assumes a vendor supports a compensating operation.
