# Trip Planning Engine

Phase 9 composes a proposed itinerary from `travel-context/v1` and
`recommendation-result/v1`. It never queries marketplace tables directly: the
engine builds context, consumes ranked recommendations, and uses Query plus
Availability only for explicit validation of planned service IDs.

Plans are persisted in the existing `trip_itineraries` draft store, extended by
the Phase 9 migration with TIE lifecycle, diagnostics, policy, and provenance
fields. This table is separate from bookings, payments, inventory, and vendor
data. No plan action reserves capacity or creates a booking.

The planner chooses ranked accommodation and transport when present, then
distributes ranked tours, activities, and events across trip days. Where a
listing does not expose an authoritative schedule, a time is explicitly marked
as a planning proposal rather than a marketplace schedule.

Lifecycle is `DRAFT`, `UPDATED`, `VALIDATED`, `READY_FOR_APPROVAL`, `APPROVED`,
`EXPORTED`, and `ARCHIVED`. Edit operations remove, reorder, or replace a
planned service; replacement candidates are freshly derived from the
Recommendation Engine and every edit triggers Availability revalidation.

Exports are JSON only in this phase and remain read-only. PDF/calendar exports
are intentionally future work; they must not be used as booking confirmation.
