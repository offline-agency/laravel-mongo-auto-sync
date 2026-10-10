# Roadmap (proposals, not implemented)

Proposals that came out of the upstream alignment and of the review of #55/#43. Each one needs its own PR, its own tests and an explicit compatibility note. Priorities: **P1** data integrity or security, **P2** correctness/compatibility, **P3** features and tooling.
Evidence: **REPRODUCED** (observed with a test or probe; probes kept outside the repository), **STATIC** (read in the code).

| ID | Priority | Topic | Evidence | Proposed PR |
| --- | --- | --- | --- | --- |
| B | P1 | Concurrent workers can write old values | REPRODUCED | `fix/sync-concurrency` |
| C | P0 (F02) | Jobs ignore the data connection (tenants) | FIXED on `feat/f02-sync-context`, pending integration | `feat/f02-sync-context` |
| A | **P0 – blocker for releasing 4.0** | Referenced relationships ownership (#46) | REPRODUCED (also by the security review) | `fix/46-referenced-ownership` |
| D-adapter | P2 | Package embedded relations vs laravel-mongodb relations | REPRODUCED | `refactor/embedded-relations-upstream` |
| G | P2 | Growth limits (payload, fan-out, document size) | STATIC | `feat/sync-limits` |
| R3 | P2 | Embedded increment events | REPRODUCED | part of D-adapter |
| R7 | P3 | Dead `$dates` configuration | STATIC | `chore/dates-casts` |
| E | P3 | Disable target sync per operation (#45/#47) | STATIC | `feat/45-47-sync-switch` |
| F | P3 | Advanced analyser | STATIC | `feat/analyse-advanced` |
| H | P3 | Reproducible benchmark (#44) | STATIC | `docs/44-benchmark` |
| I | P3 | PHP 8.3 (#82) | STATIC | maintainer decision |
| D-coalescing | — | Coalescing A -> B -> C | REPRODUCED: no defect | none |
| U | P2 | laravel-mongodb 5.11: deferred callbacks of a rolled-back `transaction()` run at the next commit | REPRODUCED | report upstream (mongodb/laravel-mongodb) |

## B. Concurrency (P1)

- Reproduced: worker A loads the source (v1); worker B saves v2 and runs its job; A then writes its mini model: the target keeps **v1** while the source holds v2 (deterministic barrier on the model `retrieved` event, probe kept outside the repository). Membership (which targets hold the source) is correct; the values are not.
- Proposal: store the source `updated_at` (or a version counter) in each mini model and write it with a conditional update (`$set` only when the stored version is older); a job that loses the race re-reads the source or stops. Test with the same barrier, plus delete/update and retry interleavings and two sources on the same target.
- Compatibility: adds a field to the mini models (persisted format), no migration required (missing version = older).

## C. Tenants and connections (P1)

- **Status:** fixed on `feat/f02-sync-context` (`SyncContext`, `SyncContextResolver`, `tests/Context/SyncContextTest.php`, `docs/tenancy.md`). Reproduced again on `8aee971`: synchronously, a source on `tenant_b` wrote its target in the default database. The text below is the original analysis.

- Reproduced: a source saved on another connection (`setConnection('tenant2')`) dispatches a job that carries only class and id; the worker loads the source from the default connection, does not find it and only performs removals: the target is never updated, silently.
- Proposal: carry the source connection name in the payload (`SyncTargetJob::$sourceConnection`, default null = model default), resolve source and targets on it, restore the previous default after exceptions; keep queue connection and data connection distinct. Tests: same id in two databases, worker with a different default connection, exception during the job.
- Compatibility: new optional constructor argument (pending jobs keep working with the default connection).

## A. Referenced relationships ownership (#46, P0 – blocker for releasing 4.0)

- The security review reproduced the take-over end to end: `storeWithSync()` of an attacker's author with `books=[{"id": "<victim book id>", "title": "stolen", "is_admin": true}]` renamed the victim's book, set `is_admin` and moved it to the attacker. This is 4.0 code (not in 3.x); it must be fixed, or referenced relationship sync disabled, before 4.0 is released.

- Reproduced (triage): updating parent A with a child id of parent B takes the child over (lookup not scoped, foreign key rewritten); any payload key is written on the child; an unknown id creates a new child; children saved with `has-target => false` are never deleted by `destroyWithSync()`.
- Proposal: scope the lookup to the relation (`$parent->relation()->whereKey($id)`), allow only the related model's `$items`, reject unknown ids, preflight every id before deleting; no implicit cascade. Test with parents A/B: an id of B in an update of A changes no document.
- Compatibility: stricter input (payloads with foreign ids or extra keys are rejected).

## D-adapter. Embedded relation classes (P2)

- Reproduced: the package relations extend Illuminate `Relation`, not `MongoDB\Laravel\Relations\EmbedsOneOrMany`, so laravel-mongodb features gated by `instanceof` are unavailable (`withCount`/`withSum` on embedded relations throw `LogicException`, `tests/Compatibility/AggregateParityTest.php`). Upstream fixes in its relations are not inherited (STATIC: `toBase()` not cloned for nested relations, no eager loading in `toCollection()`, `getUpdateValues()` without `$` operators).
- Proposal: make `OfflineAgency\MongoAutoSync\Relationships\*` extend the laravel-mongodb relations and keep only the package delta (sync hooks, `count`, `createMany`, EmbedsOne eager loading), verified by differential tests against native models (parentRelation, save/update/delete, eager loading, nested embedded, custom keys, returned classes and collections). Includes the embedded `increment()` events (R3): the package fires `saving`/`saved` and an extra `updating`, native fires `updating`/`updated` only.
- Compatibility: class hierarchy change (consumers type-hinting `Illuminate\Database\Eloquent\Relations\Relation` keep working; code relying on the extra events must be reviewed). Not done in this round: it must not be faked with artificial type checks.

## G. Growth limits (P2)

- STATIC: no limit on the size of a relationship payload, on the number of targets per source (fan-out) or on the size of a target's embedded list; a target near 16 MB fails with a driver error.
- Proposal: configurable limits checked before writing, with an explicit `InvalidRequestException`/job failure instead of silent truncation.

## U. Upstream transaction callbacks (P2)

- Reproduced on a replica set: after `DB::connection('mongodb')->transaction()` throws, `transactionLevel()` stays 1 and an `afterCommit` callback registered inside it runs at the next committed transaction (`ManagesTransactions::transaction()` rethrows before resetting the state). With `after_commit` queues this dispatches jobs of rolled-back writes; `SyncTargetJob` reloads the stored state, so the effect is limited to extra work. Proposal: report upstream with the reproduction; no workaround in the package.

## R7. `$dates`

- STATIC: Laravel 12/13 `getDates()` ignores the `$dates` property; `MDModel::$dates` and test models still declare it. Proposal: remove it and document date casts.

## E. Disable target sync per operation (#45/#47, P3)

- Proposal: a scoped switch (`MDModel::withoutTargetSync(fn () => ...)`) that disables only the target sync (sync path and observer dispatch), not other model events; nesting-safe and restored after exceptions; tested with persistent workers.

## F. Advanced analyser (P3)

- Proposal: compare embedded values (stale copies), duplicates, malformed payloads, orphan copies on the target side, JSON output, model/relation filters and checkpoints. Before any new repair, define the authoritative side (source vs target) per relation.

## H. Benchmark (#44, P3)

- STATIC: `mongo-sync:benchmark` touches and deletes the first N documents of the collection, which may be existing data. Proposal: run only on a dedicated database/collection, measure write response time and time to consistency separately (background sync), compare with MySQL on the same hardware, publish the reproducible script and raw results.

## I. PHP 8.3 (#82, P3)

- `php ^8.4` is a project decision; `php -l` on 8.3 is not evidence of support. Supporting 8.3 needs CI cells on 8.3 for every framework family and the lowest-safe profile.

## D-coalescing. A -> B -> C (no defect)

- Reproduced: moving a source A -> B -> C and running the jobs in reverse order leaves A = 0, B = 0, C = 1 copies: each job reloads the stored source, so every destination is cleaned. No `ShouldBeUnique` is used.

## Release controls (proposal)

- Release only from `master` after a green `CI` run on the exact SHA being tagged (the release workflow already uses the `workflow_run` head SHA); restrict `workflow_dispatch` releases to `master`. No change to secrets or branch protection is made by this work.
