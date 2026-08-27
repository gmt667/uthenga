# Uthenga TIE — Phase 8 AI Intelligence Layer & Conversational Planning

## Outcome

Phase 8 adds a feature-gated, provider-neutral conversational layer on top of
the deterministic TIE pipeline. It is additive: Query, Availability, Location,
Context, Recommendation, and booking behaviour are unchanged.

## Implemented boundary

- `Ai.php` contains versioned conversation contracts, a bounded session memory,
  fixed server-side tool orchestration, prompt construction, provider factory,
  mock provider, response validation, and fallback logic.
- `conversation/chat.php` requires authentication, CSRF, rate limits, and AI,
  LLM, Recommendation, Context, Query, and Availability flags.
- The tool orchestrator builds `TravelContext` and ranked recommendations before
  the provider is called. It passes only privacy-minimized evidence onward.
- Provider responses can select canonical recommendation IDs but cannot define
  listing information or modify marketplace state.

## Safe initial provider posture

The default provider is unavailable. `mock` is a zero-network deterministic
provider for local validation. The OpenAI Responses adapter is available only
when its provider name, local credential, and model are configured; it uses
structured JSON output and `store: false`. Other named providers retain the
same provider-neutral interface and fail closed until adapters are added. This
avoids accidental external data sharing.

Groq is supported through a separate, OpenAI-compatible Chat Completions
adapter. It defaults to `openai/gpt-oss-20b`, a production model with strict
structured output support. Groq provider tools are intentionally not enabled:
all TIE tool orchestration remains server controlled.

## Response contract

`ai-conversation-response/v1` contains `message`, canonical
`recommendations`, `suggested_actions`, `follow_up_questions`, `confidence`,
safe diagnostics, and provenance. Diagnostics do not contain prompts,
coordinates, user IDs, credentials, or provider payloads.

## Tests

`AiConversationTest.php` covers strict request inputs, prompt minimization,
mock-provider validation, hallucinated recommendation rejection, booking-claim
rejection, deterministic fallback, provider boundary, session isolation, and
the real deterministic pipeline when the configured XAMPP database is present.

## Phase gate

The system now supports conversational explanations over deterministic,
validated evidence. It cannot use an LLM to bypass marketplace rules, invent
marketplace facts, or create bookings. A real provider adapter remains a
separate credential and provider-governance decision.
