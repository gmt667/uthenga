# TIE geospatial production operations

## Coordinate quality policy

Marketplace coordinates have a computed, provider-neutral quality state:

| State | Meaning | Precision-search eligible |
| --- | --- | --- |
| `VERIFIED` | Valid coordinate with a recent administrator verification. | Yes |
| `UNVERIFIED` | Valid coordinate awaiting review or not verified. | No |
| `MISSING` | Neither coordinate is present. | No |
| `STALE` | Verified coordinate is older than the review policy permits. | No |
| `INVALID` | Coordinate is incomplete or outside valid geographic bounds. | No |

`TIE_VENDOR_LOCATION_STALE_DAYS` defaults to `365`. Staleness is calculated
from `location_verified_at`, falling back to `location_captured_at`; it does
not overwrite marketplace records. A stale listing must be resubmitted or
reviewed before it becomes eligible for a precision radius search again.

## Deterministic nearby search

The Query Engine performs a verified, non-stale bounding-box prefilter and an
exact Haversine radius check. The Availability Engine then removes ineligible
services. Final nearby ordering is deterministic:

1. straight-line distance ascending;
2. declared available units descending when distance is equal;
3. service ID ascending when both values are equal.

This is discovery ordering only. It does not imply recommendation scoring,
road distance, travel time, or ETA.

## Controlled imports

`POST /api/tie/location/vendor-coordinate-import.php` is an administrator-only
and CSRF-protected bulk import endpoint. It accepts one to
`TIE_VENDOR_COORDINATE_IMPORT_MAX_ENTRIES` records per atomic request. Each
entry requires `service_id`, `latitude`, and `longitude`; it may include
`accuracy_m` and ISO-8601 `captured_at`.

Every imported coordinate is validated through the Location contract, recorded
with source `imported`, written to the audit table, and set to
`pending_review`. Importing never makes a coordinate precision-searchable. An
administrator must use the existing review endpoint to verify or reject it.

## Observability and routine review

Nearby requests emit safe latency, candidate, eligible, rejection,
missing-coordinate, and radius metrics. No coordinate, address, user ID, or
session ID is logged.

Run the following with XAMPP PHP from the repository root to record and print
the current quality distribution. It is suitable for a scheduled operational
job:

```bash
/opt/lampp/bin/php php_app/tools/tie_geospatial_diagnostics.php
```

The output contains only state counts and the stale cutoff. Investigate
`STALE`, `UNVERIFIED`, `MISSING`, and `INVALID` counts through the vendor and
administrator workflows; never infer or fabricate a coordinate from a textual
address.
