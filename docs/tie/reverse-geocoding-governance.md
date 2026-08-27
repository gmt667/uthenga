# TIE reverse-geocoding resilience and cache governance

## Failure policy

Coordinates are authoritative once validated. Reverse geocoding is enrichment
only: `not_configured`, `rate_limited`, `provider_unavailable`, or `unresolved`
GeographicContext outcomes never invalidate a LocationContext, nearby search,
or deterministic distance calculation.

## Cache policy

The geographic cache stores only normalized provider results. It is disabled by
default and process-local when enabled. Keys are hashes of provider and rounded
coordinates; entries contain no user/session identifier, raw provider payload,
or movement history. Failed provider calls are never cached.

Configuration controls TTL and maximum entry count:

- `TIE_GEOGRAPHIC_CONTEXT_CACHE_SECONDS`
- `TIE_GEOGRAPHIC_CONTEXT_CACHE_MAX_ENTRIES`

The cache implementation exposes provider-scoped or complete invalidation for
deployment/administrative maintenance hooks. Because it is process-local, it is
not represented as a cross-node production cache; a shared cache requires a
separate provider-compliance review before adoption.

## Provider compliance

Adapters own provider endpoints, timeouts, user agents, retry count, rate
limits, fallback order, attribution, licensing, and retention requirements.
The included Nominatim adapter is opt-in. Before enabling any provider in a
production environment, confirm its current usage, attribution, caching, and
retention terms; do not enable long-lived caching by default.

## Observability

Safe metrics record latency, cache hits/misses, provider failures, fallback
count, and rate-limit events, all with provider/status dimensions only. Logs
exclude coordinates, resolved addresses, user identifiers, and raw payloads.
