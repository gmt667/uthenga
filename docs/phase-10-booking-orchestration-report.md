# Uthenga TIE — Phase 10 Booking Orchestration & Transaction Engine

## Outcome

Phase 10 adds an idempotent, approval-driven transactional orchestration layer
without replacing Uthenga's booking system. TIE stores orchestration execution
records separately and calls the existing request router for actual marketplace
writes.

## Delivered

- Versioned booking request/result contracts and booking state machine.
- Fresh plan/availability revalidation before every execution.
- Owner-scoped idempotency, execution/operation persistence, safe diagnostics,
  partial-failure states, rollback/cancellation framework, and journey state.
- Legacy marketplace API provider adapter, isolated behind an interface.
- Validation, execute, cancel, and status endpoints.
- Migration 017 for orchestration records only.
- Tests with a fake provider; no integration test creates a real booking.

## Payment safety gate

The deployed legacy booking route currently records bookings as paid during
creation. Phase 10 therefore ships with execution disabled unless
`TIE_BOOKING_LEGACY_IMMEDIATE_CAPTURE_ENABLED=true` is explicitly configured.
This is intentional: TIE cannot claim payment authorization or transform a
correlation reference into payment approval. The validate endpoint remains safe
to use while this gate is off.

## Booking boundary

TIE never redesigns a plan, reranks a service, accesses payment credentials,
or writes canonical marketplace booking records. Existing booking and payment
flows remain authoritative and must perform their own final transaction-level
checks.
