# F03 – Stale-write protection for projections (design, pending approval)

Status: **DESIGN_DELIVERED_PENDING_APPROVAL** (BLOCKED_DESIGN_APPROVAL for the implementation). It introduces a storage contract (new fields in source and target documents), so per the program rules it is reviewed before code changes.

## 1. Contract

After the relevant jobs and retries have completed and the source has stopped changing, every target reflects the **latest accepted** state of the source: an older write never overwrites a newer projection, and a removed reference is never resurrected. Not promised: exactly-once execution, instantaneous consistency, protection for writes made outside the package (raw/bulk writes, see F18/F22).

## 2. Current behaviour (head `8aee971`, after F02/F07/F08)

- The job reloads the source when it starts and writes removals, then the upsert (`removeRelationWithSync()` / `updateRelationWithSync()`), each an atomic update on one target document.
- Reproduced (roadmap B): worker A reads source v1, worker B reads v2 and writes v2, A resumes and writes v1 → the target regresses. A stale job that read the source before it was deleted can re-add the projection after the delete job removed it.
- The synchronous path writes directly with the same two methods, with the same race between concurrent requests.

## 3. Options

| Option | Ordering state | Pros | Cons |
| --- | --- | --- | --- |
| **A. Source revision + conditional target writes + removal tombstones (recommended)** | `_sync_revision` on the source; the revision inside each projected entry; a bounded tombstone map on the target | No locks, no topology requirement beyond MongoDB 4.2 pipeline updates (7.0+ is already required); works for sync and queued writers; replay-safe | New fields in business documents; tombstone retention must outlive the oldest pending job; writes outside the package do not bump the revision |
| B. Per-source lease lock + fencing token | lock collection + token on targets | Serialises workers | A lease expiry is not a correctness guarantee; needs fencing on targets anyway (same storage cost as A) plus a lock collection and timeouts |
| C. Multi-document transactions | none | Simple reasoning | Requires a replica set; does not order two transactions that read different source versions; does not cover the synchronous path's concurrent requests |

## 4. Proposed protocol (option A)

1. **Revision.** Every package write of a source (`storeWithSync`, `updateWithSync`, plain `save()` of an `MDModel`, `destroyWithSync`) increments `_sync_revision` atomically in the same MongoDB update that persists the source (`$inc` merged into the update built by `MDModel::performUpdate()`; `1` on insert). Two concurrent saves obtain two different revisions; the stored document always holds the highest.
2. **Projection entries** carry `_sync_revision` (EmbedsMany items and the EmbedsOne value).
3. **Upsert** of source `S` with revision `r` on target `T` is ONE pipeline update:
   `filter: {_id: T, $nor: [{sub_items: {$elemMatch: {ref_id: S, _sync_revision: {$gte: r}}}}, {"_sync_removed.S": {$gte: r}}]}`
   `update: [{$set: {sub_items: {$concatArrays: [{$filter: {input: "$sub_items", cond: {$ne: ["$$this.ref_id", S]}}}, [mini + _sync_revision: r]]}}}, {$unset: "_sync_removed.S"}]`.
   An older or equal revision matches nothing: no regression, no duplicate (replace-by-filter, not push).
4. **Removal** of `S` from `T` at revision `r` (reference removed or source deleted): pull the entries with `_sync_revision < r` and set `_sync_removed.S = max(existing, r)` in the same update. A later stale upsert with revision `≤ r` is rejected by step 3. A newer upsert (`> r`, the reference was added back) wins and clears the tombstone.
5. **Delete.** `destroyWithSync()` increments the revision before deleting the source (one update), dispatches removals with that revision; the tombstone survives the source document.
6. **Jobs** carry the revision they were dispatched for and re-read the source: they write the stored revision (never a payload value), so replays and retries converge.
7. **Legacy data:** entries without `_sync_revision` count as revision 0; the first package write upgrades them.

Where ordering state persists: source document (`_sync_revision`), target document (entry revision, `_sync_removed` map). Nothing in a separate collection; no lock, so lease loss or a crashed worker cannot corrupt ordering (a crash between removal and upsert leaves a state the next job/replay or `mongo-sync:analyse --fix` reconciles).

Tombstone retention: `_sync_removed` entries are pruned by an explicit maintenance command older than a configured horizon that must exceed the queue's maximum job lifetime (`retry_until`/`backoff`); documented as an operational requirement.

## 5. Decisions requested (D2)

1. Field names `_sync_revision` and `_sync_removed` in business documents (hidden from `toArray()` via `$hidden` on `MDModel`), or a single `_sync` sub-document.
2. Revision bump on **every** package write, or only when a projected field changes (smaller churn, more code).
3. Tombstone pruning command and default horizon (proposal: 30 days, configurable).
4. Accept that raw/bulk writes outside the package do not participate until F18/F22.

## 6. Implementation plan (after approval)

- `src/Sync/Revision.php` (bump on insert/update via `MDModel::performInsert/performUpdate`).
- `src/Traits/RelationshipMongoTrait.php`: `updateRelationWithSync()` / `removeRelationWithSync()` become the conditional pipeline updates above (one code path for sync and queued writers).
- `src/Jobs/SyncTargetJob.php`: payload carries the dispatch revision (payload version 3, compatible with v2: missing revision = read from the source).
- `src/Console/PruneSyncTombstones.php`; config `tombstone_retention_days`.
- Docs: consistency guide, UPGRADE (new fields, MongoDB 4.2+ pipeline updates).

## 7. Acceptance tests (deterministic, explicit barriers)

Barriers are documents in a test collection that two `php artisan` worker processes (Symfony Process) wait on; no sleep-based timing.

| Scenario | Expected |
| --- | --- |
| A reads v1; B writes v2 and syncs; A resumes with v1 | target keeps v2 |
| duplicate and retried jobs | one entry per source |
| target A -> B -> C with jobs out of order | only C holds the entry; A and B tombstoned |
| delete vs pending update (stale job resumes after the delete job) | no resurrection |
| two sources on one target concurrently | both entries present |
| crash between removal and upsert, then replay | converges |
| synchronous request racing a queued job | same guarantees |
| legacy entries without revision | upgraded on first write |

Exit codes, raw final documents and barrier logs are kept as evidence.
