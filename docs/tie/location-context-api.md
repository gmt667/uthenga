# TIE Location Context API and privacy contract

## Public API

`POST /api/tie/location/context.php` is the provider-neutral public Location
Context API. It is authenticated, CSRF-protected, feature-gated, rate-limited,
and accepts either a validated current observation or a no-location permission
state.

With an observation, the request follows `location-context-request/v1` and
reuses the existing Location validation, consent, freshness, confidence, and
geographic-context pipeline. The public response is always
`location-context-response/v1`.

The response deliberately omits latitude, longitude, altitude, heading, speed,
accuracy in metres, and capture time. It supplies only the least information a
caller needs: validity, permission state, confidence, quality classes,
coarse normalized administrative geography, provenance without coordinates,
and diagnostics.

```json
{
  "location_context": {
    "schema_version": "location-context-response/v1",
    "location": {
      "valid": true,
      "confidence": "HIGH",
      "freshness": "FRESH",
      "usable_for_nearby": true
    },
    "geographic_context": {
      "country": "Malawi",
      "district": "Lilongwe",
      "city": "Lilongwe"
    }
  }
}
```

## Permission-denied fallback

No coordinate is required when a client reports `DENIED`, `UNAVAILABLE`,
`RESTRICTED`, `NOT_REQUESTED`, `REQUESTED`, or `EXPIRED`. The API returns a
successful, coordinate-free response containing a `fallback` object with
`search_by_city`, `search_by_district`, `search_by_destination`, and
`manual_map_selection` alternatives. `booking_blocked` is always false.

## Retention and exposure policy

| Data | Policy |
| --- | --- |
| Current user observation | Request-scoped and ephemeral; never stored by TIE. |
| Permission state | Session-scoped only; never persisted to the database. |
| User location or movement history | Never created. |
| TravelContext location | Request-scoped; no location history cache. |
| Reverse-geocoding cache | Optional process-local normalized geography, keyed by rounded coordinates; no user or session identity. |
| Logs and metrics | No raw coordinates, addresses tied to users, session IDs, or movement data. |
| Public Context API | No precise coordinate, telemetry, accuracy-in-metres, or capture timestamp. |

Any proposal to persist a user location requires a separate architecture
decision covering purpose, retention duration, access control, deletion, and
user controls. This phase introduces no persistence and no background tracking.

## Downstream boundary

Downstream TIE components consume normalized Location Context only. They do
not call browser/mobile location APIs or reverse-geocoding providers directly.
Components that need an exact coordinate for a user-initiated deterministic
operation use the internal validated request path; they do not obtain it from
the public API response.
