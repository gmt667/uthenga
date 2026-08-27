# TIE Phase 3 query engine

## Deployed profile

The XAMPP database was profiled on 2026-07-28 as MariaDB 10.4.32. Its
canonical published inventory source is the active `listings` table—not the
unused normalized schema in `database/production_schema.sql`.

| Domain | Canonical source | TIE treatment |
| --- | --- | --- |
| Published service | `listings` | Authoritative published inventory record. |
| Vendor identity and eligibility | `listings.vendor_id` / `vendor_name`, checked against `users.is_approved` and `users.account_status` | Read-only eligibility filter. |
| Category | `listings.listing_type` plus `meta.category` | Normalized to event, accommodation, tour, or transport. |
| Price | Type-specific `listings.meta` values | Published price only; never booking-authoritative in Phase 3. |
| Declared availability | Type-specific `listings.meta` values | Exposed as declared data; Phase 4 must validate it. |
| Schedules and routes | Type-specific `listings.meta` values | Normalized without inference. |
| Location | `listings.location`, optional `gps_lat`/`gps_lng` | Seed data has text locations and no populated coordinates. |

The baseline currently contains 11 active listings: 5 events, 2
accommodations, 2 tours, and 2 transport services. This is demonstration data,
not a claim of production catalogue completeness.

## Boundary and safety

`php_app/includes/tie/Query.php` contains the criteria contracts, category
normalizer, read-only `UthengaTieListingsRepository`, and catalogue service.
No other TIE module accesses raw `listings.meta` or SQL. All repository queries
are prepared statements; SQL structure is fixed and request values are bound.

The repository reads only active listings whose matched user is approved and
active. Its deterministic ordering is `updated_at DESC, id ASC`; it does not
rank, recommend, or personalize results. The database fetch window is capped
at 250 and API page size at 50. A response warns when the source window is
reached, rather than silently presenting it as exhaustive.

Prices and availability are deliberately never cached. Category results are
also currently uncached, keeping the boundary simple and fresh.

## Normalized candidate

Every returned candidate has stable fields for identity, title, category,
vendor, location, price, declared availability, schedule, rating, media,
source provenance, and freshness. `source.profile` is
`unified_listings_v1`; consumers must retain it for troubleshooting and future
profile migration.

`availability.validation_status` is always `phase_4_required`. A value such
as `declared_available` means the vendor-published listing says units remain;
it never means the service can be booked.

## Filter support

`q`, `destination`, `origin`, `category`, `vendor_id`, `date`, `min_price`,
`max_price`, `availability`, `page`, and `page_size` are supported. Date
filtering uses event dates, tour available dates, and transport schedule days.
Accommodation date availability remains intentionally unvalidated until Phase
4.

`latitude`, `longitude`, and `radius_km` are accepted for a radius filter when
listing coordinates are present. The current seed records have no coordinates,
so a correctly formed radius query returns no candidates with a clear warning.
The schema can carry coordinates; location enrichment and consent handling are
still Phase 5 work.

## Known data/schema risks

The initial migration run exposed legacy collation drift: several optional
tables fail foreign-key creation when `utf8mb4_general_ci` and
`utf8mb4_unicode_ci` columns are linked. The working TIE profile uses
`listings` and `users`, which are present and verified. Before relying on
optional rooms, tickets, role, or social modules, normalize those collations
and rerun the incomplete migrations. This is a maintenance prerequisite, not
a reason to invent a second inventory model.
