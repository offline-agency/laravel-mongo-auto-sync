# F10 – Recursive target synchronisation and cycle handling (design, pending approval)

Status: **DESIGN_DELIVERED_PENDING_APPROVAL**. Current behaviour is unchanged: only single-hop sync (source → its direct targets), on both paths. Recursive propagation is not cascade deletion.

## 1. Problem

A target can itself be the source of other projections (Article → Category → Menu). Today, updating Article refreshes the Article mini model inside Category, but Category's own projection inside Menu is not refreshed when the change affects fields Category projects. The pre-4.0 code attempted level > 1 sync with a malformed request key and never worked.

## 2. Graph contract

- **Nodes:** `(context, model class, id)` (F02 context in the identity).
- **Edges:** declared `mongoRelation` entries with a target, authoritative direction source → target; only edges whose projected fields changed are followed.
- **Revision:** each visit carries the revision of the node that caused it (F03); a node visited again with a newer revision is new work, the same revision is a duplicate.
- **Visited set:** `(node, revision)` per propagation; cycles (A → B → A) stop when the same revision reaches a node again.
- **Budgets:** max depth (default 1 = current behaviour), max fan-out per hop, max nodes per propagation, deadline. Exhausting a budget stops with an explicit `incomplete` outcome (F06 event, analyser finding), never "synchronised".
- **Ordering:** each hop is its own `SyncTargetJob` (queued) or call (sync), so F03 conditional writes protect every hop.

## 3. Synchronous vs queued

Synchronous: depth > 1 is executed in the request only up to `sync_max_depth` (default 1); deeper hops are queued. Queued: each job, after writing its targets, dispatches the next hop for targets whose projected fields changed, carrying `propagation_id`, `depth`, `visited` digest (bounded) and revisions. F09 outbox (when approved) records each hop's intent.

## 4. Safe defaults and upgrade

`recursive_sync.enabled = false`, `max_depth = 1`. Enabling is per relation (`propagate: true`) so a graph is opted in edge by edge. Upgrade guide: enable, run `mongo-sync:analyse` to measure drift, then backfill (F15).

## 5. Future tests

A → B → A cycle, self-loop, diamond (A → B → D, A → C → D: D written once per revision), deep chain hitting max depth (incomplete outcome), fan-out budget, delete of a middle node during propagation, two tenants with the same ids, partial failure + retry, the same entity reached with two revisions (newest wins).

## 6. Files (proposed)

`src/Propagation/PropagationContext.php`, `src/Propagation/Graph.php`, changes in `SyncTargetJob` (next-hop dispatch), config keys, `tests/Propagation/*`.

## 7. Decision requested

Approve the opt-in per-relation model, the default budgets and the dependency on F03 (revisions) before implementation.
