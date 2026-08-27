# Uthenga TIE — Phase 3 Completion Report

**Status:** Implemented, disabled by default

## Outcome

Phase 3 adds a real, read-only Uthenga marketplace query boundary. TIE can now
retrieve normalized services, eligible vendors, categories, published prices,
declared availability, schedules, routes, and textual locations without using
an LLM or changing a booking.

## What was deployed

- `Query.php`: validated filter contract, profile adapter, category normalizer,
  source/freshness metadata, and a repository containing all raw SQL.
- `UthengaTieQueryService`: replaces the former empty query placeholder and
  exposes services, vendors, and categories to the existing kernel.
- Feature-gated read APIs: `services.php`, `vendors.php`, and `categories.php`.
- Database-backed integration coverage in `QueryTest.php`.
- Query-engine and API/security documentation.

The API remains disabled until both `TIE_ENABLED=true` and
`TIE_QUERY_ENABLED=true` are set in the local configuration. Enabling it does
not require or add provider credentials.

## Evidence from the local XAMPP profile

- MariaDB 10.4.32 was reached through the configured application connection.
- The created database was empty, so the repository's existing seeded
  setup/migrations were applied.
- The working profile now has 11 active `listings`: events (5), accommodation
  (2), tours (2), and transport (2), with 9 seeded users.
- The query integration test returned the Lilongwe-to-Blantyre transport
  listing in 2.52 ms on the local profile and confirmed no inventory rows were
  changed.

## Explicit boundaries

Phase 3 does not rank or recommend results, infer facts, call an LLM, write
trip plans, create or amend bookings, charge payments, track GPS, or validate
booking availability. Published price and availability fields retain their
provenance and state that Phase 4 validation is still required.

## Risks recorded

The legacy migration set has mixed `utf8mb4_general_ci` and
`utf8mb4_unicode_ci` foreign-key definitions. Several optional tables therefore
did not install cleanly. The canonical `listings` and `users` path is live and
verified; the collation cleanup should be handled before Phase 4 adopts any
optional room/ticket tables. Listing coordinate columns exist but all current
seed values are null, so radius search is structurally supported but has no
results until coordinates are populated.

## Phase 4 prerequisites

1. Decide and document the availability source of truth per category.
2. Fix the legacy collation migration failures before depending on optional
   room or ticket inventory tables.
3. Define transaction-safe availability checks and the revalidation point
   immediately before existing booking creation.
4. Add realistic vendor-maintained availability and coordinate data; do not
   treat the seeded `meta` values as production truth.
