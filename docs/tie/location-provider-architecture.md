# TIE Location provider architecture and operations

## Provider-neutral roles

```text
Foreground client observation
        -> Location validation and Context Engine
Reverse geocoding adapter
        -> normalized geographic context
MariaDB geographic-search adapter
        -> Phase 3 Query Engine -> Phase 4 Availability Engine
```

The server never calls browser, Android, or iOS location APIs. A client obtains
one explicit foreground observation, while the server validates the canonical
observation. `UthengaTieForegroundClientGeolocationProvider` documents this
boundary; it does not introduce background acquisition or tracking.

Reverse geocoding remains behind `UthengaTieGeocodingProvider`. Provider
adapters return normalized geography only; Nominatim, Google, Mapbox, HERE, or
other provider payloads never reach a downstream TIE engine.

Nearby discovery is behind `UthengaTieGeographicSearchProvider`. The active
adapter is `mariadb_verified_coordinate_search`, which delegates solely to the
Phase 3 Query Engine. Phase 4 validates each candidate before it is exposed.
There is no duplicate marketplace or spatial-search path.

## Server-authoritative facts

Clients may provide an observational location: coordinates, accuracy, capture
time, source, platform, and normalized permission metadata. They may not send
marketplace facts such as `distance_km`, vendor availability/verification,
eligibility, rating, quality, candidates, or results. Nearby Search rejects
those fields before it queries the marketplace. The server derives all service
distance, coordinate quality, availability, and eligibility facts.

## Rate-limit policy

All limits are session-scoped per minute and configurable:

| Operation | Setting | Default |
| --- | --- | ---: |
| Location Context | `TIE_LOCATION_CONTEXT_RATE_LIMIT` | 20 |
| Permission updates | `TIE_LOCATION_PERMISSION_RATE_LIMIT` | 20 |
| Nearby Search | `TIE_LOCATION_NEARBY_RATE_LIMIT` | 10 |
| Vendor coordinate update | `TIE_LOCATION_VENDOR_COORDINATE_RATE_LIMIT` | 10 |
| Coordinate review | `TIE_LOCATION_VENDOR_COORDINATE_REVIEW_RATE_LIMIT` | 10 |
| Coordinate import | `TIE_LOCATION_VENDOR_COORDINATE_IMPORT_RATE_LIMIT` | 10 |
| Reverse-geocoding provider | `TIE_GEOCODER_RATE_LIMIT_PER_MINUTE` | 10 |

Nearby uses `POST`, not `GET`, because a validated foreground observation is
submitted in an authenticated, CSRF-protected request body. It remains
request-scoped and never streams device location.

## Failure and degradation contract

| Failure | Behaviour | Marketplace fallback |
| --- | --- | --- |
| Browser/device observation unavailable | No coordinate is sent; use the Phase 6.16 fallback. | City, district, destination, or manual selection. |
| Reverse geocoder unavailable/rate-limited/timeout | Valid coordinate remains usable; geographic context is partial. | Nearby can continue. |
| Geographic search provider unavailable | Nearby API returns a standard `fallback` object. | Normal catalogue, destination, or category search. |
| Provider timeout/failure | Logged as a safe provider failure; no provider payload is exposed. | Core marketplace stays available. |

Logs, metrics, provider descriptors, and fallback responses contain no raw
coordinates, addresses tied to users, sessions, or movement history.
