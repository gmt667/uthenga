# TIE vendor coordinate enrichment and verification workflow

## Lifecycle

```text
Vendor coordinate or address-derived coordinate
        -> deterministic validation and provenance capture
        -> pending_review
        -> administrator review
        -> verified or rejected
```

Vendor submissions use `POST /api/tie/location/vendor-coordinate.php`. The
vendor can submit `vendor_input`, `vendor_gps`, or `geocoded_address` with a
capture time and optional accuracy measurement. Submissions are always
`pending_review`. An administrator may submit an explicitly verified coordinate
or review a pending submission through
`POST /api/tie/location/vendor-coordinate-verify.php`.

## Precision-search rule

Only `verified` coordinates can enter radius/nearby search. Pending,
unverified, rejected, malformed, or missing listing coordinates are presented
as non-precision locations to TIE consumers. This prevents a vendor-provided
pin from affecting nearby recommendations before review.

## Provenance and audit

The listing stores current source, accuracy, capture time, verification status,
verifier, and verification time. `listing_location_audit` records every
submission and review with actor, action, source, status, quality metadata, and
an optional short review note. It intentionally stores no customer location or
movement data.

## Operational guidance

1. Add coordinates during vendor listing creation or update, ideally from a
   confirmed map pin or a reviewed address-geocoding result.
2. Review `pending_review` records against the listing address and vendor
   evidence.
3. Reject incorrect pins with a correction note; vendors resubmit corrected
   data.
4. Periodically audit rejected/pending records and the proportion of listings
   with verified coordinates.

The existing `location` address field remains descriptive marketplace data; it
does not by itself authorize a precision coordinate.
