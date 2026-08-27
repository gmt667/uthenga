# AI conversation layer

Phase 8 adds conversational planning without moving marketplace authority into
an LLM. The request path is:

```text
authenticated request -> TravelContext -> availability -> recommendation
-> sanitized evidence -> provider gateway -> response validator -> response
```

`POST /api/tie/conversation/chat.php` accepts a bounded user message and the
same travel criteria used by recommendations. It rejects client-supplied
recommendations, history, bookings, prices, and other marketplace facts.

## Provider boundary

`UthengaTieLlmProviderFactory` is the single provider selection point. `mock`
is a deterministic local/test provider. The OpenAI adapter is selected only
when `TIE_AI_PROVIDER=openai`, a model, and a local `TIE_OPENAI_API_KEY` are
all configured; it uses the Responses API with strict JSON schema output and
`store: false`. Unconfigured providers and other named providers fail closed.
Consumers use only `UthengaTieLlmGateway` and never a provider SDK or raw
provider response.

Groq is also supported through its OpenAI-compatible Chat Completions API. The
initial default model is `openai/gpt-oss-20b`: a production model that supports
strict structured output and is a good latency/cost fit for evidence-grounded
travel guidance. Configure `TIE_AI_PROVIDER=groq` and a local
`TIE_GROQ_API_KEY`; never place the key in source control.

## Privacy and safety

Prompts receive a minimized context: travel criteria, coarse geographic labels
when available, active-booking count, and canonical ranked recommendation
evidence. They never receive database rows, raw coordinates, user identifiers,
payment data, or conversation state from another user. Common email and phone
patterns are redacted from user message text before provider invocation.

Conversation memory is session-only, keyed by a hash of the authenticated user
and conversation ID, bounded by `TIE_AI_MAX_HISTORY`, and expired by
`TIE_AI_MEMORY_SECONDS`. It is not persisted as a profile.

Provider output is untrusted. It may reference only recommendation IDs in the
current evidence set. Canonical recommendation details come from TIE, not the
provider. Unsupported IDs, unsupported actions, malformed output, and booking
claims trigger the deterministic fallback response.

## Enablement

Keep all flags off by default. For a local mock exercise, enable the existing
TIE dependency flags plus:

```text
TIE_LLM_ENABLED=true
TIE_AI_ENABLED=true
TIE_AI_PROVIDER=mock
TIE_RECOMMENDATIONS_ENABLED=true
TIE_CONTEXT_ENABLED=true
TIE_QUERY_ENABLED=true
TIE_AVAILABILITY_ENABLED=true
```

Location remains optional and still requires its own flag and consent-marked
input. The conversation endpoint cannot book, reserve inventory, or change
marketplace records.
