# Uthenga TIE — Phase 6.17 Location API, Provider Architecture & Operational Hardening

## Outcome

Phase 6.17 formalizes the production boundary of the completed Location
subsystem. It preserves the existing deterministic Query and Availability
engines and adds no routing, recommendations, AI, tracking, or marketplace
persistence.

## Delivered

- A provider-neutral foreground geolocation descriptor, reverse-geocoding
  interface, and MariaDB geographic-search adapter.
- Explicit server-authoritative input policy: nearby clients can send only
  observations, never marketplace facts.
- Endpoint-specific configurable limits for context, permission, nearby,
  vendor coordinate update/review/import, and reverse geocoding.
- Rate-limit and successful-nearby-response metrics without location data.
- A standardized fallback response when the geographic-search provider is
  unavailable; normal catalogue search remains available.
- Formal provider, failure, and operational documentation.

## Deliberate API decision

Nearby remains a versioned `POST` API. The roadmap’s example `GET` is not used
because its request requires an authenticated, CSRF-protected foreground
observation. No coordinate is placed in a URL or server log.
