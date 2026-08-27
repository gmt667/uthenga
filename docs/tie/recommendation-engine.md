# TIE deterministic recommendation engine

## Boundary

Phase 7 ranks only the eligible candidate set already produced by
`TravelContext`:

```text
TravelContext -> Query Engine -> Availability Engine -> Recommendation Engine
```

Recommendation code has no marketplace repository and does not query
`listings`. It cannot book, reserve inventory, mutate a vendor record, invoke
an LLM, or override Phase 4 validation.

## API

`POST /api/tie/recommendations.php` is authenticated, CSRF-protected,
feature-gated, and rate-limited by `TIE_RECOMMENDATION_RATE_LIMIT`.

The request accepts travel intent—destination, dates, travellers, budget,
preferences, travel mode, optional validated location, optional nearby radius,
category, and limit. Candidate sets, scores, distance, availability, rating,
eligibility, and vendor facts are server-derived and rejected if supplied by a
client.

The response is `recommendation-result/v1` and includes recommendations,
structured explanations, rule contributions, exclusions, policy metadata, and
server-authoritative provenance. It does not include a prompt or LLM output.

## Policy and scoring

All weights are configured through `TIE_RECOMMENDATION_*` settings. Defaults:

| Signal | Default weight |
| --- | ---: |
| Distance | 20 |
| Availability | 20 |
| Price | 15 |
| Category | 15 |
| Date compatibility | 10 |
| Vendor eligibility | 10 |
| Context/geographic match | 10 |

Scores are normalized to 0–100. Geographic distance is straight-line only;
routing, ETA, and road distance remain outside Phase 7. If a signal is not
available (for example, no location or no price), the engine uses a documented
neutral contribution rather than inventing a fact.

Candidates are excluded for failed business rules, ineligible vendors,
inactive services, wrong category, duplicate active booking, or known
over-budget price. Ordering is score descending, geographic distance ascending
on a score tie, declared available units descending on a distance tie, then
service ID.

## Explainability and privacy

Each recommendation includes fixed deterministic reasons such as availability,
approved vendor, geographic distance, price compatibility, category match, and
date compatibility. Diagnostics record only scores, contributions, rule codes,
policy version, and aggregate counts. They contain no raw TravelContext,
coordinates, PII, or prompts.
