# Feature completion matrix (F01-F26)

Generated from `feature-completion-matrix.json`. Updated 2026-10-11; PR #40 head assessed `35e881bbe3f9ef87de8b3ab4f290b5a449b2c58e`. Statuses follow the program rules: a follow-up PR is READY_IN_FOLLOWUP_PENDING_INTEGRATION, a design is never an implemented feature.

| ID | Feature | Kind | Status | Branch / PR | Tests | In PR #40 head | Release blocker | Limits |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| F01 | Safe referenced writes | REQUIRED_IMPLEMENTATION | VERIFIED_AND_INCLUDED | #93 merged (d3037cb); head 35e881b tested: 442 passed, 100 % | tests/Referenced/* (A/B takeover, mixed lists, ids, fields, cardinality, lifecycle, events, example); 440 passed, 100 % | yes | resolved in the head | Preflight is not a transaction; nested referenced relations validated at write time only. Tenant-connection children fixed on #95 (F01 x F02). |
| F02 | Queued database/tenant context | REQUIRED_IMPLEMENTATION | VERIFIED_AND_INCLUDED | #95 feat/f02-sync-context 861bf77 (merged; head 35e881b: 471 passed, 100 %) | tests/Context/* (same ids in 5 databases, serialized jobs + queue:work, retry, tenants, legacy payloads, referenced children on the parent connection); 454 passed, 100 % | yes | resolved in the head | No distributed transaction; analyser scans declared connections. |
| F03 | Stale concurrent write protection | REQUIRED_IMPLEMENTATION | BLOCKED_DESIGN_APPROVAL | design #98 | - | no | P0 release blocker | Needs a storage contract (revision/watermark): design and approval gate first. |
| F04 | SyncPlan and read-only dry-run | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F05 | Operational analyzer | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F06 | Sync events and metrics | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F07 | Per-operation target-sync opt-out | REQUIRED_IMPLEMENTATION | VERIFIED_AND_INCLUDED | #94 feat/45-target-sync-opt-out 121064e (merged; head 35e881b: 471 passed, 100 %) | tests/TargetSyncOptOutTest.php, tests/Referenced/OptOutInteractionTest.php (both modes, real queue, events, nesting, exceptions, boundary, F01 x F07); 455 passed, 100 % | yes | - | Does not cancel jobs queued earlier. Interactions with F04/F06/F08 pending those features. |
| F08 | Bounded dispatch coalescence | REQUIRED_IMPLEMENTATION | VERIFIED_AND_INCLUDED | #96 merged into feat/45 (e4933e4), part of #94 (merged; head 35e881b: 471 passed, 100 %) | tests/DispatchCoalescingTest.php (update 2 -> 1 job, no-op 0, A->B->C, exception, opt-out, sources apart); 455 passed on #94 | yes | - | In memory per operation; crash loses it (F09). Query counts not measured. |
| F09 | Durable delivery / outbox | REQUIRED_SEPARATE_DESIGN | DESIGN_DELIVERED_PENDING_APPROVAL | #98 | - | no | - | Design only until approved. |
| F10 | Recursive target sync and cycles | REQUIRED_SEPARATE_DESIGN | DESIGN_DELIVERED_PENDING_APPROVAL | #98 | - | no | - | Design only until approved. |
| F11 | Projection registry and non-HTTP services | REQUIRED_IMPLEMENTATION | BLOCKED_DESIGN_APPROVAL | interface map #98 | - | no | - | Interfaces frozen for review before implementation; F12-F20 build on them. |
| F12 | Utility commands | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F13 | Native migration generation | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F14 | Resumable data transformations | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F15 | Versioned projection backfill | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F16 | Lifecycle modes and field retirement | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F17 | Consumer testing kit | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F18 | Bulk/import integration | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F19 | Versioned docs, Boost and capability manifests | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F20 | Real optional MCP server | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | laravel/mcp v1.0.1 (tag 92987a9) verified compatible (Illuminate 12/13); first read-only stdio slice planned after the F11 review. |
| F21 | Agent integration evaluation kit | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | - |
| F22 | External-write change-stream adapter | REQUIRED_SEPARATE_DESIGN | DESIGN_DELIVERED_PENDING_APPROVAL | #98 | - | no | - | Design only until approved. |
| F23 | Registered search profiles | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | Upstream 5.11.0 provides search()/vectorSearch()/autocomplete() and searchIndex()/vectorSearchIndex()/dropSearchIndex(); a mongot-capable backend is needed for integration tests. |
| F24 | Application-managed embedding lifecycle | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | Upstream 5.11.0 provides search()/vectorSearch()/autocomplete() and searchIndex()/vectorSearchIndex()/dropSearchIndex(); a mongot-capable backend is needed for integration tests. |
| F25 | Search/Vector index lifecycle and commands | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | Upstream 5.11.0 provides search()/vectorSearch()/autocomplete() and searchIndex()/vectorSearchIndex()/dropSearchIndex(); a mongot-capable backend is needed for integration tests. |
| F26 | Hybrid retrieval and relevance evaluation | REQUIRED_IMPLEMENTATION | NOT_STARTED | - | - | no | - | Upstream 5.11.0 provides search()/vectorSearch()/autocomplete() and searchIndex()/vectorSearchIndex()/dropSearchIndex(); a mongot-capable backend is needed for integration tests. |
