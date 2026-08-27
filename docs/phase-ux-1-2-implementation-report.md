# UX-1 and UX-2 implementation report

## Outcome

The Trip Planner now has a progressive TIE experience layered onto the existing PHP marketplace. It asks for destination, trip timing and travellers, then budget and preferences, while maintaining a visible trip summary throughout the flow.

The page now sends signed-in trip requests to the existing authenticated Phase 8 conversation endpoint. That endpoint orchestrates validated context and recommendations first, then lets the configured LLM explain the resulting evidence. Each displayed result links to the existing listing-detail route and does not reserve inventory, create a plan, initiate a payment, or execute booking.

## Delivered

- A scoped visual foundation in `php_app/assets/css/tie-experience.css`.
- Per-page stylesheet support in the shared header, so the new experience does not alter legacy marketplace pages.
- A responsive, keyboard-operable progressive planner in `php_app/trip-planner.php`.
- Live, client-side trip summary for destination, dates, traveller count, budget, and preferences.
- TIE conversational-planning integration using JSON, session authentication, and the existing CSRF header contract.
- A bounded “Ask Uthenga” follow-up area backed by the existing session-only conversation memory.
- Explainable marketplace cards showing category, location, price, eligibility state, and deterministic reasons.
- A clear sign-in requirement for AI planning; an unavailable AI request shows an error rather than silently presenting a legacy result as intelligence.

## Guardrails retained

- LLM calls receive only sanitized, server-orchestrated TravelContext and recommendation evidence through the existing Phase 8 gateway.
- No raw coordinates, prompts, diagnostics, or recommendation scores are shown in the UI.
- No external fallback inventory is injected. The old Unsplash-backed suggestion list was removed.
- Listing prices and availability remain authoritative only on the underlying Uthenga listing and booking paths.
- Existing booking remains separate; TIE booking orchestration is still feature-gated.

## Validation to perform in XAMPP

1. Open `http://127.0.0.1/uthenga/trip-planner.php` as a guest. Confirm the page clearly asks the user to sign in rather than presenting legacy results as AI output.
2. Sign in, complete the same flow, and confirm `POST /api/tie/conversation/chat.php` returns grounded conversational guidance and TIE cards with explanation reasons.
3. Change the destination, dates, budget, and traveller choice and confirm the summary updates without a page reload.
4. Select a card and confirm it opens the existing listing-detail page. Confirm no booking is created by either planning path.
5. Temporarily disable the AI feature in local configuration and confirm the page displays a clear unavailable-state error without falling back to legacy results.

## Deliberately deferred

This slice implements UX-1 and UX-2 only. Explainable comparison controls, persisted-plan timeline editing, authenticated conversational planning, nearby consent UX, maps, and live journey mode remain the next UX workstreams.
