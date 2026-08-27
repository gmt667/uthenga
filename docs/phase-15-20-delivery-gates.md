# Uthenga TIE — Phase 15–20 delivery gates

This is the implementation baseline after Phases 11–14. It prevents unsafe
payment or operational work from being mistaken for a feature-flag change.

| Programme | Required before activation | Current state |
| --- | --- | --- |
| 15 Booking orchestration | immutable booking quote, final availability recheck, inventory-hold contract, idempotency, compensation rules, support/audit view | Existing legacy adapter is not payment-safe; disabled |
| 16 Payments | provider contract, payment intent/state machine, signed webhook verification, idempotent event store, reconciliation, refunds, failure recovery | Not implemented |
| 17 Journey | canonical journey records derived from confirmed bookings, timeline/status transitions, supplier update ingestion | Placeholder only |
| 18 Maps/routing | selected provider, server adapter, consent boundary, routing/cache/rate-limit policy, ETA fallback | Provider not selected |
| 19 Notifications | outbox, queue worker, channel adapters, preferences, retries, delivery audit | No durable notification pipeline |
| 20 Operations | secrets rotation, CI checks, central observability, alerting, backup/restore, incident/runbook process | Local XAMPP validation only |

## Payment programme entry criteria

Do not enable booking or integrate a gateway until all of the following are
approved:

1. A payment provider's official integration and webhook documentation has been
   reviewed against the selected account mode.
2. Payment data is classified and the application stores no raw card or mobile
   money credentials.
3. The exact booking price and availability are revalidated immediately before
   payment initiation.
4. Every initiation, callback, and refund is idempotent and persists an audit
   event without storing provider secrets.
5. The booking outcome can be reconciled against provider transactions and the
   marketplace booking record.
6. A failed, expired, duplicate, or delayed callback has a documented recovery
   path.

## Recommended next implementation slice

Build Phase 15 as a design-and-schema slice first: quote snapshots, inventory
hold interfaces, booking/payment state transitions, and a fully mocked provider
test suite. Keep the payment gateway disabled. Only then add Phase 16 behind a
separate test environment and verified webhook endpoint.
