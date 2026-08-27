# TIE location permission and consent contract

## Canonical permission model

TIE maps client-specific values to one internal state: `NOT_REQUESTED`,
`REQUESTED`, `GRANTED`, `DENIED`, `UNAVAILABLE`, `RESTRICTED`, or `EXPIRED`.
Browser `prompt`, Android `permanently denied`, and iOS `when in use` normalize
to `NOT_REQUESTED`, `DENIED`, and `GRANTED` respectively.

The lifecycle is validated in the active PHP session for device-derived
locations. A denied permission must be requested again before it can become
granted. Manual, vendor, and geocoded locations carry provenance without
altering device permission state. Permission state is never stored in the
database, inferred from coordinates, or used to enable tracking.

## Consent and provenance contract

Every normalized location context includes a top-level source and permission,
plus `consent` and `provenance` objects. They expose the normalized permission
state, platform, provider, capture timestamp, accuracy, coordinate precision,
freshness, and `ephemeral: true` status.

Supported sources are `browser_geolocation`, `device_gps`, `manual_location`,
`saved_location`, `vendor_location`, and `geocoded_address`. Device-derived
locations require an explicit `GRANTED` state and accuracy measurement. Unknown
sources and invalid lifecycle transitions are rejected.

Only the current request's approved location context is made available to the
Context Engine. No historical locations, movement records, or tracking IDs are
created.

## Permission API

`POST /api/tie/location/permission.php` accepts a platform and platform-native
permission state, normalizes it, and updates the active session only. It
requires authentication, CSRF protection, and `TIE_LOCATION_ENABLED`; it does
not accept coordinates. States other than `GRANTED` include a non-blocking
fallback contract: search by city, district, or destination, or choose a
location manually. Permission denial never blocks marketplace browsing,
destination search, booking, or an explicitly chosen manual location.
