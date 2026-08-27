# TIE Phase 10.5: Experience Integration report

## Outcome

The authenticated Trip Planner is now the integrated TIE workspace. The browser sends intent and consented input to TIE APIs; it does not calculate availability, ranking, plan state, or booking state locally.

The customer dashboard now provides a clear entry point to the workspace.

## Connected customer-facing capabilities

| Experience | API contract | UI behaviour |
| --- | --- | --- |
| Catalogue discovery | `services.php`, `categories.php` | `tie-explore.php` sends filters to the normalized Query Engine and renders only active approved marketplace services. |
| Verified trip context | `context/build.php` | Builds and renders a minimized, validated context before the assistant responds. Coordinates are never displayed. |
| Conversational planning | `conversation/chat.php` | Sends structured trip input plus a user message; renders validated guidance and structured recommendation cards separately. Conversation ID survives a browser refresh for the current session. |
| Recommendation evidence | Conversation tool orchestration | Shows server-returned category, location, price, eligibility and deterministic reasons only. Raw scores and diagnostics remain hidden. |
| Availability | `availability/validate.php` | Each eligible card can request a fresh availability check against current trip details. |
| Location and consent | `location/permission.php`, `location/context.php` | Explicit button only; reports purpose, does not watch location, stores no location history, and offers destination fallback. |
| Nearby discovery | `location/nearby.php` | Uses the one-time validated observation to show eligible listings with straight-line distance labelled correctly. |
| Draft planning | `plans/create.php`, `plans/view.php` | Creates and restores a persisted draft plan, rendered as a chronological proposal. |
| Plan management | `plans/update.php`, `plans/validate.php`, `plans/approve.php`, `plans/export.php` | Supports removal, current availability validation, explicit approval, and JSON export. Approval is not booking. |
| Booking boundary | Feature flag and existing listing flow | Booking execution remains absent while `TIE_BOOKING_ENABLED=false`; UI directs users to existing listing checkout rather than implying a reservation. |

## Feature-aware rollout

The page receives the server-evaluated feature set at render time. Location controls appear only when location is enabled; draft-plan controls appear only when plans are enabled; conversation shows an understandable unavailable state when AI/LLM features are disabled. The ordinary marketplace detail and booking paths remain intact.

## Important non-capabilities

- There is no implemented public Journey API. `UthengaTieJourneyService::get()` currently returns `null`, so no live journey dashboard, tracking, ETA, weather, or map is exposed.
- A map-provider decision and route API are still required before displaying a map. Nearby distances are intentionally described as straight-line geographic distance, not travel time.
- There is no owner-scoped plan-list endpoint. The UI restores the customer’s active plan for the current browser session, but a full “My trips” list requires that backend contract.
- Booking orchestration is deliberately feature-disabled because the existing legacy route immediately captures payment. This is a payment-policy gate, not a UI omission.
- Vendor coordinate create/verify/import endpoints are operational/admin workflows and are not exposed on the customer Trip Planner.

## Validation performed

- XAMPP PHP lint passed for the modified PHP pages and shared header.
- The planner rendered successfully over local Apache (HTTP 200).
- Rendered planner JavaScript and the shared TIE client passed Node syntax checks.
- All 18 TIE backend regression tests passed.

## Manual acceptance flow

1. Sign in as a customer and open `trip-planner.php`.
2. Complete the progressive fields and select **Ask Uthenga to plan**. Confirm the progress stages, verified context, assistant response, and recommendation reasons appear.
3. Select **Use my location**, grant browser permission, then select **Find nearby**. Confirm no coordinate is shown and distances say straight-line.
4. Select **Check** on a result to request fresh availability.
5. Select **Create draft plan**, then **Check current availability**, approve only after review, and export the plan. Confirm no booking is created.
6. Refresh the page and confirm the active draft and in-session conversation remain available.
7. Temporarily disable an enabled TIE feature in local configuration and confirm that its control disappears or presents an understandable unavailable state.
