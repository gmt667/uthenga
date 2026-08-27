# TIE security and privacy

The public catalogue endpoints (`services`, `vendors`, and `categories`) use
the same public-marketplace scope as the existing browse experience. They are
feature-gated and return no customer, account, payment, or raw inventory JSON.
Other non-health TIE endpoints require an authenticated Uthenga session.
State-changing requests also require the existing CSRF token, either in form
data or the `X-CSRF-Token` header.

Availability diagnostics are also authenticated and CSRF-protected, despite
being read-only, because they reveal operational inventory state. The client
can request a quantity and an inventory option but cannot provide any asserted
availability, capacity, vendor state, or price.

Location is request-scoped by default. Exact coordinates are excluded from TIE
logs and are not written to a customer profile or history. Device locations
require an explicit normalized `GRANTED` permission state; browser, Android,
and iOS states are retained only in the active session. Permission logs contain
only state, platform, duration, and correlation ID, never coordinates or route
history. Reverse geocoding is disabled unless explicitly configured because it
sends coordinates to a third party. Vendor listing coordinates are separate
operational data and can be updated only by their owner or an administrator
through the protected endpoint.

All client-supplied coordinate observations are validated server-side before
they can enter `location-context/v1`. Validation failures return structured
field codes, but logs retain only generic outcome and correlation metadata.

TravelContext is purpose-limited: it excludes direct contact data, account balances, credentials, raw booking notes, payment references, full saved trip plans, and persistent location history. The context build endpoint is authenticated and CSRF-protected; context logs contain no raw context payload.

TIE uses only purpose-limited user context. Location is represented as sensitive context with an explicit source and is not acquired, persisted, or tracked in Phase 2. Future LLM calls may receive validated minimal candidate context only—not credentials, payment data, raw profiles, full booking history, or precise location without explicit consent.
