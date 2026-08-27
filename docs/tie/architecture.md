# TIE architecture

TIE is an in-process PHP module under `php_app/includes/tie/`, selected because Uthenga is a single PHP/MySQL application deployed through cPanel. It is not a microservice.

```text
TIE API → orchestration modules → provider interfaces → providers
              ↓
      existing Uthenga data/booking boundaries
```

TIE cannot write bookings, payments, inventory, vendor status, or canonical prices. It returns typed, validated responses and hands users to the existing booking flow. The only Phase 2 endpoints are `api/tie/health.php`, `api/tie/context.php`, and `api/tie/trips.php`; all advanced capabilities are deliberately inert.
