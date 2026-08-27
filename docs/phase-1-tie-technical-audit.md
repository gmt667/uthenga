# Uthenga Travel Intelligence Engine — Phase 1 Technical Audit

**Status:** Source audit complete; Phase 1 live-environment validation pending; no TIE implementation performed  
**Audit date:** 2026-07-28  
**Method:** Read-only source and schema audit. No database writes, configuration changes, or production-behaviour changes were made.

## 01 Executive summary

Uthenga is a single, server-rendered PHP marketplace application backed by MySQL. Its active application code is built around a unified `listings` table and a central action router (`request_api.php`). It already provides discovery, account and vendor roles, bookings, payment callbacks, reviews, favourites, basic availability, maps, weather, a rule-based Trip Planner, and a Gemini-backed chat feature.

TIE should be introduced as an in-process PHP module behind explicit internal interfaces, not as a new microservice and not as a replacement for existing booking or marketplace code. Its initial job should be to orchestrate verified catalogue retrieval, availability checks, deterministic pricing/budget calculations, and optional LLM generation of structured itinerary prose. The existing booking flow remains the sole authority for bookings and payments.

The primary blocking architectural issue is **schema and contract drift**. Runtime pages query a legacy/unified `listings` model with type-specific JSON metadata, while `database/production_schema.sql` defines a richer normalized domain model for properties, rooms, routes, schedules, and tours. Several compatibility migrations and runtime `table_exists`/`column_exists` branches support both models. Before Phase 2, the deployed database schema and canonical inventory contract must be measured and chosen. TIE must not independently read arbitrary JSON metadata and declare it authoritative.

The current Trip Planner is a useful UI entry point but is not a safe planning engine. It accepts one text query, regex-parses only duration and a numeric budget, uses a static Malawi location catalogue, selects the first few matching listings, and emits an itinerary with hard-coded fallbacks. It neither performs a date-aware availability check nor validates its total against the submitted budget. It should be extended behind a stable response contract rather than rewritten in place.

### Phase 1 completion decision

Phase 1 has established the source-level baseline and a smallest viable integration design. Phase 2 should **not** start until the data/schema verification checklist in sections 19 and 21 has been completed in a controlled environment.

## 02 Repository structure

This is a single-repository PHP application. There is no JavaScript package manifest, Composer manifest, container definition, CI workflow, or separate frontend/backend application in this checkout.

```text
uthenga/
├── index.php                         Root bootstrap to php_app/index.php
├── .cpanel.yml                       cPanel rsync deployment definition
├── .env.example                      Root environment template
├── README.md
└── php_app/                          Deployable application
    ├── *.php                         Public pages and central request router
    ├── api/                          JSON endpoints, including ai/
    ├── admin/                        Admin portal
    ├── vendor/                       Vendor onboarding and portal
    ├── mbanda/                       Ride-sharing feature
    ├── payments/                     Checkout and payment callbacks
    ├── auth/                         OAuth and 2FA flows
    ├── includes/                     Shared auth, catalogue, functions, UI helpers
    ├── database/
    │   ├── migrations/               Additive and compatibility migrations
    │   ├── production_schema.sql     Rich normalized schema proposal
    │   └── additional_seed_data.sql
    ├── install/                      Legacy/setup schema and seed scripts
    ├── assets/                       CSS, JavaScript, images
    ├── cache/                        File cache directory
    └── config.php, db.php            Bootstrap and PDO access
```

`index.php` at the repository root is a deployment bootstrap. The cPanel deployment copies `php_app/` into `public_html/` and intentionally excludes `.env`, `database/`, `install/`, and `README.md`. Therefore the production application is a traditional PHP deployment, with schema installation/migration performed separately.

### Tooling and test posture

The checkout contains a manual shop smoke-test checklist and application migration scripts, but no automated test suite or CI configuration was found. The audit environment has no `php` executable or MySQL client, no checked-in local `.env`, and no local `config.local.php`; the application and live database could not be executed or profiled here. This report labels live-data assertions as **unverified** rather than guessing from seed/fallback content.

## 03 Current architecture

```text
Browser
  │  server-rendered forms / fetch()
  ▼
PHP public pages, vendor pages, admin pages
  │
  ├── request_api.php                 Central JSON action router
  ├── api/*.php                       Focused JSON endpoints
  ├── api/ai/*.php                    Chat, budget, itinerary, recommendation
  └── payments/*.php                  Payment initiation/callbacks
       │
       ├── includes/catalog.php        Unified catalogue reads and file cache
       ├── includes/auth*.php          Session roles and authorization
       └── db.php                      PDO MySQL helper layer
             │
             ▼
         MySQL (deployed schema must be verified)

External services:
  PayChangu; Airtel/TNM callback paths; Google/Facebook/Microsoft OAuth;
  Gemini (optional); Open-Meteo; Leaflet/OpenStreetMap tiles; CDN assets.
```

### Communication and service boundaries

- The frontend is PHP-rendered HTML with small inline JavaScript and `assets/js/main.js`; it is not a separate SPA.
- `request_api.php` is the main API boundary for catalogue fetches, booking, cancellation/refund, wishlists, vendor listing creation, and several admin operations.
- Focused endpoints supplement that router: room availability, reviews, favourites, notifications, weather, maps, gates, and AI features.
- The code uses session state and PDO directly. There is no internal HTTP service mesh, message broker, Redis, background-worker, WebSocket, or serverless boundary found.
- Caching is filesystem-based (`php_app/cache`) plus short-lived PHP session data. The catalogue cache serializes values to disk.

## 04 Frontend capability map

| Area | Existing behaviour | TIE relevance |
| --- | --- | --- |
| Public discovery | Home, events, hotels/hostels/lodges, tours, transport, tourism, marketplace, car rental, airport transfer, and Mbanda pages. Searches are primarily text `LIKE` filters over listings. | Reuse visual listing/detail/booking destinations; do not create a second catalogue UI. |
| Service detail | `event-details.php` resolves a listing, displays typed price/inventory controls, reviews, favourite control, booking form, and a Leaflet map. | Return listing identifiers and deep links from TIE. |
| Booking | Forms submit to `request_api.php` with `create_booking`; the user sees booking/ticket pages. | TIE must hand off to this flow, never manufacture a booking. |
| Trip Planner | `trip-planner.php` has one required free-text field, loading/results UI, weather lookup, printable itinerary link, and direct-booking suggestions. | Retain as Phase 2 entry point while replacing its internal planning pipeline. |
| AI chat | Global footer widget and dedicated `ai/chat.php` call AI endpoints. | A future conversational planner can share a session/context service, not raw browser history. |
| User surfaces | Login/register/profile/dashboard/bookings/wishlist. Profile stores identity/contact and notification/security preferences. | Account ID, bookings, favourites and reviews are useful opt-in context. Travel preferences do not yet exist. |
| Vendor surfaces | Register, pending approval, portal, business listing, analytics, ads, withdrawals. | Vendor status and active inventory must be filtered before planning. |
| Maps | Tourism, marketplace, event and detail pages use Leaflet with OpenStreetMap tiles. | Extend the existing map provider first; do not add Google Maps without a product decision. |

No browser `navigator.geolocation` use, location-permission UI, current-location storage, origin input, route renderer, ETA UI, or live journey UI was found.

## 05 Backend and API inventory

The following inventory covers endpoints relevant to TIE and its hand-off paths. The API layer is form-oriented rather than a versioned REST API.

| Endpoint / action | Method | Authentication | Purpose and main input | Output / dependency | TIE action |
| --- | --- | --- | --- | --- | --- |
| `request_api.php?action=get_listings` | POST | No | `type`, `q` | Normalized active listing feed through catalogue helpers | Reuse internally; create a typed retrieval interface rather than call HTTP from TIE. |
| `request_api.php?action=create_booking` | POST | Customer + CSRF | Listing, type, quantity, optional room/seat/ticket type, payment gateway/coupon/dates | Validates listing/price, reserves supported inventory atomically, creates booking/transaction artifacts | Reuse as the booking authority; TIE only creates a hand-off. |
| `request_api.php?action=cancel_booking` | POST | Booking owner/admin + CSRF | Booking ID | Changes booking state and restores inventory where supported | Do not duplicate. |
| `request_api.php?action=refund_booking` / `admin_update_booking` | POST | Admin + CSRF | Booking and status/refund details | Admin lifecycle operations | Do not expose through TIE. |
| `request_api.php?action=create_listing` | POST | Vendor + CSRF | Common fields and type-specific metadata | Creates generic listing; category data goes into JSON `meta` | Reuse publishing flow; add canonical validation later. |
| `api/room-availability.php` | GET | No | `property_id/listing_id`, check-in/out | Room availability from `room_types`, `property_rooms/room_availability`, or legacy metadata | Reuse/extend; current output has no requested room quantity or total-stay price. |
| `api/trip_planner.php` | POST or GET | No | `query` | Current itinerary response and optional session persistence | Replace internals behind an explicit versioned contract. |
| `api/ai/chat.php` | POST JSON | No | `message`, browser-supplied `history[]` | Free-text Gemini/local response and static suggestion chips | Keep separate from booking; put behind a gateway and apply authenticated context controls. |
| `api/ai/recommend.php` | GET/POST | No; accepts caller `user_id` | type, location, budget, limit, user ID | Rating/date ordered candidates with basic prior-booking penalty | Replace/extend; do not trust caller-provided user ID. |
| `api/ai/itinerary.php` | POST JSON/form | No | destination, days, interests, style | Template itinerary plus mixed live/static attractions | Retire from booking-safe use or rework behind TIE. |
| `api/ai/budget.php` | POST JSON/form | No | destination, days, travellers, style | Static budget template with optional live price averages | Replace with deterministic quote calculation service. |
| `api/map_points.php` | GET | No | optional type | Active point records | Reuse for map overlays; not a nearby/radius endpoint. |
| `api/weather.php` | GET | No | city | Cached current Open-Meteo weather | Reuse as optional enrichment after canonical location resolution. |
| `api/notifications.php` | GET/POST | Intended authenticated session | Read/mark notifications | Notification records | Reuse later for opt-in trip alerts; audit its login guard before use. |
| `api/submit-review.php`, `api/toggle-favorite.php`, `api/track_event_view.php` | POST | Review/favourite require login | Engagement signals | Reviews/favourites/analytics | Read through controlled internal queries for later ranking. |
| `api/validate-promo.php`, `api/refund-request.php`, `api/gate_api.php` | POST/GET | Context-specific | Checkout promotion, refund, gate scanning | Booking lifecycle support | Not direct TIE dependencies. |

The `/api/auth`, `/api/bookings`, `/api/vendors`, and `/api/admin` index files are compatibility forwards to the central router rather than independent domain APIs.

## 06 Database and intelligence data map

### 06.1 Runtime/legacy catalogue model

The code paths used by public pages and the current planner treat `listings` as the operational catalogue:

```text
users ──< listings ──< reviews
   │          │
   │          ├── ticket_types / seat_classes / room_types (optional detail tables)
   │          └── bookings (legacy direct listing reference)
   │
   ├── vendor_profiles / vendors
   ├── favourites / wishlist / recent_views
   ├── trip_planner_sessions / trip_itineraries
   └── notifications / audit and security tables
```

`listings` has ID, `listing_type` (`event`, `accommodation`, `tour`, `transport`), title, description, string location, vendor ID/name, rating, featured/active flags, images, and JSON `meta`. `meta` holds category-specific price, rooms, capacity, route, schedule, and availability conventions. `includes/catalog.php` extracts a first price from different JSON keys.

This provides immediate broad marketplace coverage but is insufficient as a single TIE truth source because type-specific facts are untyped, have multiple naming conventions, and are not consistently date-aware.

### 06.2 Inventory, availability, and booking facts

| Domain | Current source-level support | TIE interpretation |
| --- | --- | --- |
| Events | Listing plus optional `ticket_types` (price, total/remaining quantity, sale period) | Candidate eligibility should use active tickets, sale window, remaining quantity and requested date. |
| Transport | Listing plus optional `seat_classes` (price, total/remaining seats) and route metadata | No canonical departure-date schedule in the runtime unified model was proven. Do not claim a route is available until a schedule contract exists. |
| Accommodation | Listing plus optional `room_types` (per-night price, available rooms) and optional `property_rooms` / `room_availability` daily table | `api/room-availability.php` is the strongest current availability hook, but a booking decrements room-level count rather than a demonstrated stay-date allocation in the legacy flow. |
| Tours | Listing and price metadata | No robust capacity/date availability contract was found in the runtime path. |
| Mbanda | `ride_sharing_trips` and `ride_sharing_bookings`; departure datetime and seat fields | Distinct travel inventory, useful only after inclusion in the canonical retrieval policy. |
| Local businesses | `local_business_listings` plus city/address and optional `lat`/`lng`, price range/rating | Suitable as an enrichment source, not necessarily bookable inventory. |

The booking router is the authoritative transaction boundary. It re-reads the listing and price server-side, starts a transaction, atomically decrements ticket/seat/room inventory when optional typed tables are used, creates a booking and, in the modern schema branch, booking items. It supports cancellation/refund paths. This is the correct final validation point for TIE recommendations.

### 06.3 Rich normalized schema and drift risk

`database/production_schema.sql` defines a more normalized target: country/city/location, vendors, events, properties/property rooms/room availability, transport providers/routes/vehicles/schedules/seat allocations, tour packages/tour bookings, generic bookings/booking items, reviews, favourites, analytics, notifications, and payments.

The code is explicitly compatibility-aware: it checks tables and columns at runtime and switches between unified/legacy and normalized/modern flows. `install/setup.sql` and several migrations define a different generic model; comments in migration 008 also state that some rich entities are omitted because the unified listing is used. `install/verify.php` expects names that do not exactly match the rich schema. This is evidence of an unresolved deployment-schema contract, not evidence that either schema is currently live.

**Required Phase 2 gate:** run a read-only production/staging schema inventory (`SHOW TABLES`, `SHOW CREATE TABLE`, row counts and null/duplicate profiles) and designate one canonical read model. If the unified listing model remains canonical initially, define a typed inventory projection that normalizes it; do not introduce a third parallel inventory table.

### 06.4 Location data assessment

| Source | Location form | Geospatial readiness |
| --- | --- | --- |
| `listings` | Free-text `location`; optional `gps_lat`, `gps_lng` added by migration | Text filtering is used; coordinate completeness unverified. |
| `local_business_listings` | City/address plus optional `lat`, `lng` | Has coordinates but no spatial index/geospatial query in inspected runtime code. |
| `map_points` | Latitude/longitude, city/address/type | Has a latitude/longitude index and is used for static points. |
| `malawi_locations.php` | Static district/city aliases and descriptive data | Useful resolver fallback, not vendor inventory or live geocoding. |
| Rich schema | `locations` and associated property/route maps | Not proven to be the runtime source. |

No spatial database type or `ST_Distance`/radius query was found. Near-me capability will require coordinate completeness, an agreed distance-query strategy, and explicit consent.

### 06.5 Data-quality and AI-readiness status

Live data quality cannot be scored in this audit because the database was unavailable. The following are source-level risks that must be measured:

| Dataset | Availability | Quality risk | Required profile |
| --- | --- | --- | --- |
| Listings | Present in runtime design | JSON-key drift, string-only locations, missing/zero prices, fallback/mock content | Counts by type/status; price and coordinate null rates; metadata-schema validity; duplicate title/vendor/location rate. |
| Vendors | Users plus profiles/vendors | Approval status represented in more than one place | Counts and agreement of user/vendor/profile statuses; missing city/category/contact. |
| Availability | Partial and category-specific | Legacy quantities can be non-date-aware; tours lack demonstrated availability | Coverage by category and date; zero/negative counts; booking-to-inventory reconciliation. |
| Bookings | Present with legacy/modern branches | Schema-specific fields differ | Booking/payment-status distributions, cancellation/refund correctness, item/reference coverage. |
| Location | Partial | Coordinates optional, static city fallbacks may mask missing data | Valid lat/lng ranges; address/city completeness; coordinate-to-city consistency. |
| User signals | Bookings, favourites, reviews, recent views exist | No travel preference model; retention/consent unknown | User linkage coverage, consent basis, sparsity, and aggregation policy. |

The marketplace already has enough data to begin deterministic candidate retrieval in categories with valid listing, price, and availability support. It is not ready for trusted itinerary optimization until these profiles and canonical contracts exist.

## 07 Vendor and marketplace analysis

Vendor identities are represented through `users` roles and, depending on schema, `vendors` and/or `vendor_profiles`; vendor approval is enforced by shared authorization helpers and used as a concept in the chat prompt. Vendors publish listings through the vendor portal/central action flow. The shared listing schema gives a common discovery surface but uses category-specific JSON metadata for details.

TIE candidate retrieval must at minimum require:

1. An active listing.
2. A vendor that is approved/eligible according to the deployed canonical vendor rule.
3. A valid canonical price for the requested unit.
4. A location compatible with the requested destination or route.
5. Category-specific availability where the user supplied dates/quantities.

Restaurants and other local businesses should be explicitly marked **bookable**, **contact/reserve**, or **informational** in any itinerary item. The current planner sometimes falls back to names not sourced from Uthenga; Phase 2 must remove that behaviour from bookable recommendations.

## 08 Booking-system analysis

```text
Discover listing
  → event-details/category page
  → customer login and CSRF-protected booking form
  → request_api.php:create_booking
  → re-read active listing and authoritative typed price where available
  → transaction + supported atomic inventory decrement
  → booking/booking item + transaction artifacts
  → payment/success/callback flows and customer booking history
  → cancellation/refund/admin/vendor fulfilment paths
```

### Existing safeguards to preserve

- Price is re-read server-side rather than trusted from the page.
- Ticket/seat/room typed inventory updates use conditional atomic decrements in a database transaction.
- Booking actions require a customer session and CSRF token in the central router.
- Cancellation can restore inventory through central helper logic.

### Gaps relevant to TIE

- The public planner does not use this availability/price-validation path before making recommendations.
- Transport and tour date/schedule availability is not consistently exposed by a single contract.
- The legacy booking flow may mark bookings paid/confirmed before an external payment result; the exact payment lifecycle must be verified before any TIE checkout shortcut is considered.
- Room availability must be verified with a real-date inventory test before TIE uses it for multi-night planning.

TIE must produce selections and a quote timestamp, then redirect/hand off to the existing booking form. Booking remains responsible for the final quote, availability, payment, and confirmation.

## 09 Trip Planner current-state specification

### UI and request

`trip-planner.php` exposes one free-text field (`query`) and POSTs it to `api/trip_planner.php`. The UI shows destination, district, days, target budget, estimated cost, weather, a printable itinerary URL, and direct listing suggestions.

It does **not** collect structured origin, date range, traveller count, children/rooms, accessibility, travel mode, location permission, or persisted preferences.

### Current backend trace

```text
Free-text query
  → Regex extracts N-day duration (default 3, capped 14)
  → Regex extracts a number as budget (default MWK 500,000)
  → Static Malawi district/city alias resolver finds destination (default Lake Malawi)
  → SQL LIKE selection of up to 3 stays, 4 tours, 2 transport listings
  → Optional local-business restaurant lookup
  → First results and hard-coded fallback content form daily itinerary
  → JSON plan written to trip_planner_sessions when table exists
  → Browser renders itinerary and separately asks weather endpoint
```

### Response contract

The current response is:

```json
{
  "success": true,
  "id": 1234,
  "days": 3,
  "budget": 500000,
  "destination": "...",
  "district": "...",
  "estimated_cost": 0,
  "itinerary": [{"day": 1, "theme": "...", "activities": [{"time": "...", "title": "...", "description": "...", "cost": 0, "booking_url": null}]}],
  "suggestions": [{"type": "...", "title": "...", "location": "...", "price": 0, "image": "...", "url": "..."}]
}
```

`trip_planner_sessions` stores the original query, an unversioned JSON plan, duration, budget, destination, optional user ID, and PHP session key. `trip_itineraries` also exists in a migration but is not the observed planner persistence path.

### Existing intelligence and limitations

The planner is deterministic; it does not call an LLM. It uses static location aliases, SQL `LIKE`, first-result selection, price extraction from metadata, and hard-coded fallback activities/food/transport/costs. It has no candidate ranking, no request date interpretation, no origin/routing, no person-count calculation, no availability recheck, no vendor approval filter, no budget-feasibility enforcement, no response schema validation, and no plan-edit conversation state. It can present invented fallback items, which violates the proposed TIE truth boundary.

## 10 Location and maps analysis

Uthenga currently uses Leaflet 1.9.4 and OpenStreetMap tile servers in tourism, marketplace, events, and detail pages. Detail maps use a small hard-coded city substring-to-coordinate mapping; tourism/map-point pages use static map data. Open-Meteo is used for current weather and is cached for one hour in `weather_cache`.

There is no Google Maps, Mapbox, Places, geocoding, reverse geocoding, directions, traffic, route matrix, ETA, browser geolocation, GPS persistence, live vehicle tracking, route progress, or route enrichment integration in the inspected code.

**Integration choice:** Phase 2 should preserve Leaflet/OpenStreetMap rendering until product requirements choose a routing/geocoding provider. A routing provider is a new capability, not an extension of an existing Google Maps integration. The UI must request location only for the active feature, with clear purpose and retention controls.

## 11 User, authentication, and personalization analysis

Authentication is PHP-session based. Configuration starts a named session, uses HTTP-only cookies, sets `SameSite=Lax`, and marks cookies secure when HTTPS is detected. Shared helpers enforce customer/vendor/admin roles, login redirects, approval checks, and CSRF verification for many form actions. Password hashing and OAuth flows for Google, Facebook, and Microsoft are present; 2FA and device-session schema/features are also present.

Usable TIE context, subject to authorization and purpose limitation:

- Session user ID and role.
- Customer booking history.
- Favourites/wishlist, reviews, recent views, and notifications.
- User-entered trip constraints in the current plan.

Not currently modelled: explicit travel preferences, accessible-travel needs, dietary preferences, saved companions, preferred transport modes, explicit location-consent state, trip-level sharing controls, or itinerary versions. These require deliberate data models and consent—not inference from raw profile fields.

## 12 External integrations and infrastructure

| Provider/integration | Current purpose | Failure behaviour observed | TIE relevance |
| --- | --- | --- | --- |
| PayChangu | Payment initiation/callback configuration | Callback/payment code; live configuration unverified | Booking-only; TIE must not call it directly. |
| Airtel Money / TNM Mpamba / bank-card paths | Payment options/callbacks | Existing routes; provider details/config unverified | Booking-only. |
| Google, Facebook, Microsoft OAuth | Social login | Redirect/callback implementation | User authentication only; Google OAuth is not Maps. |
| Gemini `gemini-1.5-flash` | Optional chat response | 15-second cURL call; deterministic local fallback | Replace direct call with LLM gateway; upgrade/provider choice later. |
| Open-Meteo | Current weather | Five-second request, DB cache, error response | Optional enrichment; do not make planning depend on it. |
| Leaflet / OpenStreetMap | Map display and tiles | CDN/network dependent | Existing display layer. |
| Unsplash/CDN/JsBarcode | Images and client assets | External asset load | Not TIE sources of truth. |

Deployment is cPanel file synchronization. No Dockerfile, Compose configuration, queue/worker, Redis, cron declaration, CI/CD workflow, application metrics service, or central tracing service was found. PHP error logs and audit-log tables are the observed operational facilities. A future queue/realtime system is not justified in the first TIE slice; LLM calls can be synchronous with strict timeout and graceful fallback.

## 13 AI readiness assessment

### Existing AI-related implementation

- `api/ai/chat.php` passes browser-supplied history plus a prompt built from top active listings, approved vendor details (including phone), static Malawi location descriptions, and provider API key to Gemini when configured. It otherwise uses keyword/rule replies.
- `api/ai/recommend.php` ranks active listings mainly by rating/recency, filters a per-item budget, and reduces score for prior bookings. The caller supplies `user_id`.
- `api/ai/itinerary.php` combines active listings with static attraction text.
- `api/ai/budget.php` is primarily a static daily-cost table, with optional listing-price averages.

### Readiness conclusion

| Capability | Status | Phase 2 treatment |
| --- | --- | --- |
| Catalogue candidate retrieval | Partial | Normalize through one internal inventory interface. |
| Price truth | Partial | Retrieve by typed booking unit and validate immediately before hand-off. |
| Date-aware availability | Partial for rooms/tickets/seats | Capability-based validation; omit unsupported categories instead of assuming availability. |
| Location intelligence | Partial static map/points | Add canonical geocoding/routing only after provider/consent design. |
| Deterministic recommendations | Basic | Extend score inputs after data profiling. |
| LLM integration | Direct, unstructured Gemini chat | Replace with centralized gateway and schema-constrained plan narration. |
| User personalization | Sparse implicit signals | Start opt-in and explainable; no trained model needed. |
| Trip-plan state | Snapshot persistence only | Add versioned plan/session state. |

### LLM data boundary

The LLM may receive only the minimum planning context: user-requested constraints, opaque candidate IDs, display names/descriptions, validated price/availability summaries, vendor-display data needed for explanation, and non-sensitive location/geography. It must not receive passwords, auth/session/CSRF tokens, payment data, full user profiles/contact details, raw audit logs, device data, precise current location unless explicitly necessary and consented, or complete booking histories. Candidate IDs and all booking actions must remain server-controlled.

## 14 Security and privacy assessment

### Existing controls observed

- PDO prepared statements and error suppression/fallback when the database cannot connect.
- Password hashing, sessions, CSRF checks in the central state-changing router, role/approval helpers, OAuth, 2FA/device-security schema, audit logging, and payment callback code.
- Environment templates keep secrets out of version control; cPanel deployment excludes `.env`.

### Findings requiring remediation before TIE exposure

| Finding | Impact | Recommended Phase 2 control |
| --- | --- | --- |
| AI chat is public and accepts client-controlled history. | Prompt injection, unbounded context/cost, unauthenticated abuse. | Gateway-side history ownership, input/output size limits, rate limits, abuse logging, and authenticated personalization. |
| Gemini prompt includes vendor phone and receives raw chat history. | Avoidable disclosure to external provider. | Data minimization/redaction policy and explicit provider-data assessment. |
| `api/ai/recommend.php` accepts `user_id` from request. | Potential leakage of another user's booking-derived ranking. | Derive identity only from session; authorize or make endpoint non-personalized. |
| Planner sessions bind a PHP session key and optional user ID without demonstrated plan authorization in print/read path. | Cross-session plan disclosure risk if identifiers are guessable or access checks incomplete. | Require owner/session authorization; use opaque high-entropy public share tokens only when intentionally shared. |
| Location-consent and retention model is absent. | Legal/trust risk once GPS is added. | Feature-specific consent, approximate vs precise distinction, purpose/retention/deletion policy, revocation UI, no silent tracking. |
| Multiple legacy/modern schema branches and permissive DB-null fallback. | Security/correctness failures can be concealed in unavailable environments. | Health checks, deployment schema gate, explicit degraded-mode messages, integration tests. |

Before connecting any external LLM or location provider, create a data-processing inventory, retention schedule, user-facing consent language, and redaction/test cases. Do not log full prompts, precise coordinates, or booking details by default.

## 15 Gap analysis

| Capability | Existing | Partial | Missing | TIE action |
| --- | :---: | :---: | :---: | --- |
| Marketplace/vendor discovery | ✓ |  |  | Reuse through canonical inventory projection. |
| Vendor approval filtering |  | ✓ |  | Centralize deployed approval rule in retrieval. |
| Typed prices |  | ✓ |  | Reuse booking sources; remove metadata-only assumptions. |
| Event/seat/room availability |  | ✓ |  | Capability-based validation and final booking revalidation. |
| Tour/route schedule availability |  | ✓ |  | Verify schema/live coverage, then extend. |
| Booking/payment workflow | ✓ |  |  | Reuse; no direct TIE writes. |
| Trip Planner UI | ✓ |  |  | Preserve entry point; replace planner internals incrementally. |
| Structured intent |  |  | ✓ | Add schema-backed intent extraction/clarification. |
| Budget calculation |  | ✓ |  | Deterministic itemized quote engine. |
| Recommendation ranking |  | ✓ |  | Extend with validity, distance, availability, price, rating, intent. |
| LLM gateway/structured output |  |  | ✓ | Add one provider abstraction, JSON schema and validator. |
| Maps display | ✓ |  |  | Reuse Leaflet/OpenStreetMap. |
| Geolocation/geocoding/routing/ETA |  |  | ✓ | Provider decision and consent-first integration. |
| Current/local journey tracking |  |  | ✓ | Defer until consent, realtime and vendor telemetry are designed. |
| User travel preferences |  |  | ✓ | Add explicit opt-in preference model. |
| Notification delivery/storage | ✓ |  |  | Extend later for opt-in trip alerts. |
| Observability/test automation |  | ✓ |  | Add logs/metrics/contract and integration tests. |

## 16 TIE integration points and data boundary

### Where TIE lives

Create a `php_app/includes/tie/` module namespace (or equivalent PHP module folder) within the existing application. The public planner endpoint becomes a thin controller. This is the smallest architecture consistent with the codebase and preserves the cPanel deployment model.

```text
trip-planner.php / ai chat UI
        │
        ▼
TIE controller (new endpoint or versioned planner action)
        │
        ├── Intent and plan-session service
        ├── Catalogue retrieval adapter (existing listings and typed inventory)
        ├── Availability and price validator (existing booking/availability facts)
        ├── Deterministic ranker and budget calculator
        ├── Optional location/routing adapter
        ├── LLM gateway (optional explanation only)
        └── Response validator and audit/event logger
                 │
                 ▼
          Existing listing detail and booking hand-off
```

### Read boundary

Initially read only active/approved vendor and service data, typed prices, supported availability, locations, ratings/reviews, explicit user preferences, the requesting user's own bookings/favourites/recent views, and saved trip-plan state. Every retrieval must state its freshness and source.

### Write boundary

Initially write only versioned trip plans, plan-item selections/reference IDs, plan-session state, user-approved preferences, consent state, and privacy-safe recommendation events. Do not write `bookings`, payment records, inventory quantities, vendor status, or canonical prices. Only existing booking APIs/controllers may mutate those records.

## 17 Proposed target architecture

```text
CURRENT
Browser → PHP pages / request_api.php → listings + optional typed inventory → MySQL
                          └──────── direct Gemini chat (optional)

INTEGRATION CHANGE
Trip Planner endpoint becomes a controller over deterministic retrieval,
validation and planning services; LLM is given only validated candidate context.

TARGET (smallest viable)
Browser
  → Planner/Chat controller
  → TIE Plan Service
       → Intent + clarification state
       → Inventory Projection (canonical deployed schema)
       → Availability/Price Validator
       → Budget + Ranking
       → Location adapter (only when enabled/consented)
       → LLM Gateway (structured explanation, optional)
       → Response validator
  → Existing listing detail / booking controller
  → MySQL and approved external providers
```

The target is modular PHP, not microservices. Interfaces should allow later extraction only if latency, scale, or ownership evidence justifies it.

## 18 Risks and mitigations

| Risk | Impact | Probability | Mitigation |
| --- | --- | --- | --- |
| Deployed schema differs from repository assumptions. | Critical correctness and booking failures. | High | Read-only schema/data profile and canonical contract gate before Phase 2. |
| Incomplete/stale price or availability data. | Incorrect recommendations or failed checkout. | High | Category capability matrix; final booking revalidation; omit unverified candidates. |
| Generic metadata and hard-coded fallbacks cause invented facts. | Trust damage and unsafe booking suggestions. | High | Typed projection, provenance per item, no invented vendor/price/availability. |
| LLM hallucination/prompt injection. | False itinerary facts, data leakage, cost abuse. | High | Retrieval-first context, structured output, schema/business validation, rate limits, minimal data. |
| Public AI endpoints abuse/latency/provider outages. | Cost, degraded UX. | Medium | Gateway timeout/retries/budget telemetry, anonymous limits, deterministic fallback. |
| GPS denied/inaccurate or privacy-sensitive. | Feature failure/trust risk. | High | Optional location; manual location works; consent/retention controls; coarse location default. |
| Price/availability changes after recommendation. | Booking failure or mismatch. | High | Quote as indicative, timestamped; revalidate at booking hand-off. |
| No queues/realtime infrastructure. | Cannot safely promise live journey features. | Medium | Defer live tracking; introduce async infrastructure only with an approved use case. |
| No executable test runtime in this checkout. | Regressions and unverified contracts. | High | Establish PHP/MySQL staging, fixtures, automated contract/integration tests before release. |

## 19 Phase 2 prerequisites

1. Obtain approved read-only staging/production access and execute the schema/data-quality profile described in section 06.5.
2. Name the deployed canonical inventory/booking schema and publish its versioned interface; resolve or quarantine legacy branches.
3. Create a category capability matrix: canonical price unit, availability model, date/schedule support, booking hand-off, location completeness, and vendor approval source.
4. Decide the mapping/routing provider and commercial/privacy constraints. Leaflet may remain the display layer.
5. Define location consent, retention, deletion, and user-visible fallback behaviour.
6. Define a provider-neutral LLM gateway policy: models, API-key storage, timeouts, rate limits, token/cost logging, data minimization, fallback and structured-output schema.
7. Define planner intent and response JSON contracts, including clarification, provenance, warnings, candidate IDs, quote freshness, and plan-version semantics.
8. Secure or replace public AI/recommendation endpoints and authorize plan/session retrieval.
9. Provision a PHP/MySQL staging environment with seeded realistic inventory and add automated tests for availability, price revalidation, authorization, and planner contracts.
10. Confirm payment lifecycle semantics and booking concurrency in the deployed schema before allowing any one-click/book-all experience.

## 20 Final recommendations

1. Treat the schema/source-of-truth decision as the first Phase 2 design task and completion gate.
2. Build the first TIE slice around **validated candidate retrieval and deterministic planning**, with an LLM only for controlled intent extraction and explanation.
3. Maintain backward compatibility with the current Trip Planner UI while introducing an explicit versioned plan API; do not silently change the existing response shape.
4. Do not add live tracking, custom ML training, microservices, or Google Maps merely because they are present in the long-term vision. None is required for a safe first intelligent planner.
5. Make every plan item attributable to either Uthenga inventory, a clearly labelled external enrichment source, or a non-bookable generic suggestion. Only Uthenga inventory may be represented as bookable.

## 21 Evidence scope and acceptance checklist

Completed from source inspection:

- [x] Repository map, application boundaries, deployment files, and tool gaps documented.
- [x] Frontend Trip Planner, discovery, detail, booking, map, profile, vendor and admin paths inspected.
- [x] Relevant API endpoints and central action router catalogued.
- [x] Runtime unified schema, rich schema, compatibility migrations, data relationships, and availability paths mapped.
- [x] Booking transaction path and final-validation authority traced.
- [x] Existing AI, maps/location, authentication, integrations, caching, and infrastructure mapped.
- [x] AI/privacy risks, gap analysis, TIE boundary, target integration, risks, and Phase 2 gates documented.

Pending live-environment evidence (must be completed before implementation):

- [ ] Actual deployed table/column/schema inventory.
- [ ] Data completeness/duplication/invalid-coordinate/price/availability measurements.
- [ ] Runtime authentication/API authorization and payment-callback verification.
- [ ] PHP/MySQL execution, integration tests, and representative booking concurrency tests.
- [ ] Provider contracts/costs and privacy/legal approval for LLM and location processing.
