# Issue closure matrix

Generated from `issue-closure-matrix.json`. Live open issues retrieved 2026-10-11 (GitHub search `is:issue is:open`, total 8, complete). PR #40 head assessed: `8aee9711261a278f2c0eecd06bebac255ec63a2f`. A follow-up PR is not part of the head until it is merged into develop.

| Issue | Decision | In PR #40 head | Evidence | Missing | Closing keyword |
| --- | --- | --- | --- | --- | --- |
| [#42](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/42) Refactor target synchronization to Observer pattern | READY_FOR_MERGE_CLOSURE | yes | tested now (full suite on the PR head, real mongodb queue test) | Per-save vs per-operation dispatch is F08 (one updateWithSync dispatches 2 jobs per relation, observed on the head). | after final verification |
| [#43](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/43) Command Analyse Database | READY_FOR_MERGE_CLOSURE | yes | tested now (full suite on the PR head, incl. end-to-end repair with queue:work) | - | after final verification |
| [#44](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/44) Add benchmark result on Readme | OPEN_DELIVERABLE | no | code inspected (BENCHMARK.md table empty; command selects first N documents, not its own) | Safety contract of BenchmarkCommand, harness, real measurements. | no |
| [#45](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/45) Add option in order to enable/disable sync between collection on save | READY_IN_FOLLOWUP_PENDING_INTEGRATION | no | tested now on feat/45-target-sync-opt-out (PR #94) and on the local integration branch | Merge of #94 into develop. | no |
| [#46](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/46) Add Support for referenced relationships. | READY_IN_FOLLOWUP_PENDING_INTEGRATION | no | reproduced on the PR head (takeover, unknown id creates a document); fixed and tested now on feat/46-safe-referenced-writes (PR #93) | Merge of #93; on the current head the takeover is a release blocker (BLOCKED_SECURITY). | no |
| [#47](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/47) Add option in order to enable/disable sync between collection on delete | READY_IN_FOLLOWUP_PENDING_INTEGRATION | no | behaviour present in the head (only a Queue::fake test); persisted-state evidence tested now on PR #94 (and children retention on #93) | Merge of #94 (evidence) and #93 (referenced children policy). | no |
| [#55](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/55) Throw Exception Missing Relationship in Request | READY_FOR_MERGE_CLOSURE | yes | tested now (full suite on the PR head) | - | after final verification |
| [#82](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/82) Upgrade PHP 8.3 | POLICY_DECISION_REQUIRED | no | code inspected (composer.json requires ^8.4) | Maintainer disposition: superseded / not planned for the v4 support policy, or explicit approval to broaden support. | no |

Acceptance criteria, scope limits, paths and readiness levels are in the JSON file.
