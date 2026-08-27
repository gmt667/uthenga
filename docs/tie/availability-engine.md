# TIE Phase 4 availability and business rules

## Source-of-truth audit

The local XAMPP profile was audited against the actual booking code, not only
the schema files. The availability sources are deliberately not treated as
interchangeable.

| Category | Published data | Current booking path | Phase 4 result |
| --- | --- | --- | --- |
| Event | `listings.meta.standardAvailable` / `vipAvailable` | Existing `create_booking` checks and decrements the selected JSON field when `ticket_types` is absent. | Validated against the current legacy runtime source; final booking revalidation is mandatory. |
| Transport | `listings.meta.availableSeats` | No legacy fallback decrements/checks seats when `seat_classes` is absent. | `UNKNOWN`, blocking. |
| Accommodation | `listings.meta.rooms[].availableRooms` | No date-based room inventory or fallback booking decrement when `room_types` is absent. | `UNKNOWN`, blocking. |
| Tour | `meta` dates and maximum group size | No remaining-capacity source or booking decrement. | `UNKNOWN`, blocking. |

`ticket_types`, `seat_classes`, `room_types`, `room_availability`, and the
normalized `vendors` table are absent from the deployed database. The intended
inventory migration could not create several FK-backed tables because the base
schema uses `utf8mb4_general_ci` while later migrations use
`utf8mb4_unicode_ci`. Phase 4 does not invent a replacement inventory schema.

## Rules pipeline

`Availability.php` implements a separate deterministic layer:

```text
Candidate → vendor rules → service lifecycle → dates/routes → availability → capacity → result
```

Rules return structured `passed`, `rule_code`, `severity`, and `message`
objects. Any blocking rule makes `eligible` false. Important codes include
`VENDOR_NOT_APPROVED`, `VENDOR_INACTIVE`, `SERVICE_INACTIVE`,
`SERVICE_EXPIRED`, `EVENT_DATE_MISMATCH`, `SCHEDULE_MISMATCH`,
`ROUTE_MISMATCH`, `CAPACITY_EXCEEDED`, `AVAILABILITY_UNKNOWN`, and
`AVAILABILITY_STALE`.

The engine uses the PHP application's actual runtime timezone, currently
`Europe/Berlin`. This must be deliberately changed to the product's agreed
timezone before production launch; the engine does not silently substitute a
timezone based on Malawi-related listing text.

## Availability trust states

- `available` / `limited` with `validation_status: validated`: only current
  event JSON inventory checked through the legacy booking path.
- `unavailable`: a validated event option cannot satisfy the requested
  quantity.
- `unknown`: no deployed authoritative source exists. This is blocking.
- `stale`: an approved configured freshness policy has elapsed. This is
  blocking.

No freshness timeout is guessed. `TIE_AVAILABILITY_MAX_AGE_SECONDS=0` means
no freshness policy has been approved. A positive configured value enables
stale detection from the source record's `updated_at` timestamp.

## API and security

`POST /api/tie/availability/validate.php` is an authenticated, CSRF-protected
diagnostic API. It accepts only `service_id`, quantity, dates, route context,
and an inventory-option choice. It never accepts price, capacity, vendor
status, or availability from the client. It performs no writes, reservations,
or bookings.

The endpoint remains disabled unless both `TIE_ENABLED=true` and
`TIE_AVAILABILITY_ENABLED=true` are explicitly configured.

## Booking boundary

Every validation response includes `revalidation_required: true`. Phase 4 does
not alter `request_api.php`; the current booking flow remains authoritative.
Before any future TIE-assisted booking action reaches it, the selected
inventory must be checked again inside the booking transaction. Search-time
validation is not booking authorization.

## Performance and batching

The validation engine evaluates an already-normalized candidate in memory.
`validateCandidates()` accepts a Phase 3 batch and performs no per-candidate
database query, preventing an N+1 pattern. The diagnostic service loads one
fresh candidate by its ID only.
