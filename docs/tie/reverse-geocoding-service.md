# TIE Reverse Geocoding Service

## Boundary

`UthengaTieReverseGeocodingService` is the only TIE component that calls a
`UthengaTieGeocodingProvider`. The Location Engine supplies validated
coordinates and receives only `geographic-context/v1`; Context, planning,
routing, and future AI modules never receive provider payloads or SDK objects.

```text
Validated LocationContext
        -> Reverse Geocoding Service
        -> Configured provider and fallbacks
        -> Geographic normalizer
        -> geographic-context/v1
```

## Provider configuration

Provider selection is configuration-driven:

- `TIE_GEOCODER_ENABLED`
- `TIE_GEOCODER_PROVIDER`
- `TIE_GEOCODER_FALLBACK_PROVIDERS`
- `TIE_GEOCODER_TIMEOUT_SECONDS`
- `TIE_GEOCODER_RETRY_ATTEMPTS`
- `TIE_GEOCODER_RATE_LIMIT_PER_MINUTE`
- `TIE_GEOGRAPHIC_CONTEXT_CACHE_SECONDS`

The current adapter is Nominatim. Adding Google, offline, or test adapters
requires only a provider implementation/factory entry; downstream TIE modules
remain unchanged. An unavailable or unknown provider is isolated behind the
same interface.

## Deterministic degradation

Resolution yields one normalized status: `resolved`, `unresolved`,
`not_configured`, `provider_unavailable`, or `rate_limited`. Provider failure
does not invalidate the source LocationContext. Failures are never cached.

Provider, cache outcome, and normalized status are available as provenance.
Logs must continue to omit coordinates and resolved addresses associated with a
user.
