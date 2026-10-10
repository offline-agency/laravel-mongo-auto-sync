# Issue triage (#42, #43, #44, #45, #46, #47, #55, #82)

State on 2026-10-10. All eight issues are open; their bodies are one or two lines (#55 and #82 have only a title) and have no comments.
No issue is closed by this work: an issue can be closed only after the change that addresses it is merged into `develop` and released from `master`.

Evidence levels: **VERIFIED** = observed by running code or tests; **STATIC** = read in the code.
Branches: **A** `fix/upstream-compatibility` (base `fix/pr-40-review`, PR #89), **B** `fix/55-relationship-request-validation`, **C** `fix/43-analysis-command` (both on A).

| Issue | Title | Status | Addressed by |
| --- | --- | --- | --- |
| #42 | Refactor target synchronization to Observer pattern | RESOLVED_BY_CODE (opt-in), pending merge | #89 (+ A) |
| #43 | Command Analyse Database | RESOLVED_BY_CODE for the contract below, pending merge | C |
| #44 | Add benchmark result on Readme | OPEN | roadmap H |
| #45 | Enable/disable sync between collections on save | IMPLEMENTED on `feat/45-target-sync-opt-out` (pending integration into #40) | see below |
| #46 | Support for referenced relationships | FIXED and included in the PR #40 head (develop, #93) | see below |
| #47 | Enable/disable sync between collections on delete | IMPLEMENTED; persisted-state evidence on `feat/45-target-sync-opt-out` (pending integration) | see below |
| #55 | Throw Exception Missing Relationship in Request | RESOLVED_BY_CODE for the contract below, pending merge | B |
| #82 | Upgrade PHP 8.3 | NOT_APPLICABLE as asked (project requires PHP ^8.4); maintainer decision | roadmap I |

## #42 Observer / queue

- `MongoAutoSyncObserver` + `SyncTargetJob` behind `use_background_sync` (default `false`). The job carries ids only, rebuilds the mini model from the stored source and writes targets with atomic updates that join the connection transaction (A). VERIFIED: `tests/BackgroundSyncTest.php`, `tests/Compatibility/TransactionTest.php` (replica set), consumer profile (real MongoDB queue worker).
- Limits: nested targets (level > 1) are not synchronised on either path; HasOne/HasMany relations have no target sync; any plain `save()` of a model with targets dispatches jobs when background sync is enabled; jobs are dispatched before the commit unless the queue connection sets `after_commit` (documented in UPGRADE-4.0.md).
- To close: merge + release; decide whether nested sync is in scope (roadmap).

## #43 `mongo-sync:analyse`

Before C (VERIFIED on `4cff251`): exit code always 0 (also with anomalies and `--fix`); models in sub-directories skipped; the `other_models` loop did nothing; no deduplication; an abstract class crashed the command; a missing `model_path` threw an uncaught exception; `--fix` dispatched one job per inconsistent target.

Contract and status after C (VERIFIED by `tests/Console/AnalyseDatabaseContractTest.php`):

| Requirement | Status |
| --- | --- |
| sub-directory namespaces preserved | done |
| `other_models` included (key + `model_namespace`) | done |
| duplicates removed, abstract and non-model classes skipped | done |
| invalid configuration (error) vs absent optional model (note) | done |
| EmbedsOne/EmbedsMany inverses, ObjectId vs string ids | done |
| exit 0 only without anomalies/errors; 1 for missing target, missing inverse, configuration/operational errors, unsupported target type | done |
| `--fix` with anomalies still exits 1 (also with the sync queue driver) | done |
| no writes and no jobs without `--fix` | done |
| `--fix`: at most one job per source/relation, `queue_connection`/`queue_name`, no repair for missing targets, dispatch failures reported | done |
| end to end: `--fix` exits 1, MongoDB worker repairs, queue empty, no failed jobs, new scan exits 0 | done |

Not claimed: consistent snapshot, comparison of the embedded values, orphan copies on the target side, HasOne/HasMany analysis (reported as unsupported). To close: merge C + release.

## #44 Benchmark

`BENCHMARK.md` holds an empty table; there is no MySQL comparison. `mongo-sync:benchmark` (STATIC) touches and deletes the first N documents of the collection, which may be existing data rather than the documents it inserted: never run it on a real database. To close: reproducible benchmark on dedicated databases, distinguishing write response time from time to consistency (roadmap H).

## #45 / #47 Enable/disable sync per operation

- #47: `destroyWithSync(false)` skips target cleanup on both paths (the background path was fixed in #89, commit bf13709, VERIFIED by `tests/BackgroundSyncTest.php`). It also skips deleting referenced children. No equivalent for save.
- #45: no per-operation switch for `storeWithSync()`/`updateWithSync()`/`save()`.
- Implemented on `feat/45-target-sync-opt-out`: `['sync_targets' => false]` for `storeWithSync()` / `updateWithSync()`, `withoutTargetSync()` for plain saves, and `destroyWithSync(false)` moved from the process-wide observer suspension to the same per-instance scope. `tests/TargetSyncOptOutTest.php` (synchronous and real MongoDB queue): raw target documents unchanged (timestamps included), no new jobs, #55 validation kept, Eloquent and package events fire, nested scope, another model inside the scope keeps syncing, exception restores the scope, the next enabled save/delete syncs, and the boundary: a job queued by an earlier operation is not cancelled.

## #46 Referenced relationships (HasOne/HasMany)

Reproduced again on `8aee971` before the fix (`tests/Referenced/OwnershipTest.php`, 11 of its first 15 tests failing): updating A with B's child id moved the child to A with the payload title, A's own children left out were deleted and B was left with none; an unknown id created a new document.

Fixed on `feat/46-safe-referenced-writes` (`ReferencedWritePlanner` / `ReferencedWritePlan`): lookups among the parent's own children through the declared relation, the same error for unknown and foreign ids, no id on create, writable fields = the child's items and relationships (never the foreign key), duplicate and `id`/`_id` mismatch checks, `HasOne` cardinality, preflight of every top-level referenced relationship before the parent is written, re-planning from fresh reads at write time, `onParentDelete` lifecycle (`delete` / `retain`, default unchanged) with `destroyWithSync(false)` always retaining. Tests: `tests/Referenced/*`, `tests/Coverage/TraitsReferencedRelationshipsTest.php`. Not a transaction (documented).

## #55 Missing relationship key

Before B (VERIFIED on `4cff251`): a missing key of a relation **with** target was not detected (it fell back to a generated request); without target the exception came after the source was written; error messages dumped the request JSON; and omitting an EmbedsMany relation with a target emptied it (`[]`) — in background mode also on the targets (data loss, already on `master`).

Contract and status after B (VERIFIED by `tests/RelationshipRequestValidationTest.php`):

| Requirement | Status |
| --- | --- |
| full request must contain every top-level relationship key | done |
| checked after merging `additionalData`, before writing source/targets or dispatching | done |
| `InvalidRequestException` with or without target, store and update, background on/off | done |
| missing vs explicit `null` / `""` / `"[]"` | done (explicit empty values are present) |
| parser formats not widened | done |
| partial: omitted relations unchanged, updated fields propagated to the copies | done (data-loss defect fixed) |
| same instance partial then full; model without relations | done |
| message names the key, no request content (sentinel secret) | done |

Not claimed: transaction semantics, recursive validation of nested relationships. To close: merge B + release.

## #82 PHP 8.3

The package requires PHP `^8.4`; CI runs 8.4 and 8.5. The triage ran `php -l` on PHP 8.3.33 for `src/` and `tests/` without errors (VERIFIED), but the suite was not run on 8.3 and laravel-mongodb's `php ^8.2` does not prove our code works there. Supporting 8.3 is a maintainer decision with its own CI cells (roadmap I).
