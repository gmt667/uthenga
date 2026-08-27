# Uthenga TIE UX Master Plan

## 1. Purpose and product stance

TIE changes Uthenga from a browse-first marketplace into a travel companion
that helps a customer express intent, understand suitable marketplace options,
form a plan, and deliberately hand off to booking.

The experience must make the intelligence visible without implying that AI can
invent availability, confirm a booking, or track location without permission.
The interface is therefore built around three clear promises:

1. **Tell us what you need.** Natural language and progressive inputs are both
   valid ways to begin.
2. **See why an option fits.** Every service card presents deterministic facts
   and recommendation reasons.
3. **Stay in control.** A plan is a draft until the customer explicitly
   approves it; booking remains a separate, clearly labelled step.

This is a product-experience blueprint. It does not change live page behaviour
or enable booking execution.

## 2. Current experience and design opportunity

The deployed application is a server-rendered PHP marketplace. Its public
entry points include [home](/home/axontesla/uthenga/php_app/index.php),
[Trip Planner](/home/axontesla/uthenga/php_app/trip-planner.php), category
pages (`events.php`, `hotels.php`, `tours.php`, `transport.php`), details,
bookings, and dashboard pages. The primary navigation already exposes Trip
Planner, while search-led discovery remains the home-page default.

TIE now exposes trusted contracts for context, recommendations, conversation,
plans, location, and controlled booking orchestration. The UI should add a
cohesive layer over those contracts rather than build a second listing or
checkout system.

## 3. Experience principles

| Principle | UX consequence |
| --- | --- |
| Intent before filters | Start with a plain-language prompt and progressive questions; reveal filters only when useful. |
| Facts before prose | Price, availability, distance, and booking state always come from structured TIE responses. |
| Explainable by default | A “Why this?” disclosure shows deterministic recommendation reasons. |
| Consent is contextual | Ask for current location only after the customer selects a nearby/current-location task. |
| Progressive commitment | Explore → draft plan → validate → approve → booking review. Never conflate these steps. |
| Graceful degradation | If AI, location, or maps are unavailable, retain browse, search, and ordinary marketplace booking paths. |
| Mobile first | Plan creation, card comparison, and plan review work comfortably at 320 px width before desktop enhancements. |

## 4. Target information architecture

```text
Home
├── Ask Uthenga (assistant)
├── Explore
│   ├── Events
│   ├── Stays
│   ├── Tours and activities
│   └── Transport
├── Plan a trip
├── Nearby
├── My trips
│   ├── Draft plans
│   ├── Approved plans
│   └── Booking status
├── Saved
├── Notifications
└── Profile and preferences
```

The first release should retain existing category/detail URLs and add the
intelligence surfaces as progressive enhancements. Deep links from TIE cards
must lead to existing listing detail and booking pages until a dedicated,
reviewed booking-review screen is introduced.

## 5. Core journey flows

### A. “Plan a trip” — progressive planning

```text
Destination → dates → travellers → budget → optional preferences
    → ranked recommendations → draft itinerary → validate → approve
```

Only one primary question is shown at a time. A compact “Trip details” summary
remains visible and editable. The system asks no question whose answer can be
reliably inferred from previously supplied context.

Suggested prompts:

- “Where are you travelling?”
- “When would you like to go?”
- “Who’s travelling?”: Just me, Couple, Family, Group, or a numeric option.
- “What budget should we stay within?”
- “Anything important?”: quiet, family-friendly, no buses, accessible, etc.

Each step has **Skip for now**, **Back**, and a visible progress indicator.
Skipping a field is represented as unknown, not as an invented default.

### B. “Ask Uthenga” — conversational planning

The assistant opens with a focused question and four suggestion chips:

```text
How can I help with your trip?
[Plan a trip] [Find a stay] [Need transport] [What is nearby?]
```

Messages are sent to `conversation/chat.php` only after the customer has an
authenticated session and applicable TIE features are enabled. The interface
renders conversational text separately from structured recommendation cards.
It must never parse booking facts out of prose.

When the conversation lacks deterministic inputs, it asks the next progressive
question instead of guessing. The user can switch between conversation and the
structured planner without losing the visible trip summary.

### C. “Nearby” — permission-led discovery

```text
Tap Nearby → explain purpose → request permission → show results or fallback
```

Use purpose-specific copy: “Use my location to find places to stay nearby.” A
denied, unavailable, stale, or low-confidence result shows a non-blocking
manual destination/search alternative. Do not show a permission prompt on page
load, do not imply continuous tracking, and do not display coordinates.

### D. Plan review and approval

The plan screen is a chronological timeline grouped by day. Each item shows:

- service title/category and a deep link to its existing detail page;
- proposed time, clearly labelled when it is a planning estimate;
- availability/revalidation status;
- deterministic “why included” details;
- remove, reorder, or replace actions when the plan is editable.

The call to action changes by lifecycle:

| Lifecycle | Primary action | Copy |
| --- | --- | --- |
| Draft / Updated | Validate plan | “Check current availability” |
| Validated | Approve plan | “I’ve reviewed this plan” |
| Approved | Booking review | “Continue to booking” |
| Booking execution disabled | Explain state | “Booking is not available yet; use listing checkout” |
| Partially booked | Resolve | “Review services needing attention” |

Approval must not look like payment or booking confirmation.

## 6. Dashboard concept

The signed-in dashboard becomes a calm travel overview, not a listing grid.

```text
Good afternoon, [first name]
[Ask Uthenga]                                  [Plan a trip]

Your next trip / continue a draft
Recommended for your current plan
Upcoming bookings
Nearby ideas (only after location consent)
Explore categories
```

Initial dashboard modules should be ordered by available evidence. If there is
no draft, do not show an empty itinerary card; show the planning starter. If
location is not consented, replace nearby content with a clear “Explore by
destination” card.

Weather, live journey status, and dynamic route data remain future modules;
they must not be mocked as live information.

## 7. Recommendation and listing cards

Use one card system across Explore, conversation results, nearby results, and
plan replacement. Cards show only fields returned by the appropriate
structured contract.

```text
[image]
Category · Available / needs revalidation
Title
Location or approximate distance
Price and currency when known
[Why recommended] [View details] [Add/replace in plan]
```

`Why recommended` expands deterministic explanation codes such as availability,
budget compatibility, category match, approved vendor, and geographic distance.
It must not claim “best value,” “quiet,” “free breakfast,” or “near terminal”
unless a structured Uthenga field supports the claim.

Cards should handle incomplete data honestly: hide an unknown field rather than
rendering a placeholder fact. “Available” is time-bound and should include an
appropriate revalidation note before booking.

## 8. Visual and interaction system

- 16–20 px card radius, modest elevation, and strong focus outlines.
- One primary action per panel; destructive/remove actions remain secondary.
- Travel-category icons supplement text; no icon-only critical controls.
- 150–250 ms motion for transitions, respecting `prefers-reduced-motion`.
- Clear typography hierarchy: destination/plan title, next action, supporting
  facts, and provenance/status copy.
- Use status language and icons together: Available, Needs validation,
  Unavailable, Location unavailable, Plan needs review.
- Minimum AA contrast, 44 × 44 px tap targets, keyboard-visible focus, semantic
  headings, and screen-reader announcements for loading/errors.

## 9. Frontend-to-TIE contract map

| UI surface | TIE source | Rendering rule |
| --- | --- | --- |
| Planner summary | Context build + local form state | Show only submitted/validated values. |
| Recommendation cards | `recommendations.php` | Render canonical recommendation, explanation, score only for diagnostics/internal use. |
| Conversation | `conversation/chat.php` | Render text and structured cards separately; show graceful fallback normally. |
| Nearby | `location/permission.php`, `location/context.php`, `location/nearby.php` | Show permission and confidence fallbacks; never expose coordinates. |
| Draft timeline | `plans/create.php`, `plans/view.php`, `plans/update.php` | Treat proposed timing as a proposal unless schedule-backed. |
| Approval | `plans/validate.php`, `plans/approve.php` | Require explicit user confirmation. |
| Booking status | `bookings/status.php` | Use canonical execution states, not assumed confirmation. |

All client calls must send the current CSRF token, retain `request_id` for
support, handle feature-disabled responses, and avoid storing raw prompts,
location, or booking/payment data in browser analytics.

## 10. Delivery roadmap

### UX-1: Product shell and design system

Create a reusable TIE shell, navigation state, responsive layout tokens, card
components, empty/loading/error states, and accessibility baseline. Preserve
existing PHP routes and templates.

### UX-2: Planner entry and progressive form

Modernize `trip-planner.php` into the structured progressive flow. Connect
only Context and Recommendation initially; preserve an ordinary marketplace
search fallback.

### UX-3: Explainable recommendation results

Add structured cards, filters as optional refinements, compare/save affordances,
and “why recommended” panels. Do not expose internal diagnostics or raw scores.

### UX-4: Draft timeline and plan editing

Add plan creation, lifecycle status, remove/reorder/replace, validation, and
approval review. Clearly distinguish proposal, validation, approval, and
booking.

### UX-5: Conversation and nearby integration

Embed the assistant in the planner/dashboard, then add consent-led nearby
discovery. Measure comprehension and completion before adding maps.

### UX-6: Map and journey enhancements

After a chosen map-provider/product decision, visualize nearby cards and plan
locations. Live journey mode remains a later, separately consented programme.

## 11. Measurement plan

Measure outcomes, not only clicks:

- planner start-to-recommendation completion;
- recommendation explanation open rate and subsequent selection;
- plan validation and approval completion;
- abandonment by progressive-planning step;
- nearby permission grant/deny and fallback success;
- booking handoff completion, failures, and manual-resolution rate;
- latency/error states by feature flag.

Never send raw messages, exact location, payment references, booking details,
or hidden diagnostics to analytics.

## 12. UX acceptance criteria before UI implementation

- A customer can begin with either intent text or a structured first question.
- Every service fact visible in a TIE card maps to a structured field.
- The UI distinguishes recommendation, draft, validation, approval, and
  booking execution states in copy and action hierarchy.
- Location requests are explicit, purpose-bound, and recoverable.
- Keyboard, screen reader, reduced-motion, and 320 px mobile states are
  designed before implementation.
- Existing discovery, detail, and booking pages remain available as fallbacks.
- A feature-disabled or provider-unavailable response is understandable and
  does not block conventional marketplace use.

## 13. Decisions required before UX implementation

1. Which customer dashboard route becomes the intelligence home?
2. Should `trip-planner.php` be incrementally modernized or should a new
   `plan-trip.php` route be introduced first?
3. Which map provider should extend the current Leaflet/OpenStreetMap surfaces?
4. Which phases may write customer-facing analytics events, and where are they
   retained?
5. What payment-policy decision unlocks the Phase 10 booking-review UX?
