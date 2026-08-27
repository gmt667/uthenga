# TIE GeographicContext

## Contract

Phase 6.8 adds `geographic_context` to `location-context/v1`. It is a
provider-neutral administrative description of the current coordinate:

```json
{
  "status": "resolved",
  "provider": "configured_provider",
  "country": "Malawi",
  "region": "Central Region",
  "city": "Lilongwe",
  "provenance": {
    "provider": "configured_provider",
    "source_type": "reverse_geocoding",
    "resolution_status": "resolved",
    "cache": "disabled"
  }
}
```

Country, region, district, city, area, and address form the stable hierarchy.
They are `null` when unresolved and are never fabricated. The context carries
`geographic-context/v1` plus provider, normalization, resolution, cache, and
resolution-time provenance.

## Failure and privacy behavior

An unconfigured provider returns `not_configured`; a configured provider with
no usable administrative labels returns `unresolved`; a failing configured
provider returns `provider_unavailable`. None of these outcomes invalidates the
coordinate or calls a routing/AI provider.

The optional geographic cache is disabled by default. When enabled through
`TIE_GEOGRAPHIC_CONTEXT_CACHE_SECONDS`, it is process-local, keyed only by a
rounded coordinate/provider hash, contains no user identity, and never stores
provider failures. Location observations themselves remain ephemeral.

The Context Engine embeds this normalized geographic context in TravelContext.
Consumers do not access Nominatim, Google, browser geocoders, or provider
response payloads directly. The Reverse Geocoding Service owns configured
provider selection, retry count, rate limit, fallback order, cache usage, and
normalization.
