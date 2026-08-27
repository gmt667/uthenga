# Uthenga TIE — Program 3 operational foundation

## Delivered

### Journey engine

The Journey API now derives read-only journey cards from confirmed, paid
marketplace bookings. Each card exposes upcoming/current/completed state,
booking provenance, timeline entries, and an explicit statement that live
tracking is unavailable unless a vendor position provider is added.

Endpoints:

```text
GET /api/tie/journeys/current.php
GET /api/tie/journeys/view.php?journey_id=JRN-<booking-id>
```

Both are authenticated, feature-gated, and owner-scoped.

### Notifications

`tie_notification_outbox` provides durable, idempotent notification records.
The application enqueues messages; it does not send email, SMS, push messages,
or background jobs until a channel provider and worker are configured. This
avoids silently claiming delivery from a web request.

### Operations

The public TIE health endpoint now exposes only safe feature/dependency state:
database availability, feature flags, configured provider names, and LLM
health. It never exposes keys, payment references, customer details, raw
prompts, or precise locations. Journey/outbox metrics are added to the existing
safe error-log metrics seam.

## XAMPP migration

Migration `020_tie_journey_notifications.sql` was applied and the durable
notification outbox table was verified.

## Maps and routing

Routing remains provider-neutral and disabled. No map or routing provider was
selected or called because no server credential, public usage policy, or route
privacy/retention policy has been configured. The marketplace continues to
operate normally without routing.

Before enabling routing, select a provider, store its server key outside source
control, define a per-user consent boundary, use only fresh high-confidence
origin/destination context, and define cost/rate-limit/cache policies.

## Remaining Program 3 gates

1. Add an authenticated notification worker plus email/SMS/push providers,
   retry policy, customer channel preferences, and delivery receipts.
2. Add vendor schedule/delay/cancellation ingestion so journeys can report
   operational changes rather than only booking dates.
3. Add a route provider adapter, map UI, consent screen, and route-cache policy.
4. Add a central metrics/logging backend, alert rules, backup/restore checks,
   release/rollback procedure, and an operator support dashboard.
