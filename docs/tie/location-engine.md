# TIE Phase 5 and 6.1–6.4 location and geospatial engine

## Scope and privacy model

Phase 5 accepts user location only after an explicit foreground action. The
browser helper in `assets/js/tie-location.js` does not request location on page
load, watch movement, or persist coordinates. The server validates latitude,
longitude, accuracy, timestamp, source, and explicit permission state. It does
not infer GPS from IP address or infer device permission from coordinates.
Customer location is request-scoped only.

Phase 6.1 normalizes browser, Android, and iOS permission results into the
session-scoped states `NOT_REQUESTED`, `REQUESTED`, `GRANTED`, `DENIED`,
`UNAVAILABLE`, `RESTRICTED`, and `EXPIRED`. Phase 6.2 adds the current
location's consent and provenance metadata. Neither phase adds background
tracking, movement history, or persistent permission decisions.

Phases 6.3 and 6.4 make `location-context/v1` the single provider-neutral
location contract. The server validates and normalizes every coordinate before
constructing this DTO; Context, nearby search, and future routing consume the
DTO rather than browser or provider response shapes.

## Quality and reverse geocoding

Location context preserves source, normalized permission, platform, provider,
accuracy, coordinate precision, and capture time. It classifies freshness as
`FRESH`, `AGING`, `STALE`, or `EXPIRED`, and accuracy as `EXCELLENT`, `GOOD`,
`MODERATE`, `POOR`, or `UNKNOWN`. Defaults are configurable through
`TIE_LOCATION_*` settings. Expired, insufficiently accurate, or unconsented
device locations cannot drive nearby search.

The Location Engine evaluates shared operation profiles rather than allowing
consumers to compare raw meter values or timestamps. Nearby search and routing
accept `EXCELLENT`/`GOOD`; trip planning also accepts `MODERATE`; live journey
tracking accepts `EXCELLENT` and `FRESH` only. An `EXPIRED` observation sets a
reacquisition-required signal but never starts device acquisition itself.

Phase 6.7 synthesizes validated accuracy, freshness, source, and permission
into a versioned `HIGH`, `MEDIUM`, `LOW`, or `UNKNOWN` confidence signal.
Operation profiles include a configurable minimum confidence, so consumers use
their final eligibility result instead of recomputing trust from raw metadata.

Phase 6.8 resolves a provider-neutral `geographic_context` with only available
administrative values and provider provenance. Reverse-geocoder failure or
non-configuration leaves the location valid and returns a normalized status;
no provider response shape reaches a downstream module.

The required DTO fields are `latitude`, `longitude`, `captured_at`, and
`source`. `accuracy_m` is required for device-derived observations and optional
for manual or provider-derived observations; its absence is classified as
`UNKNOWN`. Coordinates are normalized to six decimal places, accuracy to two
decimal places, and timestamps to UTC ISO-8601. Optional altitude, heading,
and speed are omitted unless supplied; TIE never fabricates them. Validation
errors are machine-readable field errors such as
`INVALID_LATITUDE`, `INVALID_LONGITUDE`, `MALFORMED_COORDINATES`,
`INVALID_SOURCE`, and `INVALID_TIMESTAMP`.

`UthengaTieGeocodingProvider` is provider-neutral. Nominatim is an opt-in
adapter selected only with `TIE_GEOCODER_ENABLED=true` and
`TIE_GEOCODER_PROVIDER=nominatim`; it is off by default because it sends
coordinates to a third party. Failure or non-configuration leaves coordinates
valid and returns partial geographic context. No persistent geocoding cache is
created in Phase 5.

## MariaDB geospatial decision

The deployed engine is MariaDB 10.4.32 and already has decimal
`listings.gps_lat`/`gps_lng`. Phase 5 uses:

```text
Composite lat/lng index → bounding-box prefilter → exact Haversine radius check
```

Migration `014_tie_location_provenance.sql` adds coordinate provenance,
accuracy/capture/verification fields and `idx_listings_geo`. PostGIS, spatial
microservices, and routing were not introduced.

All current seed listings have null coordinates. They are excluded from radius
results. A vendor can add an optional coordinate while creating or updating a
listing, or a vendor/administrator can use the protected coordinate endpoint;
coordinates are never invented from addresses. Every vendor submission starts
as `pending_review`; only coordinates explicitly marked `verified` participate
in precision radius and nearby results.

## Nearby pipeline and APIs

```text
Explicit location → quality check → Phase 3 radius filter
                  → exact distance → Phase 4 eligibility → nearby results
```

Results are sorted only by straight-line geographic distance—not road
distance, ETA, traffic, or recommendation score.

All APIs require authentication, CSRF, location feature enablement, and
session-local rate limits:

| Endpoint | Purpose |
| --- | --- |
| `POST /api/tie/location/context.php` | Explicit ephemeral location context. |
| `POST /api/tie/location/nearby.php` | Nearby eligible services; also requires availability enablement. |
| `POST /api/tie/location/vendor-coordinate.php` | Vendor/admin listing-coordinate enrichment. |
| `POST /api/tie/location/permission.php` | Session-only normalized permission lifecycle update; accepts no coordinates. |
