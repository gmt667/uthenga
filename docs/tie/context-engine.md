# TIE Phase 6 context engine

## Purpose and boundary

`TravelContext` is the versioned deterministic handoff for future AI modules. It aggregates trusted outputs but does not call an LLM, rank services, generate an itinerary, modify a booking, or persist a planning state.

Its schema version is `travel-context/v1` and includes a purpose-limited user, trip request, active booking summary, latest saved planner session, time, optional ephemeral location, eligible candidates, component freshness, provenance, warnings, cache metadata, and build duration.

## Deployed data map

| Component | Source | Treatment |
| --- | --- | --- |
| User | `users` | ID, role, currency and notification settings only. |
| Active bookings | `bookings` | Active summary; notes, QR data, payment references, and PII excluded. |
| Active trip | `trip_planner_sessions` | Latest destination, duration and budget only. |
| Trip request | Trusted `TripRequest` | Validated destination, dates, travellers, budget and preferences. |
| Candidates | Phase 3 plus Phase 4 | Eligible candidates with provenance and validation. |
| Location | Phase 5 | Optional and request-scoped only. |

The deployed user schema has no preferred-language or stored travel-preference fields. Those remain unknown or come from the explicit current trip request; the engine does not infer them.

## Freshness, caching, and candidate selection

Each context carries component freshness. Availability is freshly evaluated for each build and location is never cached. A per-session cache can store only the purpose-limited user component for `TIE_CONTEXT_USER_CACHE_SECONDS` (60 by default). Booking, candidate, and location data are not cached.

Candidate flow is `Trip request → Phase 3 destination retrieval → Phase 4 validation → eligible candidate context`. Origin is transport-specific in the deployed schema, so route matching happens in Phase 4 per transport candidate.

## API

`POST /api/tie/context/build.php` requires an authenticated session, CSRF, and `TIE_ENABLED=true` plus `TIE_CONTEXT_ENABLED=true`. It accepts a normal TripRequest and optional nested Phase 5 location plus `nearby_radius_km`. Observability logs only correlation ID, module, outcome, and duration—not raw context, coordinates, booking details, or future prompts.
