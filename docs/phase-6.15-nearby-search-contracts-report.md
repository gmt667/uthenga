# Uthenga TIE — Phase 6.15 Nearby Search Contracts & Integration Standardization

## Outcome

The Location Intelligence subsystem now exposes a stable public geographic
discovery boundary: `nearby-search-request/v1` and
`nearby-search-response/v1`.

## What was standardized

- Geographic distance is explicitly `GEOGRAPHIC`, in kilometres, and is
  described as straight-line distance only.
- The deployed marketplace category registry is formalized without duplicating
  the Phase 3 query-category model.
- Nearby inputs reject unsupported fields before Query Engine retrieval.
- Every response contains normalized candidate, distance, listing-location
  metadata, Phase 4 validation, provenance, diagnostics, and ordering metadata.
- The Location → Query → Availability flow is the sole supported integration
  path for downstream engines.

## Explicit exclusions

This phase adds no routing provider, road distance, ETA, recommendation score,
popularity ranking, personalization, LLM reasoning, or marketplace schema
access outside the Query Engine.
