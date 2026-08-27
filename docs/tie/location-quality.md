# TIE location accuracy and freshness policy

## Accuracy classification

Phase 6.5 classifies `accuracy_m` without provider-specific business logic:

| Class | Intended use |
| --- | --- |
| `EXCELLENT` | All supported location-aware operations. |
| `GOOD` | Nearby discovery and routing. |
| `MODERATE` | Trip planning and city/district-scale context. |
| `POOR` | Coarse geographic context only. |
| `UNKNOWN` | Accuracy is unavailable; precision-sensitive operations reject it. |

Thresholds are configuration-driven and must be ascending:
`TIE_LOCATION_ACCURACY_EXCELLENT_MAX_METERS`,
`TIE_LOCATION_ACCURACY_GOOD_MAX_METERS`,
`TIE_LOCATION_ACCURACY_MODERATE_MAX_METERS`, and
`TIE_LOCATION_ACCURACY_POOR_MAX_METERS`.

## Freshness classification

Phase 6.6 evaluates the age of the validated `captured_at` timestamp:

| State | Meaning |
| --- | --- |
| `FRESH` | Suitable for real-time operations. |
| `AGING` | Usable for nearby and planning, but no longer real-time routing. |
| `STALE` | Suitable for trip planning only. |
| `EXPIRED` | Reacquisition is required before location-aware use. |

`TIE_LOCATION_FRESH_SECONDS`, `TIE_LOCATION_AGING_SECONDS`, and
`TIE_LOCATION_EXPIRED_SECONDS` set the boundary windows. Capture timestamps
are validated using `TIE_LOCATION_CLOCK_SKEW_SECONDS` before classification.

## Operation profiles

`UthengaTieLocationOperationProfiles` is the sole shared suitability policy.
It evaluates named operations (`nearby_search`, `trip_planning`,
`regional_context`, `routing`, and `live_journey_tracking`) against accuracy
and freshness classes. Location-aware modules consume the resulting eligibility
instead of raw meters or timestamps.

Each `location-context/v1` response includes `accuracy_classification`,
`freshness`, `freshness_age_seconds`, and operation-profile results under
`metadata`. No classifier requests location, stores a history, or logs exact
coordinates.
