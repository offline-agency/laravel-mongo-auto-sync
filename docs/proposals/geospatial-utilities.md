# Proposal – geospatial utilities (separate from #46)

Status: **proposal for review**, no code. Independent of issue #46 and of PR #40. Prepared on 2026-10-11 against `mongodb/laravel-mongodb` **5.11.0** (latest stable, 2026-09-10), `mongodb/mongodb` 2.5.0, MongoDB 8.0.

## 1. What the official stack already provides (verified in the 5.11.0 sources)

| Capability | Where | Package needs to add? |
| --- | --- | --- |
| `$near`, `$nearSphere`, `$geoWithin`, `$geoIntersects` as query operators (`where('location', 'near', [...])`, `'geoWithin'`, `'geoIntersects'`) | `MongoDB\Laravel\Query\Builder` (operators list, `nearsphere`/`geowithin`/`geointersects` mapping) | no |
| `$geoNear` aggregation stage | `mongodb/mongodb` Builder (`Stage::geoNear()`), usable from laravel-mongodb's aggregation builder | no |
| `2d` / `2dsphere` indexes in migrations | `Blueprint::geospatial($columns, '2dsphere')` | no |
| GeoJSON storage | plain BSON documents / arrays | no |
| Validation that a stored value is valid GeoJSON with WGS84 ranges | MongoDB rejects invalid geometries **only** on fields with a `2dsphere` index; nothing in the driver for unindexed fields | possible gap |
| Diagnostics: which geo fields exist, are indexed, hold invalid data | none | possible gap |
| Keeping a geo field consistent in denormalised projections | this package's sync copies fields; no geo-specific behaviour needed | no, beyond validation |

Existing package code: `src/Http/Models/DefaultGeo.php` is **not** a geospatial helper: it is an application-specific model (`$primaryKey = 'cadastralCode'`, Italian cadastral codes) left from the pre-4.0 code base, like the methods moved to `docs/upgrade/extracted_methods.php`. Recommendation: deprecate it in 4.x and move it to the upgrade examples (separate small PR); it is unrelated to the options below.

## 2. Options

| | A. Documentation and tests only | B. Optional geo diagnostics on the shared services | C. Separate adapter package |
| --- | --- | --- | --- |
| Value | shows correct native usage (axis order, units, indexes) | finds invalid/unindexed geo data in synced projections, reuses `doctor`/`index-audit`/`schema-audit`/MCP | room for GIS features (reprojection, polygons validation library) |
| Runtime cost | none | none unless the commands run; no new dependency for Point | a second package to release and test |
| Risk | low | medium: must stay read-only and bounded | high: scope creep towards GIS |
| Depends on | nothing | F11 registry, F12 commands, F20 MCP (not yet merged) | nothing in core |

**Decision proposed: A now, B after F11/F12 are merged, C not needed.** The driver covers queries and indexes completely; the only reusable gap is *diagnosing* geo fields of synchronised models, which belongs to the existing diagnostics rather than to a new query layer. No competing query builder, geometry engine or GIS feature is proposed.

## 3. Option A – deliverables

- `docs/geospatial.md`: storing a GeoJSON Point (`['type' => 'Point', 'coordinates' => [$lng, $lat]]`, **longitude first**, WGS84), creating a `2dsphere` index with `Blueprint::geospatial('location', '2dsphere')`, `near` with `$maxDistance` in **meters**, `geoWithin` with `$centerSphere` in **radians**, `$geoNear` through the aggregation builder, and why `2d` indexes must not be used for spherical distances.
- `tests/Geo/NativeGeoQueryTest.php` (real MongoDB): the documented examples, a `geoIntersects` query without an index (valid, slower), `near` without an index (MongoDB error, documented), an insert of an invalid Point on an indexed field (rejected by the server).
- A note in the README "when the official driver alone is sufficient".

## 4. Option B – proposed API (after F11/F12)

Geo profiles are part of the F11 projection registry (trusted application configuration): `['geo' => ['location' => ['type' => 'Point', 'crs' => 'EPSG:4326', 'index' => '2dsphere']]]` on a model. Only Point is supported at first; `Polygon` / `MultiPolygon` / `LineString` only with full validation (closed rings, nesting, non-degenerate, antimeridian/pole cases) and integration tests.

| Command | Contract |
| --- | --- |
| `mongo-sync:geo-audit {profile} --limit=N --json` | Read-only, bounded (`N` ≤ configured max, cursor by `_id`): counts of valid / invalid (`not_geojson`, `wrong_type`, `lng_out_of_range`, `lat_out_of_range`, `non_finite`, `axis_suspect` = lat/lng plausibly swapped, never auto-fixed) / null / unverifiable values, and whether the expected `2dsphere` index exists (`listIndexes`). Reports document ids, **never coordinates**, unless an explicit `--with-coordinates` permission is configured. Exit 0 only when the bounded scan completed with no invalid value; partial scans report `incomplete`. |
| `mongo-sync:geo-explain {profile}` | Describes connection, collection, field, index, operator support, WGS84 / meters / radians assumptions and limits; runs no spatial query and accepts no operator from input. |
| `mongo-sync:make-geo-index {profile}` | Generates a reviewable migration stub using `Blueprint::geospatial(..., '2dsphere')`; never applies it, never overwrites a file. Notes that building a `2dsphere` index on a collection with invalid geometries fails, so `geo-audit` runs first. |
| `GeoPoint` value object (optional) | `GeoPoint::fromLngLat(float $lng, float $lat)` validates finite numbers and ranges and returns the GeoJSON array; no CRS inference, no axis swapping; projected inputs require an explicit CRS and an application-provided transformer. |
| MCP `audit_geo` (F20) | Same service as `geo-audit`, read-only, tenant-authorised, coordinates hidden by default. |

Synchronised projections: a geo field copied into a mini model is validated by the same `GeoPoint` rules when the projection is written (opt-in per profile), and backfilled through F15 when its format changes; no separate migration engine.

### Tests for option B

Index absent/present; invalid coordinates of every class; null geometry; swapped axes reported but not fixed; scan limit and cursor continuation; tenant A/B with identical ids (only the authorised tenant is scanned); coordinates absent from output and error messages; `geo-explain` executes no query (query log empty); `make-geo-index` refuses to overwrite; MCP read-only with zero writes.

## 5. Geo + vector search

`$vectorSearch.filter` accepts only a limited set of comparison and logical operators on fields indexed as `filter` in the vector index; it does **not** accept `$geoWithin`, `$near` or other geospatial operators. A combined "nearby and semantically similar" query therefore needs one of:
1. a bounded `$geoWithin` / `$geoNear` candidate selection (ids, capped), then `$vectorSearch` with `filter: {_id: {$in: ids}}` (requires `_id` declared as a filter field), authorisation applied to both steps;
2. a precomputed coarse location token (e.g. a geohash/tile string field indexed as a vector-search filter) for a pre-filter, refined afterwards with an exact geo check.

Both trade recall (candidate cap, tile boundaries) for latency; they are a later, separate PR under F23–F26 with integration tests on a `mongot`-capable deployment, and are not claimed here.

## 6. Security, dependencies, versions

- No new runtime dependency for A or B (Point validation is plain PHP); GIS libraries only under option C.
- Coordinates are personal data in many domains: diagnostics hide them by default, logs never contain them.
- Feature flags: none for A; B is enabled per profile in configuration.
- Requires MongoDB 4.2+ for the operators used (the package already requires 7.0+ in CI).

## 7. Proposed follow-up PRs

1. `docs/geospatial-native-usage` – option A (docs + `tests/Geo/NativeGeoQueryTest.php`).
2. `refactor/deprecate-default-geo` – deprecate `DefaultGeo`, move it to the upgrade examples.
3. After F11/F12: `feat/geo-diagnostics` – option B commands, `GeoPoint`, MCP tool.
4. Later, under F23–F26: geo + vector candidate strategy with recall/latency evaluation.
