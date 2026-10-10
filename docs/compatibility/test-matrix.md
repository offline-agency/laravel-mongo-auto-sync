# Test matrix

Results of the verification runs for branch `fix/upstream-compatibility` (A). Every profile ran in an isolated copy of the branch with its own MongoDB database; the resolved lock file, logs, exit codes, PHP/extension versions and MongoDB server version/topology of each profile are kept as verification artifacts outside the repository (no `composer.lock` is committed).

Each profile ran: `composer validate --strict`, `composer audit`, `composer check-platform-reqs`, `vendor/bin/pest`, `vendor/bin/phpstan analyse --memory-limit=1G`, `vendor/bin/pint --test`, `git diff --check` (against `fix/pr-40-review`).

## Executed (local, 2026-10-10, branch A at `0c1bb44`)

| Profile | PHP / ext-mongodb | Laravel | laravel-mongodb | mongodb/mongodb | Testbench / Pest | MongoDB server | Pest | Static checks |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| REFERENCE L13 | 8.4.25 / 2.5.2 | 13.35.0 | 5.11.0 (exact) | 2.5.0 (exact) | 11.3.0 / 4.7.8 | 8.0.32 standalone | 332 passed, 3 skipped (1120 assertions) | all exit 0 |
| REFERENCE L12 | 8.4.25 / 2.5.2 | 12.69.3 | 5.11.0 (exact) | 2.5.0 (exact) | 10.12.0 / 4.7.8 | 8.0.32 standalone | 332 passed, 3 skipped | all exit 0 |
| LATEST-COMPATIBLE PHP 8.4 | 8.4.25 / 2.5.2 | 13.35.0 | 5.11.0 | 2.5.0 | 11.3.0 / 4.7.8 | 8.0.32 standalone | 332 passed, 3 skipped | all exit 0 |
| LATEST-COMPATIBLE PHP 8.5 | 8.5.10 / 2.5.2 | 13.35.0 | 5.11.0 | 2.5.0 | 11.3.0 / 4.7.8 | 8.0.32 standalone | 332 passed, 3 skipped | all exit 0 |
| LATEST-COMPATIBLE MongoDB 7.0 | 8.4.25 / 2.5.2 | 13.35.0 | 5.11.0 | 2.5.0 | 11.3.0 / 4.7.8 | 7.0.43 standalone | 332 passed, 3 skipped | all exit 0 |
| Replica set (transactions) | 8.4.25 / 2.5.2 | 13.35.0 | 5.11.0 | 2.5.0 | 11.3.0 / 4.7.8 | 8.0.32 replica set rs0 (1 node) | **335 passed, 0 skipped** (1127 assertions) | all exit 0 |
| LOWEST-SAFE L12 | 8.4.26 / **2.4.0** | **12.69.0** | 5.11.0 | **2.4.2** | 10.0.0 / 3.8.5 | 8.0.32 standalone | 332 passed, 3 skipped | Pest/validate/audit/platform exit 0; PHPStan and Pint exit 1 (*) |
| LOWEST-SAFE L13 | 8.4.26 / **2.4.0** | **13.30.0** | 5.11.0 | **2.4.2** | 11.0.0 / 4.4.1 | 8.0.32 standalone | 332 passed, 3 skipped | as above (*) |
| Consumer (no dev dependencies) | 8.4.25 / 2.5.2 | 13.35.0 (laravel/laravel skeleton) | 5.11.0 | 2.5.0 | none installed | 8.0.32 standalone | `consumer:check` 8/8 (package `src/` identical to `0c1bb44`): helpers, provider commands, model + query, sync path, MongoDB queue + worker, queue drained, no failed jobs, failed-job recording control | n/a |

The 3 skipped tests are the transaction tests (`tests/Compatibility/TransactionTest.php`), which need a replica set; they run in the replica set profile and in the CI `replica-set` job.

(*) With `--prefer-lowest` the dev tools and the framework resolve to their lowest versions (Pint 1.0.0; PHPStan 2.1.3 / Larastan 3.1.0 with Laravel 12.69.0; PHPStan 2.1.32 / Larastan 3.9.3 with Laravel 13.30.0). Pint 1.0.0 applies an older preset. PHPStan reports type mismatches in `src/` against the lowest framework stubs: `Relationships/EmbedsMany.php` (paginator generics of Laravel 12.69.0) and `Traits/ModelAdditionalMethod.php` (`raw()` closure parameter with Laravel 13.30.0). The runtime suite is green on these versions; static analysis is certified on the REFERENCE and LATEST profiles only.

Coverage (pcov, PHP 8.4, replica set, branch A at `0c1bb44`): **100.00 %** of lines (1342/1342), 335 tests passed (1127 assertions).

## CI (`.github/workflows/build-ci.yml`)

| Job | Cells | Blocking |
| --- | --- | --- |
| `lint` (Pint), `static-analysis` (PHPStan) | PHP 8.4, latest | yes |
| `tests` | PHP 8.4/8.5 x Laravel 12/13 x MongoDB 7.0/8.0 (8 cells), coverage on PHP 8.4 / L13 / 8.0 | yes (no `continue-on-error`) |
| `reference` | laravel-mongodb 5.11.0 + mongodb/mongodb 2.5.0 on Laravel 12 and 13 | yes |
| `lowest` | ext-mongodb 2.4.0 (checked), `--prefer-lowest`, Laravel 12 and 13, Pest only | yes |
| `replica-set` | single-node rs0 on MongoDB 8.0; fails when the transaction tests are skipped or do not pass | yes |

## Not executed locally (CI only, pending the first run of the pull request)

| Cell | Status |
| --- | --- |
| PHP 8.4 x Laravel 12 x MongoDB 7.0 | CI `tests` |
| PHP 8.5 x Laravel 12 (MongoDB 7.0 and 8.0), PHP 8.5 x Laravel 13 x MongoDB 7.0 | CI `tests` |

## Not executed anywhere (no support is claimed from these cells)

| Cell | Reason |
| --- | --- |
| Transactions on Laravel 12, on PHP 8.5, or on the lowest-safe stack | replica set runs use PHP 8.4 / Laravel 13 latest only |
| LOWEST-SAFE with PHP 8.5 or MongoDB 7.0 | not run |
| ext-mongodb other than 2.4.0 and 2.5.2 (for example 2.5.4, the current PECL release) | not run |
| Replica set with MongoDB 7.0, multi-node replica set, sharded cluster | not run |
| `mongodb/mongodb` 1.21 / `ext-mongodb` 1.21 | not supported by this package |
| PHP 8.3 and lower | not supported (issue #82) |
| Consumer on Laravel 12 | not run (consumer verified on Laravel 13 only) |

Branches B (#55) and C (#43), separately and combined, are verified in their pull requests.
