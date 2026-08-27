# ADR 006: Geospatial storage architecture

**Status:** Accepted for the current marketplace stage

## Context

The verified XAMPP environment runs MariaDB `10.4.32`. The marketplace has 11
listings, zero complete listing coordinates, and an existing composite
`idx_listings_geo` index. Phase 3/5 query behavior is an index-friendly
latitude/longitude bounding-box prefilter followed by exact Haversine radius
validation.

There is no production-volume benchmark yet because no verified coordinate data
exists. A storage replacement would therefore be an assumption rather than a
measured requirement.

## Options considered

| Option | Decision | Reason |
| --- | --- | --- |
| Decimal latitude/longitude + composite index + Haversine | Accepted | Matches current scale, deployment, and radius-search requirements with minimal operational cost. |
| MariaDB spatial types/indexes | Deferred | Revisit when verified coordinate coverage and query-volume evidence justify migration. |
| Dedicated geospatial service/database | Rejected for now | Adds operational complexity without a demonstrated spatial requirement. |
| Existing infrastructure enhancement | Covered by accepted option | The existing MariaDB deployment and index satisfy the present need. |

## Decision

Keep the current decimal coordinate columns, verified-coordinate index,
bounding-box prefilter, and exact Haversine calculation. Only coordinates with
`location_verification_status = 'verified'` may participate in precision radius
search.

## Revisit triggers

Reassess after coordinate enrichment when at least one of these is measured:

- radius-search latency fails the agreed product SLO;
- catalogue size or location-query volume materially exceeds the current design;
- product requirements need polygon, nearest-neighbour, route-corridor, or
  other spatial operations not served efficiently by the current approach;
- production database topology differs materially from the validated MariaDB
  baseline.
