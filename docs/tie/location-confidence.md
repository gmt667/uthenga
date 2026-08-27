# TIE location confidence policy

## Confidence contract

Phase 6.7 derives one deterministic confidence classification from already
validated metadata. It never reads raw coordinates or performs inference.

| Level | Meaning |
| --- | --- |
| `HIGH` | Fresh, high-quality, validated location from an approved source. |
| `MEDIUM` | Suitable for planning and nearby discovery. |
| `LOW` | Suitable only for coarse reasoning. |
| `UNKNOWN` | Metadata is incomplete or cannot support a policy decision. |

The canonical `location-context/v1` exposes the classification as `confidence`.
`metadata.confidence` contains the policy version and non-sensitive evaluated
metadata. It does not expose raw coordinates or device identifiers.

## Configurable policy

The confidence policy is controlled by `TIE_LOCATION_CONFIDENCE_*` settings:

- policy version;
- accepted sources for `HIGH` and `MEDIUM`;
- accuracy/freshness combinations for `HIGH` and `MEDIUM`;
- incomplete-metadata result;
- minimum confidence per named operation.

Defaults permit `HIGH` only for fresh browser/device locations with excellent
or good accuracy. They permit `MEDIUM` for validated fresh/aging observations
with excellent, good, or moderate accuracy from configured sources.

`metadata.operation_profiles[operation].eligible` combines the accuracy,
freshness, and confidence policy. Downstream modules use that final decision.
