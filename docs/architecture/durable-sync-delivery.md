# F09 – Durable delivery of target sync (design, pending approval)

Status: **DESIGN_DELIVERED_PENDING_APPROVAL**. No table, collection, daemon or service is added by this document. Implementation follows an explicit approval.

## 1. Failure window today

Background sync dispatches `SyncTargetJob` from the model observer (coalesced per operation, F08) **after** the source write returned. If the process dies between the source write and the queue push, or the queue is unavailable at that moment, the target update is lost: nothing records that it was owed. Inside `DB::transaction()` a queue that is not part of the transaction only waits for the commit with `after_commit => true`, and laravel-mongodb 5.11 can run deferred callbacks of a rolled-back transaction at the next commit (reproduced, see UPGRADE).

Recovery today: `mongo-sync:analyse --fix` (presence checks) or `mongo-sync:sync-collection`. Neither is a delivery guarantee.

## 2. Invariants

1. A committed source change that requires target sync has a durable **intent** before the write is acknowledged, or the write fails.
2. An intent is delivered at least once; delivery is idempotent (the job re-reads the source; F03 revisions reject stale replays).
3. An intent survives the deletion of its source (it is not stored inside the source document).
4. Intents are scoped by context (F02: connection, database, tenant) and never cross it.
5. Poison intents stop after a bounded number of attempts and stay visible.

## 3. Options

| | Transactional outbox (recommended) | Change streams (F22) | Periodic reconciliation only |
| --- | --- | --- | --- |
| Atomicity with the source write | same transaction (replica set) | observes committed writes | none |
| Topology | replica set for atomicity; standalone degrades to "intent written right after the source" | replica set + oplog retention | any |
| Covers raw/external writes | no | yes | yes (eventually) |
| Extra infrastructure | one collection + relay command | stream worker with resume tokens | scheduler |

Recommendation: outbox for package writes, with reconciliation (F05 analyser) as the safety net; change streams stay the separate F22 project.

## 4. Proposed design

- Collection `mongo_sync_outbox` **on the source's connection** (same database, so it can join the source's transaction): `{_id, context: {connection, database, tenant}, source_class, source_id, relation, revision, current_ids, removed_ids, state, attempts, available_at, created_at, last_error_code}`; unique index on `(context, source_class, source_id, relation, revision)` (dedupe of replays).
- Write path: `storeWithSync`/`updateWithSync`/observer flush (F08) insert the intent in the same `DB::transaction()` as the source when the connection is a replica set; on a standalone server the intent is inserted right after the source write and the residual window is documented.
- Relay: `php artisan mongo-sync:relay` (long-running, or scheduled `--once`) claims intents with `findOneAndUpdate(state=pending → publishing, lease_until)`, pushes `SyncTargetJob`, marks `published`. A crash after the push and before marking leaves `publishing` with an expired lease: re-published, deduplicated by idempotent jobs.
- States: `pending → publishing → published → done | failed(poison)`; `done` set by the job after its target writes; retention: `done` removed after N days, `failed` kept until an operator acts (`mongo-sync:outbox:retry|discard`).
- Deletes: `destroyWithSync()` writes a removal intent before deleting the source, in the same transaction.
- F08: the coalesced flush writes one intent per relation; F06 events are emitted by the relay and the job (`queued` only after publication, `succeeded` only after the target writes).

## 5. Permissions and configuration

`insert`/`update`/`find` on `mongo_sync_outbox`; `createIndex` once via a generated migration (F13). Config: `outbox.enabled` (default false), `outbox.connection_strategy` (`source`), `outbox.max_attempts`, `outbox.retention_days`. Enabling it is an explicit opt-in; disabled installs create nothing.

## 6. Migration and rollback

Enable: run the generated migration, deploy the relay, then turn `outbox.enabled` on (intents queue up until the relay runs). Disable: turn it off, drain with `mongo-sync:relay --until-empty`, keep or drop the collection (the down migration drops only the collection it created).

## 7. Acceptance scenarios for the implementation

Crash before commit (no intent, no source change), after commit before publication (relay publishes), after publication before marking (republished, idempotent), duplicate delivery, queue outage then recovery, worker crash mid-job, delete intent after source deletion, two tenants with the same ids, retries to poison, operator retry/discard. No results are claimed here: these are the tests the implementation must pass.

## 8. File-level plan

`src/Outbox/OutboxIntent.php`, `src/Outbox/OutboxRepository.php`, `src/Outbox/Relay.php`, `src/Console/RelayCommand.php`, `src/Console/OutboxAdminCommand.php`, changes in `MainMongoTrait` (flush → intent), migration stub generator (F13), `tests/Outbox/*` with process-level crash simulation.

## 9. Decision requested

Approve the outbox collection, the relay command and the replica-set requirement for atomic intents (standalone: documented residual window).
