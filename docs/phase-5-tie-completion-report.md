# Uthenga TIE — Phase 5 Completion Report

**Status:** Implemented, disabled by default

## Summary

Phase 5 adds consent-aware, ephemeral location context, configurable quality
checks, optional provider-neutral reverse geocoding, MariaDB radius search, and
nearby eligible-service discovery. It contains no LLM calls, routing, traffic,
ETA, tracking, trip optimization, or recommendation ranking.

## Architecture and data decisions

- Browser acquisition is explicit through `tie-location.js`; it does not watch
  or persist a user location.
- Location API requests validate coordinates, permission, source, accuracy, and
  ISO timestamps. Logs exclude exact coordinates.
- Reverse geocoding has an opt-in Nominatim adapter. Geocoder failure does not
  invalidate coordinates.
- MariaDB 10.4.32 is retained. Migration 014 was applied locally, adding
  coordinate provenance and a `(gps_lat, gps_lng)` index.
- Query uses a bounding-box prefilter and exact Haversine radius validation.
- Nearby results flow through Phase 3 retrieval and Phase 4 eligibility before
  distance-only ordering.

## APIs and safeguards

Added protected context, nearby, and vendor-coordinate endpoints. They require
session authentication and CSRF; nearby also requires availability enablement.
Session-local limits default to 20 context and 10 nearby requests/minute.
Reverse geocoding is disabled by default because it sends coordinates to a
third party. No customer location history or persistent geocoding cache exists.

## Current data reality

All 11 seeded listings lack coordinates. Nearby searches therefore return no
services safely until vendors/admins enrich listings with explicit coordinate
provenance. Existing inventory limitations remain: transport, accommodation,
and tours are unavailable to Phase 4 without authoritative availability.

## Verification

XAMPP lint passed. Phase 2 contracts, Phase 3 query integration, Phase 4
availability integration, and new Phase 5 location tests all passed. The
location test covers coordinate/permission validation, freshness, provider
failure fallback, Haversine distance, safe empty nearby results, and no DB
mutation. A five-candidate in-memory validation batch measured 0.25 ms; larger
radius-query performance requires populated vendor coordinates.

## Phase 6 prerequisites

Populate vendor coordinates, approve quality thresholds and production
timezone, repair availability inventory gaps, then add routing as a separate
provider-backed phase. Do not treat straight-line distance as a route or ETA.
