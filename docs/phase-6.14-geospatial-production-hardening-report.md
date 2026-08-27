# Uthenga TIE — Phase 6.14 Geospatial Production Hardening

## Outcome

Phase 6.14 closes the remaining operational gaps after Phases 6.11–6.13. It
does not introduce routing, ETA calculation, personalization, recommendation
ranking, LLM reasoning, or new geospatial infrastructure.

## Changes

- A deterministic quality policy now classifies every listing coordinate as
  `VERIFIED`, `UNVERIFIED`, `MISSING`, `STALE`, or `INVALID`.
- Radius search accepts only recent verified coordinates. The review window is
  configurable with `TIE_VENDOR_LOCATION_STALE_DAYS` (default: 365 days).
- Nearby results use stable distance, availability, and service-ID tie-breaks.
- Nearby endpoints emit privacy-safe latency and result-count metrics.
- Administrators have an atomic, validation-backed import path. Imported rows
  are always `pending_review` and are auditable.
- A CLI diagnostics snapshot emits the vendor-quality distribution without
  coordinates or user data.
- Regression coverage validates stale exclusion, sort ordering, import
  authorization, and the availability of `idx_listings_geo_verified` to the
  database query planner. It also reports a single-radius baseline time.

## Storage decision

ADR 006 remains unchanged: MariaDB decimal columns, the verified-coordinate
index, bounding-box prefilter, and exact Haversine calculation remain the
simplest supported architecture for the current marketplace scale. Phase 6.14
adds no migration and no external geospatial dependency.

The current seed catalogue contains only 11 listings, so this baseline is a
query-regression signal—not a capacity claim. Re-run a representative load and
query-plan assessment before selecting MariaDB spatial indexes or a dedicated
geospatial service.

## Operational next step

Begin collecting vendor coordinates through the existing listing form or the
admin import workflow, then verify them. Until that happens, precision nearby
search deliberately returns no marketplace listings rather than guessing their
locations.
