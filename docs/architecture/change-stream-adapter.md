# F22 – External-write change-stream adapter (design, pending approval)

Status: **DESIGN_DELIVERED_PENDING_APPROVAL**. Writes outside the package (raw queries, other services, `mongosh`) remain **unsupported** by the shipped core until this is implemented and approved.

## 1. Scope

Observe committed writes on registered source collections and turn them into the same target-sync work the package performs for its own writes. Not: a delivery guarantee for intents never committed (F09), exactly-once processing, a requirement for basic users.

## 2. Requirements and topology

- MongoDB replica set or sharded cluster (change streams are unavailable on standalone servers); `changeStream` and `find` privileges on the watched databases; oplog retention longer than the worker's maximum downtime.
- One stream per (connection, database) with a `$match` on registered collections; `fullDocument: updateLookup` for updates, or a source reload by id.

## 3. Behaviour

- **Resume tokens** persisted after each processed batch (collection `mongo_sync_stream_state`, keyed by context). Restart resumes after the last token.
- **History gap** (token no longer in the oplog): stop with an explicit error, require an operator-started re-snapshot (backfill F15) and a new starting point; never skip silently.
- **Initial snapshot + catch-up:** record the cluster time, run the backfill, then start the stream at that time.
- **Deletes:** the event carries only the id: removals use the target projection's references (no source reload possible).
- **Duplicates:** events are delivered at least once; processing is idempotent through F03 revisions.
- **Loop suppression:** package-owned target writes carry a marker (`_sync_writer` on the write or a session tag) and are ignored when they come back on the stream.
- **Tenants:** one stream state per context; no event crosses contexts.
- **Fallback:** periodic `mongo-sync:analyse` reconciliation remains the safety net.

## 4. Tests to define for the implementation

Crash and resume, duplicate events, history gap, delete without source, own-write loop, two tenants with the same ids, stream started during a backfill, oplog rollover.

## 5. Files (proposed)

`src/Streams/StreamWorker.php`, `src/Streams/ResumeTokenStore.php`, `src/Console/StreamCommand.php`, `tests/Streams/*` (replica set only), compatibility guide.

## 6. Decision requested

Approve the topology requirement, the state collection and the operational ownership (who runs the stream worker) before implementation.
