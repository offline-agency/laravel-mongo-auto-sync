# F11 – Projection registry and transport-independent services (interface map, pending review)

Status: **DESIGN_DELIVERED_PENDING_APPROVAL** for the public interfaces below. F12 commands, F14/F15 runs and the F20 MCP adapter are built on them; they are frozen here before implementation, as the program requires for invasive API changes.

## 1. Principles

- One projection definition, used by the sync engine, planner (F04), analyser (F05), backfill (F15), CLI (F12) and MCP (F20).
- Adapt the existing `$mongoRelation` configuration into a typed internal contract; `storeWithSync()` / `updateWithSync()` stay as compatibility adapters.
- Dependency direction: CLI / MCP / HTTP adapters → services → infrastructure interfaces. Services never depend on Laravel MCP classes or on an HTTP `Request`.
- No global mutable "current tenant": every service call receives an `ExecutionContext`.
- Nothing from a request or tool argument becomes a class name, a database name, a filter document or an expression: callers select **registered aliases** only.

## 2. Projection definition

```php
final class ProjectionDefinition
{
    public function __construct(
        public readonly string $name,              // stable logical name, e.g. "article.categories"
        public readonly string $sourceModel,       // class-string<MDModel>, from trusted configuration only
        public readonly string $relation,          // method on the source
        public readonly string $targetModel,       // class-string<MDModel>
        public readonly string $targetMethod,
        public readonly Cardinality $sourceSide,   // EmbedsOne | EmbedsMany | HasOne | HasMany
        public readonly Cardinality $targetSide,   // EmbedsOne | EmbedsMany | none
        public readonly array $fields,             // allowed field paths (list<string>) of the mini model
        public readonly array $transformations,    // registered transformation ids, never code
        public readonly ProjectionMode $mode,      // live (default) | snapshot | manual (F16)
        public readonly int $schemaVersion,        // projection schema version (not a revision, not a payload version)
        public readonly DeletePolicy $deletePolicy,// retain | delete for referenced children (F01 onParentDelete)
        public readonly ResourceLimits $limits,    // fan-out, batch, document bytes
        public readonly bool $enabled = true,
    ) {}
}
```

`ProjectionRegistry` (`all()`, `get(string $name)`, `forSource(class)`) is built at boot from the models' `$mongoRelation` plus optional `config('laravel-mongo-auto-sync.projections')` overrides; it rejects duplicate names, unknown fields, ambiguous authority (two definitions writing the same target path) and disabled entries with `InvalidConfigurationException` / `UnknownProjectionException`. Versions are distinct: projection `schemaVersion`, source revision (F03), job payload version (F02 = 2), report/protocol schema versions (F05/F12/F20).

## 3. Services

| Interface | Responsibility | Used by |
| --- | --- | --- |
| `ContextResolver` | turn an alias (`"default"`, `"tenant:t1"`) into an `ExecutionContext` (wraps `SyncContext` + `SyncContextResolver` of F02); unknown alias fails closed | all |
| `Authorizer` | `can(Principal, Action, ProjectionDefinition, ExecutionContext): Decision`; default policy = deny except configured read actions | services, MCP, CLI |
| `SyncPlanner` | F04: `plan(ProjectionDefinition, ExecutionContext, SourceSelector): SyncPlan` (reads only, versioned, bounded, serialisable) | explain, preview, backfill, MCP |
| `ProjectionComparator` | F05: semantic comparison with the sync's projection rules (ids, dates, decimals, multilingual values, allowed fields) | analyser, schema audit |
| `SyncExecutor` | applies a plan after revalidation (F03 revisions, ownership) | WithSync adapters, backfill, approved MCP runs |
| `RunStore` | F14 run/checkpoint/approval persistence (optional, see §5) | backfill, transformations, MCP run tools |
| `CapabilityReporter` | public and per-principal capability manifests (F19/F20) | doctor, MCP |

`SourceSelector` is a finite DSL: ids, or registered field equality/range filters with bounded values; never a raw MongoDB filter.

## 4. Principals and authorization

`Principal` is resolved by the transport, never from tool arguments: CLI = the operator (all actions the configured CLI policy allows); MCP stdio = an operator-configured local principal with an allow-list of contexts; MCP HTTP = the authenticated user/token (F20). Actions: `read.describe`, `read.plan`, `read.analyse`, `read.audit`, `read.runs`, `write.repair`, `write.backfill`, `write.transform`, `write.retire_field`, `run.pause|resume|cancel`. Write actions are disabled unless configured and require an approval (F20 §15.6).

## 5. Optional operational storage (F13/F14)

Collections created only by a generated migration the operator runs: `mongo_sync_runs` (run id, context, action, projection, versions, code/config fingerprint, state, counters, deadlines, timestamps), `mongo_sync_checkpoints` (run id, batch key, stable traversal key, attempts, lease/fence token), `mongo_sync_approvals` (approval id, principal, context, action, plan digest, fingerprint, budgets, expiry, claimed_run_id unique). Minimal data only, no source documents. Without the migration, features that need storage fail with an actionable error; basic sync never needs it.

Run states: `pending → running → (pause_requested → paused → running) → completed | completed_with_conflicts | failed | (cancel_requested → cancelled)`; a timeout is `failed` with a reason, never `completed`.

## 6. Adapters

- `storeWithSync` / `updateWithSync`: build an `ExecutionContext` from the model's connection (F02) and call the same validation/planning code they use today (F01 planner, #55 validation).
- CLI (F12): `mongo-sync:describe|explain|doctor|…` call services and print versioned JSON with `--json`.
- MCP (F20): `src/Mcp/*` tools are thin wrappers: validate the input schema, resolve principal + context, call the service, wrap the result envelope.

## 7. First vertical slice proposed after approval

`ProjectionRegistry` + `ContextResolver` + `Authorizer` (read-only policy) + `CapabilityReporter`, exposed by `mongo-sync:describe --json` and by the MCP tools `get_capabilities`, `list_projections`, `describe_projection` over stdio (`php artisan mcp:start mongo-auto-sync`, laravel/mcp v1.0.1 as an optional dependency), with wire-level subprocess tests.

## 8. Decisions requested

1. The `ProjectionDefinition` fields and the alias-only context selection.
2. Action names and the default-deny policy.
3. The three optional operational collections and their names.
4. laravel/mcp `^1.0.1` as `require-dev` + `suggest` (not `require`).
