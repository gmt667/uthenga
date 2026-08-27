# Uthenga TIE — Phases 15–16 payment foundation report

## Outcome

TIE now has a separate, feature-gated payment-intent foundation for a safe
PayChangu Standard Checkout integration. It is intentionally **not live**:
the marketplace does not yet expose an atomic inventory hold plus post-payment
booking-commit provider, so accepting money would be unsafe.

## What was implemented

- `tie_payment_intents`: user-scoped idempotency, immutable quote snapshot,
  amount/currency, generated provider reference, hold reference, checkout URL,
  verification result, expiry, and safe diagnostics.
- `tie_payment_events`: webhook replay protection using event and payload hashes;
  it never stores raw webhook bodies.
- payment state machine: quote, hold, checkout, pending, verified, booking,
  failed, cancelled, refund-required/refunded, and manual-review states.
- a provider-neutral payment gateway and inventory-hold interface.
- a PayChangu Standard Checkout adapter that creates server-side checkout links
  and verifies transactions server-to-server.
- a PayChangu webhook boundary that requires an HMAC-SHA256 `Signature` header,
  deduplicates events, and re-verifies the transaction before treating it as
  paid.
- authenticated payment-start and payment-status endpoints plus a separate
  public webhook endpoint.

## Safety invariants

1. A browser return URL is never proof of payment.
2. A webhook is not trusted until its HMAC validates and the transaction is
   re-queried from PayChangu.
3. The verified reference, currency, and amount must match the saved quote.
4. A payment cannot skip from checkout readiness to booked.
5. The API key and webhook secret are distinct configuration values.
6. Raw provider secrets, raw webhook payloads, and payment instruments are not
   stored in TIE tables or diagnostics.
7. No TIE endpoint writes legacy `bookings` or `transactions` tables.

## Inventory and XAMPP migration

Migration `018_tie_payment_intents.sql` was applied and both payment tables were
verified in the local XAMPP database. The existing inventory migration could
not create its resource tables because its foreign-key collation did not match
the legacy `listings` table. Migration `019_tie_inventory_holds.sql` supplies
compatible ticket, seat, room, and hold tables; it was also applied and
verified.

The new hold provider performs a database transaction, decrements the selected
resource atomically, restores it when released or expired, and consumes it only
when a verified payment is committed into canonical bookings and transactions.
The payment quote now comes from the selected authoritative resource price—not
from an AI estimate.

## Activation blockers

`TIE_PAYMENT_ENABLED` and `TIE_PAYMENT_COMMIT_ENABLED` remain false. The
database now has an atomic hold and a verified-payment booking commit provider,
but live activation still needs controlled test-mode checkout, a public HTTPS
webhook endpoint, seeded and maintained vendor inventory options, and an
operational refund process.

The older checkout/callback implementation is a separate legacy flow and is
not used by this module. It must be audited and migrated independently; TIE
does not enable or rely on it.

## Required configuration when the hold/commit provider exists

```text
TIE_PAYMENT_ENABLED=true
TIE_PAYMENT_COMMIT_ENABLED=true
TIE_PAYCHANGU_ENABLED=true
TIE_PAYCHANGU_SECRET_KEY=<server API secret>
TIE_PAYCHANGU_WEBHOOK_SECRET=<dashboard webhook secret>
TIE_PAYCHANGU_WEBHOOK_URL=https://<public-host>/api/tie/payments/paychangu-webhook.php
TIE_PAYCHANGU_RETURN_URL=https://<public-host>/payments/return
```

Do not use a public checkout key as a server credential, and do not reuse an
API secret as a webhook secret. The local XAMPP URL is not a valid public
webhook destination.

## Remaining Phase 15 completion work

1. Seed and maintain ticket, seat, and room inventory options for every
   payable listing; tours still need an authoritative inventory provider.
2. Add PayChangu refund orchestration for a failed booking commit after a
   verified payment.
3. Add a reconciliation worker that queries pending PayChangu references until
   final status and an operational review screen for manual-review states.
4. Configure the webhook in the PayChangu dashboard only after a publicly
   reachable HTTPS endpoint and test-mode verification are available.
