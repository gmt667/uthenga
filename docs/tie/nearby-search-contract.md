# TIE nearby-search contract

## Scope

Nearby Search answers only: “Which eligible Uthenga marketplace services are
geographically close to this validated location?” It does not rank services,
calculate a route, estimate travel time, or produce an ETA.

The API is `POST /api/tie/location/nearby.php`. It requires the existing TIE
location and availability feature flags, authentication, and CSRF protection.

## Request: `nearby-search-request/v1`

The request combines the existing `location-context` inputs with Phase 3 query
criteria. It does not introduce a second filtering language.

```json
{
  "latitude": -13.9626,
  "longitude": 33.7741,
  "accuracy_m": 18,
  "captured_at": "2026-07-29T08:30:00Z",
  "source": "browser_geolocation",
  "permission": "granted",
  "radius_km": 5,
  "category": "accommodation",
  "date": "2026-08-01",
  "quantity": 1,
  "availability": "available"
}
```

The canonical server representation contains `location`, `radius_km`,
`category`, `date`, `quantity`, `query_filters`, and `request_metadata`.
Unknown request fields are rejected before a marketplace query occurs.

The deployed category registry is `nearby-marketplace-categories/v1`:
`accommodation`, `transport`, `event`, and `tour`. It is derived from the
Phase 3 marketplace contract; no restaurant, attraction, or other category is
invented before canonical inventory supports it.

## Response: `nearby-search-response/v1`

```json
{
  "schema_version": "nearby-search-response/v1",
  "distance_semantics": {
    "type": "GEOGRAPHIC",
    "unit": "km"
  },
  "results": [
    {
      "candidate": { "service_id": "LST001", "category": { "code": "accommodation" } },
      "distance": { "value_km": 3.2, "type": "GEOGRAPHIC", "unit": "km" },
      "location": { "coordinate_status": "listing_coordinates", "quality": "VERIFIED" },
      "validation": { "eligible": true },
      "provenance": { "system": "uthenga" }
    }
  ]
}
```

`GEOGRAPHIC` means straight-line separation between two coordinates. It is not
driving, walking, cycling, transit, road distance, route geometry, or ETA.
Future routing can add those capabilities without changing this contract.

## Mandatory integration boundary

```text
Validated LocationContext
        -> Phase 3 Query Engine
        -> Phase 4 Availability Engine
        -> nearby-search-response/v1
```

Nearby Search never queries listings directly. It receives normalized Phase 3
candidates and exposes only candidates that pass Phase 4 validation. Results
are deterministically ordered by geographic distance, then declared
availability on a distance tie, then service ID.
