# Uthenga TIE — Phase 4 Completion Report

**Status:** Implemented, disabled by default

## 01. Implementation summary

Phase 4 adds a deterministic Availability & Business Rules Engine. It is
separate from the Phase 3 query repository, never calls an LLM, never ranks,
and never creates, changes, or reserves a booking.

## 02. Availability source-of-truth audit

The audit found that only the event JSON inventory is currently part of an
existing booking-time decrement/check path. Transport, accommodation, and tour
availability have published values but no reliable deployed inventory source.
They fail closed as `unknown`, not `available`.

## 03–12. Rules and validation

`Availability.php` provides validated request DTOs, vendor eligibility,
service lifecycle, date and route rules, event capacity checks, generic booking
quantity constraints, freshness policy support, structured rule results, and a
single validation result contract. Event checks cover ticket option, date,
past-event expiry, and requested quantity. Transport checks route and schedule
but is ineligible until a seat source exists. Accommodation requires a valid
date range but is ineligible until date-based room inventory exists. Tours
require a published date but remain ineligible until capacity exists.

## 13–18. Architecture and integration

The new engine receives normalized Phase 3 candidates and preserves their
provenance. A diagnostic lookup can load an inactive/ineligible service solely
to explain why it failed; public catalogue search remains restricted to active,
approved vendors. Batch validation is in memory, avoiding N+1 reads.

`POST /api/tie/availability/validate.php` is session/CSRF/feature-gated. It
does not expose raw JSON, accept client-supplied facts, or persist state.

## 19. Booking boundary

Every result explicitly requires final revalidation. The existing booking
transaction remains the sole authority. No Phase 4 change was made to the
booking endpoint, so existing customer booking behaviour is preserved.

## 20–21. Security and observability

The API uses parameterized repository lookup, server-side facts, session
authentication, and CSRF protection. It logs only correlation ID, service ID,
outcome, rule codes, and duration—not user profiles, payment data, or raw
booking payloads.

## 22. Database changes and known schema issue

No new table was created. The deployed migration drift remains documented:
foreign keys in optional inventory tables fail across
`utf8mb4_general_ci`/`utf8mb4_unicode_ci`. This must be repaired before those
tables can become authoritative. Current runtime timezone is `Europe/Berlin`;
production timezone needs an explicit product decision.

## 23–24. Tests and performance

XAMPP PHP lint passed for all TIE files. The Phase 2 contract test, Phase 3
query integration test, and new Phase 4 availability integration test all
passed. The Phase 3 route query measured 1.88 ms locally. Phase 4 validation
is in-memory after lookup; batch validation has no per-candidate DB query.

## 25–29. Files, reuse, limitations, and Phase 5 prerequisites

Added: `Availability.php`, the availability validation endpoint,
`AvailabilityTest.php`, availability documentation, and this report.

Modified: TIE bootstrap, feature flags, query candidate normalization, query
and availability service contracts, API index, environment templates, API and
test documentation.

Reused: `listings`, `users`, the existing booking endpoint's documented event
fallback, Phase 3 provenance, session/CSRF helpers, and observability.

Limitations: transport, accommodation, and tours cannot be eligible until real
inventory is present; event JSON inventory is legacy and must be rechecked at
booking; no cross-service optimization or booking hook exists yet.

Before Phase 5, decide the production timezone, repair the inventory-table
collation migration, establish date-aware room and seat/tour inventory, and
wire final revalidation into the existing booking transaction.
