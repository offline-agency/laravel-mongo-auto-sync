# Issue closure matrix

Generated from `issue-closure-matrix.json`. Live open issues retrieved 2026-10-11 (GitHub search `is:issue is:open`, total 8, complete). PR #40 head assessed: `35e881bbe3f9ef87de8b3ab4f290b5a449b2c58e`. A follow-up PR is not part of the head until it is merged into develop.

| Issue | Decision | In PR #40 head | Evidence | Missing | Closing keyword |
| --- | --- | --- | --- | --- | --- |
| [#42](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/42) Refactor target synchronization to Observer pattern | READY_FOR_MERGE_CLOSURE | yes | tested now (full suite on the head 35e881b, real mongodb queue test) | Per-save vs per-operation dispatch is F08 (one updateWithSync dispatches 2 jobs per relation, observed on the head). | after final verification |
| [#43](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/43) Command Analyse Database | READY_FOR_MERGE_CLOSURE | yes | tested now (full suite on the head 35e881b, incl. end-to-end repair with queue:work) | - | after final verification |
| [#44](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/44) Add benchmark result on Readme | OPEN_DELIVERABLE | no | code inspected (BENCHMARK.md table empty; command selects first N documents, not its own) | Safety contract of BenchmarkCommand, harness, real measurements. | no |
| [#45](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/45) Add option in order to enable/disable sync between collection on save | READY_FOR_MERGE_CLOSURE | yes | #94 merged; tested now on the head 35e881b (471 passed, 100 %, tests/TargetSyncOptOutTest.php with the real mongodb queue) | - | after final verification |
| [#46](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/46) Add Support for referenced relationships. | READY_FOR_MERGE_CLOSURE | yes | reproduced on 8aee971 (takeover, unknown id creates a document); fixed by #93, merged; tested now on the head 35e881b (471 passed, 100 %) | - | after final verification |
| [#47](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/47) Add option in order to enable/disable sync between collection on delete | READY_FOR_MERGE_CLOSURE | yes | #94 and #93 merged; persisted-state evidence (both modes, raw targets, events, boundary) and children retention tested now on the head 35e881b | - | after final verification |
| [#55](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/55) Throw Exception Missing Relationship in Request | READY_FOR_MERGE_CLOSURE | yes | tested now (full suite on the head 35e881b) | - | after final verification |
| [#82](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/82) Upgrade PHP 8.3 | POLICY_DECISION_REQUIRED | no | code inspected (composer.json requires ^8.4) | Maintainer disposition: superseded / not planned for the v4 support policy, or explicit approval to broaden support. | no |

Acceptance criteria, scope limits, paths and readiness levels are in the JSON file.
