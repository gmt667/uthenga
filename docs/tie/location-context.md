# TIE canonical LocationContext

## Contract

`location-context/v1` is the sole internal representation of a verified,
single location observation. It is provider-neutral and contains no movement
history, device identifier, or tracking token.

The required core fields are `schema_version`, `latitude`, `longitude`,
`captured_at`, and `source`. A device-derived observation also includes its
measured `accuracy_m`, for example:

```json
{
  "schema_version": "location-context/v1",
  "latitude": -13.962612,
  "longitude": 33.774199,
  "accuracy_m": 12.35,
  "captured_at": "2020-07-29T06:30:00Z",
  "source": "browser_geolocation"
}
```

`accuracy_m` is present when the provider can measure it. When it is absent,
the coordinate remains valid but Phase 6.5 classifies it as `UNKNOWN` and
precision-sensitive operation profiles reject it. Optional fields
(`accuracy_m`, `altitude_m`, `heading`, and `speed_mps`) are omitted when the
platform has not supplied them. Consent, provenance, freshness, confidence,
provider, and ephemeral status are added by the Location Engine only when
available and relevant.

`confidence` is the deterministic overall reliability classification. The
optional `geographic_context` is `geographic-context/v1` and carries the stable
country/region/district/city/area/address hierarchy. Unresolved levels are
explicitly `null`, with provider, normalization, and resolution provenance.

## Validation boundary

`UthengaTieCoordinateValidator` is the server-side entry point for every TIE
coordinate observation. It rejects missing, non-numeric, non-finite,
out-of-range, unsupported-source, and malformed-timestamp inputs before a
LocationContext can be constructed. It accepts inclusive latitude bounds
`-90..90` and longitude bounds `-180..180`.

Errors use the existing `validation_error` response envelope with field-level,
machine-readable codes. Observability records only generic request outcome and
correlation metadata; it never logs raw coordinates.

## Consumers

The Location Engine produces this DTO. Nearby Search and TravelContext consume
it internally; API responses serialize it through `toArray()`. Browser/device
permission APIs and geocoder-specific response shapes do not cross this
boundary.
