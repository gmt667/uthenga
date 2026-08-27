# TIE contracts

`php_app/includes/tie/Contracts.php` provides PHP DTOs for TripRequest, UserContext, LocationContext, VendorCandidate, Recommendation, TripPlan, Route, JourneyState, and Conversation.

The TripRequest validator accepts destination, optional origin/date range/budget/preferences/travel mode, and a trusted server-side user ID. It rejects invalid dates, traveller counts, budgets, travel modes, and oversized preference lists. LocationContext distinguishes `gps`, `manual`, `geocoded`, `inferred`, and `unknown` sources.

Contracts are intentionally independent of `listings.meta`. Phase 3 maps the
verified canonical inventory model into VendorCandidate through
`Query.php`. `UthengaTieCatalogueContracts` validates public read criteria;
the normalized candidate retains source provenance and freshness but never the
raw JSON payload. See [query-engine.md](query-engine.md).

`Availability.php` adds `AvailabilityRequest` and the structured validation
result boundary. It distinguishes validated, unavailable, stale, and unknown
availability; unknown is blocking for eligibility. See
[availability-engine.md](availability-engine.md).

`Context.php` adds `TravelContext` and `ContextBuildRequest`. The context is versioned (`travel-context/v1`), provenance-aware, provider-neutral, and strictly deterministic. See [context-engine.md](context-engine.md).
