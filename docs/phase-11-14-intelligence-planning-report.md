# Uthenga TIE — Phases 11–14 implementation report

## Outcome

TIE now produces deterministic trip-cost evidence and actionable plan-conflict
evidence before the conversational layer explains a trip. The work is additive:
it does not create reservations, charge customers, or let an LLM alter
marketplace facts.

## Implemented

### Phase 11 — Intelligent planning evidence

The existing Trip Planning Engine now persists `budget_analysis` and
`conflict_analysis` on every draft and refreshes both during plan validation.
The plan explanation includes the budget status and estimated total as
server-calculated facts.

### Phase 12 — Budget intelligence

`UthengaTieBudgetService` calculates a versioned budget from normalized
marketplace prices. It separates transport, accommodation, activities, meals,
taxes, and contingency; computes all arithmetic on the server; and reports
`WITHIN_BUDGET`, `OVER_BUDGET`, or `BUDGET_NOT_PROVIDED`.

Published listing prices are the only marketplace price input. Meals, taxes,
and contingency are included only when an explicit policy value is configured.
Until those values are set, the response warns that they are not included.

### Phase 13 — Smart conflict detection

`UthengaTieConflictService` returns deterministic, resolved issues for:

- duplicate services;
- invalid or overlapping time ranges;
- insufficient connection time between listed locations;
- activities outside trip dates;
- excessive daily activity count;
- missing accommodation on a multi-day trip;
- incomplete price estimates; and
- a budget shortfall.

Each issue has a stable code, severity, explanatory message, and user-facing
resolution. Blocking issues prevent a plan from reaching approval.

### Phase 14 — AI reasoning over trusted evidence

The server-side AI tool orchestrator now builds a sanitized budget evidence
object from ranked candidates and supplies it with Travel Context and
Recommendation evidence. The prompt explicitly says that budget values are
deterministic estimates, not payment quotes. The LLM remains an untrusted
explainer: it cannot create bookings, change prices, calculate a different
total, or assert payment/booking status.

## User experience

The Trip Planner now displays:

- the current option-set budget estimate and remaining/over-budget amount in
  the assistant area; and
- a plan analysis area with component totals, budget status, detected conflicts,
  and actionable resolutions.

The UI renders API output only. It does not calculate totals or conflicts.

## Safety changes

The local development configuration disables the legacy immediate-capture
booking path. The pre-existing booking module has no payment authorization,
capture, webhook verification, or reconciliation boundary, so it must remain
off until the payment programme is completed.

## Verification

- PHP lint passed for the modified TIE modules, planner page, and test files.
- `php_app/tests/tie/*Test.php` passed, including the new deterministic
  intelligence planning test and the existing AI, planning, booking,
  recommendation, availability, context, and location suites.
- `trip-planner.php` and `tie-explore.php` returned HTTP 200 from the local
  XAMPP instance.

## Configuration required before a real cost estimate is complete

Set these non-secret business-policy values only after product/finance approval:

```text
TIE_BUDGET_MEAL_ALLOWANCE_PER_TRAVELLER_DAY
TIE_BUDGET_TAX_RATE_PERCENT
TIE_BUDGET_CONTINGENCY_RATE_PERCENT
TIE_PLAN_MIN_CONNECTION_MINUTES
```

No default allowance or tax is fabricated when these are absent.

## Remaining delivery gates

The following are deliberately not complete and should not be represented as
live capabilities:

1. Payment-safe booking: inventory holds, a payment state machine, provider
   webhooks, signature verification, reconciliation, refunds, and audit views.
2. Journey operations: real booking-derived journey state, supplier changes,
   cancellations, delays, and customer-visible progress.
3. Maps and routing: a selected provider, server-side route adapter, consented
   route context, ETA handling, and cost/rate-limit policy.
4. Notifications: durable outbox/queue, delivery providers, user preferences,
   retries, and delivery audit records.
5. Production operations: structured central logs, metrics backend, alerting,
   secret rotation, backups, health checks, and deployment/rollback procedure.

These gates are the Phase 15–20 programme. They require their own schema,
provider, security, and operational design; none should be bypassed by enabling
the legacy booking switch.
