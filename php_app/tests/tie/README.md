# TIE tests

The foundation deliberately uses a dependency-free PHP contract test so it can run in XAMPP without Composer or PHPUnit.

```text
/opt/lampp/bin/php php_app/tests/tie/ContractTest.php
/opt/lampp/bin/php php_app/tests/tie/QueryTest.php
/opt/lampp/bin/php php_app/tests/tie/AvailabilityTest.php
/opt/lampp/bin/php php_app/tests/tie/LocationTest.php
/opt/lampp/bin/php php_app/tests/tie/ContextTest.php
/opt/lampp/bin/php php_app/tests/tie/PermissionTest.php
/opt/lampp/bin/php php_app/tests/tie/LocationDtoTest.php
/opt/lampp/bin/php php_app/tests/tie/LocationQualityTest.php
/opt/lampp/bin/php php_app/tests/tie/LocationConfidenceGeographicTest.php
/opt/lampp/bin/php php_app/tests/tie/VendorCoordinateGovernanceTest.php
/opt/lampp/bin/php php_app/tests/tie/GeospatialHardeningTest.php
/opt/lampp/bin/php php_app/tests/tie/NearbySearchContractTest.php
/opt/lampp/bin/php php_app/tests/tie/LocationPrivacyContextApiTest.php
/opt/lampp/bin/php php_app/tests/tie/LocationOperationalHardeningTest.php
/opt/lampp/bin/php php_app/tests/tie/RecommendationEngineTest.php
/opt/lampp/bin/php php_app/tests/tie/AiConversationTest.php
/opt/lampp/bin/php php_app/tests/tie/PlanningTest.php
/opt/lampp/bin/php php_app/tests/tie/BookingOrchestrationTest.php
```

`ContractTest.php` checks TripRequest validation, location validation, trusted user identity, and that the Phase 2 planner returns only an empty draft.

`QueryTest.php` is an integration test. It requires the configured database and
the seeded `listings` baseline. It performs read-only catalogue checks only.

`AvailabilityTest.php` is also read-only. It verifies that the Phase 4 rules
engine treats only the existing event booking fallback as a validated runtime
source and fails closed for categories without authoritative inventory.

`LocationTest.php` is read-only. It verifies consent-marked location context,
quality/freshness checks, provider failure fallback, geographic distance, and
the safe no-coordinate result from the current catalogue.

`ContextTest.php` is read-only. It verifies versioned, privacy-minimized
TravelContext aggregation across user, booking, trip, query, availability, and
optional location components.

`PermissionTest.php` verifies platform permission normalization, session-only
lifecycle transitions, consent/provenance metadata, and expired device-location
handling. It does not persist any location data.

`LocationDtoTest.php` verifies the `location-context/v1` serialization,
optional-field omission, precision normalization, structured coordinate errors,
timestamp validation, and source validation.

`LocationQualityTest.php` verifies configurable accuracy and freshness classes,
operation profiles, boundary transitions, invalid accuracy handling, and DTO
integration without acquiring or persisting any location.

`LocationConfidenceGeographicTest.php` verifies deterministic confidence,
confidence operation policy, reverse-geocoding fallback, versioned hierarchy
normalization, nullable partial resolution, and provider-failure degradation.

`VendorCoordinateGovernanceTest.php` verifies that pending vendor coordinates
are excluded from radius search, verified coordinates become searchable, and
review actions write an audit entry without leaving test data behind.

`GeospatialHardeningTest.php` verifies the vendor location quality states,
stale-coordinate exclusion, deterministic nearby-result tie-breaks, controlled
import authorization, and the availability of the verified-coordinate index in
the radius query plan. It rolls back all database writes.

`NearbySearchContractTest.php` verifies the versioned public nearby request and
response contracts, geographic distance semantics, deployed category registry,
Phase 3 filter reuse, and Phase 4 result-validation exposure.

`LocationPrivacyContextApiTest.php` verifies the privacy-minimized public
Location Context response, permission-denied fallback, strict request fields,
and the absence of coordinates and telemetry from the public contract.

`LocationOperationalHardeningTest.php` verifies server-authoritative nearby
inputs, provider architecture descriptors, geographic-search failure fallback,
and session rate-limit enforcement.

`RecommendationEngineTest.php` verifies deterministic weighting, exclusions,
ordering, explanations, request restrictions, and TravelContext-based ranking
without an LLM or marketplace mutation.

`AiConversationTest.php` verifies the bounded conversation contract, prompt
minimization, provider-neutral mock path, invalid-provider fallback, canonical
recommendation references, and safeguards against booking claims. It makes no
network calls and does not write marketplace data.

`PlanningTest.php` verifies deterministic itinerary composition, chronological
ordering, conflict detection, lifecycle rules, server-derived plan inputs, and
transaction-rolled-back draft persistence/revalidation.

`BookingOrchestrationTest.php` verifies booking state transitions, strict
idempotency, payment-policy fail-closed behaviour, independently tracked
operations, and provider-bound execution. It uses a fake provider and rolls
back its execution records; it never creates a marketplace booking.
