# Issue #46 – referenced relationships: acceptance evidence

Issue: https://github.com/offline-agency/laravel-mongo-auto-sync/issues/46 ("Add Support for referenced relationships", body: support for MongoDB referenced relationships). State on 2026-10-11: OPEN, no comments. No closing keyword for #46 was found in the develop history or in the descriptions of #40 and #93.

## Scope decision

The issue asks for referenced relationships in general. The supported subset is **`HasOne` and `HasMany` with the foreign key on the child, written through `storeWithSync()` / `updateWithSync()`** (see `docs/referenced-relationships.md`). `BelongsTo`, `BelongsToMany`, morph relations and referenced relationships nested inside embedded documents (checked when written, not in the top-level preflight) are not supported and are documented as such.

## Environment of this run

| Item | Value |
| --- | --- |
| Base | develop / PR #40 head `35e881bbe3f9ef87de8b3ab4f290b5a449b2c58e` |
| Branch | `fix/46-referenced-ownership-completion` |
| MongoDB | 8.0.32, replica set `rs0` (real server, no mocks); standalone 8.0 for the skip check |
| PHP / ext-mongodb | 8.4.26 / 2.5.4 |
| Packages | laravel/framework 13.35.0, mongodb/laravel-mongodb 5.11.0, mongodb/mongodb 2.5.0, pestphp/pest 4.7.8 |
| Not executed here | the CI matrix cells (PHP 8.5, Laravel 12, MongoDB 7.0, lowest-safe): they run on the PR |

## Baseline and reproduction

Baseline on `35e881b`: 471 passed, line coverage 100 % (1536/1536).

`tests/Referenced/AuditTest.php` on the unmodified head: **9 failed, 4 passed**. Observed defects:

| # | Defect on `35e881b` | Evidence |
| --- | --- | --- |
| D1 | a child excluded by the relation's extra condition (`->where('status', 'active')`) was updated through the payload | exception not thrown, document changed |
| D2 | a configured `localKey` different from the relation's local key was accepted | exception not thrown |
| D3 | a child re-parented by another request after the plan was built was overwritten and brought back to the first parent | raw `ref_author_id` reverted |
| D4 | a left-out child re-parented meanwhile was deleted from its new parent | raw document missing |
| D5 | a `HasOne` holding two stored documents was written, the duplicate silently ignored | snapshot changed |
| D6 | a child payload missing one of the child's relationship keys failed **after** the parent was written | snapshot changed |
| D7 | same, missing a child item | snapshot changed |
| D8 | same, malformed nested payload | snapshot changed |
| D9 | `destroyWithSync()` (`onParentDelete = delete`) used the cached relation and deleted a child that another parent owned meanwhile | raw document missing |

Already correct on the head (passed before the fix): local-key value written on children, a valid first relationship followed by an invalid second one writes nothing, soft-deleted children are absent, a caller transaction rolls back parent and children.

## Changes

- `ReferencedWritePlanner`: children are read through the declared relation (`mergeConstraintsFrom()` of the relation query, on the parent's connection, with global scopes); the relation's local key must match `localKey`; a `HasOne` with several stored documents raises `ReferencedConflictException`; each document is checked against the child's own full request (`MDModel::assertFullRequestIsValid()`: items, relationship keys, nested payloads) before anything is written; `owned()` for lifecycle deletes.
- `MDModel::withWriteConstraint()` adds the relation's conditions to the filter of the child's update and delete queries (`setKeysForSaveQuery()`).
- `processReferencedRelationship()` / `deleteReferencedObj()`: atomic ownership check right before each write; updates of a child that changed owner raise `ReferencedConflictException`, deletes are skipped; lifecycle deletes read the relation again.

## Acceptance matrix (after the fix)

| Requirement | Test (persisted state asserted) | Result |
| --- | --- | --- |
| 1. B's child id in A's update → error, zero writes | `OwnershipTest` "B's child alone", "through _id" (raw snapshots of 3 collections) | pass |
| 2. valid child of A then B's child → zero writes | `OwnershipTest` "a valid entry, then B's child" | pass |
| 3. relation filter excludes a child → no mutation | `AuditTest` relation constraints (D1) | pass |
| 4. ownership changes after preflight → no update/delete/reparenting | `AuditTest` update race (D3), delete race (D4), destroy with stale cache (D9) | pass |
| 5. overlapping tenants, create/update/delete/retry | `ReferencedContextTest` (same ids in two databases, omission delete, retried request) | pass |
| 6. legitimate CRUD, full/partial, sync/background, events, opt-out | `OwnershipTest` CRUD both modes + events, `TraitsReferencedRelationshipsTest`, `OptOutInteractionTest`, `ExampleTest` | pass |
| 7. invalid configuration fails explicitly; no existence oracle | `OwnershipTest` config cases, `AuditTest` local key (D2); same message for unknown and foreign ids | pass |
| 8. lifecycle and bounded failure behaviour | `LifecycleTest`, `AuditTest` HasOne duplicates (D5), nested preflight (D6-D8), soft deletes, transaction rollback (replica set; skipped on standalone like the other transaction tests) | pass |

Commands (exit 0 unless stated): `vendor/bin/pest` (486 passed, line coverage 100 %, 1559/1559, replica set), `vendor/bin/pest tests/Referenced/AuditTest.php` on a standalone server (13 passed, 1 skipped: the transaction test), `vendor/bin/phpstan analyse --memory-limit=1G` (level max, 0 errors), `vendor/bin/pint --test`, `composer validate --strict`, `composer audit` (no advisories), `git diff --check`.

## Accepted limits

- Not a transaction: a database error or an ownership conflict while children are written leaves earlier writes in place; callers on a replica set can wrap the call in `DB::connection(...)->transaction()` (tested).
- Two requests updating the same child concurrently: last write wins (no revision check; see F03 design).
- Between the atomic ownership check and the constrained write, Eloquent's `updated`/`deleted` events can fire for a write that matched nothing.
- Creating children is not idempotent on retry without ids.
- Supported subset as stated above.

## Closure

Self-review only (no independent reviewer available). After this branch is merged into develop and the full suite passes on the resulting PR #40 head, the contract above is met for the stated subset: a closing reference for #46 in the PR #40 closure body is then justified. Until then #46 stays open.
