# TIE API

| Endpoint | Method | Auth | Flag | Current behaviour |
| --- | --- | --- | --- | --- |
| `/api/tie/health.php` | GET | No | none | Reports service/configuration-safe health. |
| `/api/tie/context.php` | GET | Session required | `TIE_ENABLED` | Returns a purpose-limited foundation user context. |
| `/api/tie/context/build.php` | POST | Session + CSRF | `TIE_ENABLED` and `TIE_CONTEXT_ENABLED` | Builds a versioned deterministic TravelContext. |
| `/api/tie/trips.php` | POST | Session + CSRF | `TIE_ENABLED` and `TIE_TRIP_PLANNER_ENABLED` | Validates a TripRequest and returns an unpersisted empty draft. |
| `/api/tie/recommendations.php` | POST | Session + CSRF | Recommendation, context, query, and availability flags | Returns deterministic, explainable `recommendation-result/v1`; never books, reserves, or invokes an LLM. |
| `/api/tie/conversation/chat.php` | POST | Session + CSRF | AI, LLM, recommendation, context, query, and availability flags | Server-orchestrates deterministic evidence, then returns validated conversational guidance. It never books, reserves, or accepts client marketplace facts/history. |
| `/api/tie/plans/create.php` | POST | Session + CSRF | Plan, recommendation, context, query, and availability flags | Persists an approval-driven TIE draft in `trip_itineraries`; never books or reserves. |
| `/api/tie/plans/view.php` | GET | Session required | Plan flag | Returns only a plan owned by the authenticated user. |
| `/api/tie/plans/update.php` | POST | Session + CSRF | Plan and deterministic dependency flags | Removes, reorders, or replaces a service from current validated recommendations, then revalidates. |
| `/api/tie/plans/validate.php` | POST | Session + CSRF | Plan, query, and availability flags | Rechecks every planned service through Availability. |
| `/api/tie/plans/approve.php` | POST | Session + CSRF | Plan, query, and availability flags | Records user approval of a validated proposal; does not book. |
| `/api/tie/plans/export.php` | POST | Session + CSRF | Plan flag | Returns a read-only JSON export; an approved plan may transition to exported. |
| `/api/tie/bookings/validate.php` | POST | Session + CSRF | Booking, plan, query, and availability flags | Performs fresh validation of an approved plan without booking. |
| `/api/tie/bookings/execute.php` | POST | Session + CSRF | Booking, plan, query, and availability flags | Idempotently orchestrates the existing marketplace booking API; disabled unless payment policy permits execution. |
| `/api/tie/bookings/cancel.php` | POST | Session + CSRF | Booking flag | Requests cancellation through the existing marketplace cancellation route. |
| `/api/tie/bookings/status.php` | GET | Session required | Booking flag | Returns an owner-scoped execution state and operation summary. |
| `/api/tie/services.php` | GET | Public marketplace scope | `TIE_ENABLED` and `TIE_QUERY_ENABLED` | Read-only normalized published-service search. |
| `/api/tie/vendors.php` | GET | Public marketplace scope | `TIE_ENABLED` and `TIE_QUERY_ENABLED` | Read-only vendor aggregation from eligible published services. |
| `/api/tie/categories.php` | GET | Public marketplace scope | `TIE_ENABLED` and `TIE_QUERY_ENABLED` | Normalized active service categories and counts. |
| `/api/tie/availability/validate.php` | POST | Session + CSRF | `TIE_ENABLED` and `TIE_AVAILABILITY_ENABLED` | Deterministic diagnostic validation; does not book or reserve inventory. |
| `/api/tie/location/context.php` | POST | Session + CSRF | `TIE_ENABLED` and `TIE_LOCATION_ENABLED` | Privacy-minimized `location-context-response/v1`; denied/unavailable location returns a non-blocking fallback contract. |
| `/api/tie/location/permission.php` | POST | Session + CSRF | `TIE_ENABLED` and `TIE_LOCATION_ENABLED` | Session-only normalized location-permission update; accepts no coordinates. |
| `/api/tie/location/nearby.php` | POST | Session + CSRF | Location + availability flags | Versioned `nearby-search-response/v1` for eligible services by straight-line geographic distance; session-limited by `TIE_LOCATION_NEARBY_RATE_LIMIT`. |
| `/api/tie/location/vendor-coordinate.php` | POST | Vendor/admin + CSRF | `TIE_ENABLED` and `TIE_LOCATION_ENABLED` | Explicit listing coordinate enrichment. |
| `/api/tie/location/vendor-coordinate-verify.php` | POST | Administrator + CSRF | `TIE_ENABLED` and `TIE_LOCATION_ENABLED` | Verifies or rejects a pending listing coordinate. |
| `/api/tie/location/vendor-coordinate-import.php` | POST | Administrator + CSRF | `TIE_ENABLED` and `TIE_LOCATION_ENABLED` | Atomically imports 1–configured maximum listing coordinates; every imported coordinate begins `pending_review`. |

All responses contain `request_id`. Failures use `success: false` and an `error` object with a normalized type and message. Endpoints do not expose provider credentials or raw internal exceptions.

Catalogue endpoints expose only public marketplace fields. They do not return
customer data, payment information, unapproved vendors, inactive listings, or
raw `meta` payloads. See [query-engine.md](query-engine.md) for filters and
the normalized result contract.
