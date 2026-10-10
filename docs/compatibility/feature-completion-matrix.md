# Feature completion matrix (F01-F22)

Generated from `feature-completion-matrix.json`. Updated 2026-10-11; PR #40 head assessed `8aee9711261a278f2c0eecd06bebac255ec63a2f`. Statuses follow the program rules: a follow-up PR is READY_IN_FOLLOWUP_PENDING_INTEGRATION, a design is never an implemented feature.

| ID | Feature | Kind | Status | Branch / PR | Tests | In PR #40 head | Release blocker | Limits |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| F01 | Safe referenced writes | REQUIRED_IMPLEMENTATION | READY_IN_FOLLOWUP_PENDING_INTEGRATION | #93 feat/46-safe-referenced-writes d3037cb | tests/Referenced/* (A/B takeover, mixed lists, ids, fields, cardinality, lifecycle, events, example); 440 passed, 100 % | no | P0 release blocker until included | Preflight is not a transaction; nested referenced relations validated at write time only. |
| F02 | Queued database/tenant context | REQUIRED_IMPLEMENTATION | READY_IN_FOLLOWUP_PENDING_INTEGRATION | #95 feat/f02-sync-context c68fa3a | tests/Context/SyncContextTest.php (same ids in 5 databases, serialized jobs + queue:work, retry, tenants, legacy payloads); 425 passed, 100 % | no | P0 release blocker until included | No distributed transaction; analyser scans declared connections. |
| F03 | Stale concurrent write protection | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | P0 release blocker | Needs a storage contract (revision/watermark): design and approval gate first. |
| F04 | SyncPlan and read-only dry-run | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F05 | Operational analyzer | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F06 | Sync events and metrics | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F07 | Per-operation target-sync opt-out | REQUIRED_IMPLEMENTATION | READY_IN_FOLLOWUP_PENDING_INTEGRATION | #94 feat/45-target-sync-opt-out fdc3fcb | tests/TargetSyncOptOutTest.php (both modes, real queue, events, nesting, exceptions, boundary); 421 passed, 100 % | no | - | Does not cancel jobs queued earlier. Interactions with F04/F06/F08 pending those features. |
| F08 | Bounded dispatch coalescence | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | Observed on the head: one updateWithSync dispatches 2 jobs per relation (one per save). |
| F09 | Durable delivery / outbox | REQUIRED_SEPARATE_DESIGN | NOT_STARTED | - | - | no | - | Design only until approved. |
| F10 | Recursive target sync and cycles | REQUIRED_SEPARATE_DESIGN | NOT_STARTED | - | - | no | - | Design only until approved. |
| F11 | Projection registry and non-HTTP services | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F12 | Utility commands | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F13 | Native migration generation | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F14 | Resumable data transformations | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F15 | Versioned projection backfill | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F16 | Lifecycle modes and field retirement | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F17 | Consumer testing kit | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F18 | Bulk/import integration | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F19 | Versioned docs, Boost and capability manifests | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F20 | Real optional MCP server | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F21 | Agent integration evaluation kit | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F22 | External-write change-stream adapter | REQUIRED_SEPARATE_DESIGN | NOT_STARTED | - | - | no | - | Design only until approved. |
