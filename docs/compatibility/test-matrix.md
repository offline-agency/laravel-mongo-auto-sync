# Test matrix

Results of the verification runs for branch `fix/upstream-compatibility` (A). Every profile ran in an isolated copy of the branch with its own MongoDB database; resolved lock files and logs are kept as verification artifacts outside the repository (no `composer.lock` is committed).

Each profile ran: `composer validate --strict`, `composer audit`, `composer check-platform-reqs`, `vendor/bin/pest`, `vendor/bin/phpstan analyse --memory-limit=1G`, `vendor/bin/pint --test`, `git diff --check` (against `fix/pr-40-review`).

## Executed (local, 2026-10-10, branch A at `6a69b77`)

| Profile | PHP / ext-mongodb | Laravel | laravel-mongodb | mongodb/mongodb | Testbench / Pest | MongoDB server | Pest | Static checks |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| REFERENCE L13 | 8.4.25 / 2.5.2 | 13.35.0 | 5.11.0 (exact) | 2.5.0 (exact) | 11.3.0 / 4.7.8 | 8.0.32 standalone | 308 passed, 3 skipped (1066 assertions) | all exit 0 |
| REFERENCE L12 | 8.4.25 / 2.5.2 | 12.69.3 | 5.11.0 (exact) | 2.5.0 (exact) | 10.12.0 / 4.7.8 | 8.0.32 standalone | 308 passed, 3 skipped | all exit 0 |
| LATEST-COMPATIBLE PHP 8.4 | 8.4.25 / 2.5.2 | 13.35.0 | 5.11.0 | 2.5.0 | 11.3.0 / 4.7.8 | 8.0.32 standalone | 308 passed, 3 skipped | all exit 0 |
| LATEST-COMPATIBLE PHP 8.5 | 8.5.10 / 2.5.2 | 13.35.0 | 5.11.0 | 2.5.0 | 11.3.0 / 4.7.8 | 8.0.32 standalone | 308 passed, 3 skipped | all exit 0 |
| LATEST-COMPATIBLE MongoDB 7.0 | 8.4.25 / 2.5.2 | 13.35.0 | 5.11.0 | 2.5.0 | 11.3.0 / 4.7.8 | 7.0.43 standalone | 308 passed, 3 skipped | all exit 0 |
| Replica set (transactions) | 8.4.25 / 2.5.2 | 13.35.0 | 5.11.0 | 2.5.0 | 11.3.0 / 4.7.8 | 8.0.32 replica set rs0 (1 node) | **311 passed, 0 skipped** (1073 assertions) | all exit 0 |
| LOWEST-SAFE L12 | 8.4.26 / **2.4.0** | **12.69.0** | 5.11.0 | **2.4.2** | 10.0.0 / 3.8.5 | 8.0.32 standalone | 308 passed, 3 skipped | Pest/validate/audit/platform exit 0; PHPStan and Pint not applicable (*) |
| LOWEST-SAFE L13 | 8.4.26 / **2.4.0** | **13.30.0** | 5.11.0 | **2.4.2** | 11.0.0 / 4.4.1 | 8.0.32 standalone | 308 passed, 3 skipped | as above (*) |
| Consumer (no dev dependencies) | 8.4.25 / 2.5.2 | 13.35.0 (laravel/laravel skeleton) | 5.11.0 | 2.5.0 | none installed | 8.0.32 standalone | `consumer:check` 8/8: helpers, provider commands, model + query, sync path, MongoDB queue + worker, queue drained, no failed jobs, failed-job recording control | n/a |

The 3 skipped tests are the transaction tests (`tests/Compatibility/TransactionTest.php`), which need a replica set; they run in the replica set profile and in the CI `replica-set` job.

(*) With `--prefer-lowest` the dev tools resolve to their lowest versions (Pint 1.0.0, PHPStan 2.1.3 / Larastan 3.1.0 on L12; PHPStan 2.1.32 / Larastan 3.9.3 on L13), whose presets and type inference differ: their findings concern the test workspace, not the runtime. Static checks are certified on the REFERENCE and LATEST profiles.

Coverage (pcov, PHP 8.4, replica set, branch A at `768e00b`): **100.00 %** of lines (1327/1327), 318 tests passed (1084 assertions).

## CI (`.github/workflows/build-ci.yml`)

| Job | Cells | Blocking |
| --- | --- | --- |
| `lint` (Pint), `static-analysis` (PHPStan) | PHP 8.4, latest | yes |
| `tests` | PHP 8.4/8.5 x Laravel 12/13 x MongoDB 7.0/8.0 (8 cells), coverage on PHP 8.4 / L13 / 8.0 | yes (no `continue-on-error`) |
| `reference` | laravel-mongodb 5.11.0 + mongodb/mongodb 2.5.0 on Laravel 12 and 13 | yes |
| `lowest` | ext-mongodb 2.4.0, `--prefer-lowest`, Laravel 12 and 13 | yes |
| `replica-set` | single-node rs0, transaction tests must not be skipped | yes |

CI results for this branch are produced by the pull request; they are not part of this table until they run.

## Not executed (no support is claimed from these cells)

| Cell | Reason |
| --- | --- |
| PHP 8.5 with Laravel 12 locally | covered only by CI |
| PHP 8.5 / Laravel 12 / 13 with MongoDB 7.0 locally | covered only by CI |
| LOWEST-SAFE with PHP 8.5 or MongoDB 7.0 | not run anywhere |
| Replica set with MongoDB 7.0, multi-node replica set, sharded cluster | not run anywhere |
| `mongodb/mongodb` 1.21 / `ext-mongodb` 1.21 | not supported by this package |
| PHP 8.3 and lower | not supported (issue #82) |
| Consumer on Laravel 12 | not run (consumer verified on Laravel 13 only) |
| Branches B (#55) and C (#43) | verified separately and combined, see their pull requests |
