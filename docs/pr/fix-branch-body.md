## Summary

Fixes the findings of the PR #40 review on top of `develop`, so that PR #40 (`develop` -> `master`) inherits them. On top of the review items, it drops Laravel 11 (out of security support, every 11.x release is blocked by Composer's advisory policy) and adds Laravel 13.

Once the test suite was actually running, it exposed several runtime regressions introduced by the 4.0 refactor; they are fixed in `aef0cb7`, `36af79c` and `e2d1720`.

## Review findings -> commits

| Finding | Area | Commit(s) |
| --- | --- | --- |
| R1 | CI did not test the supported matrix and was red | `c0b7c48` |
| R2 | `mongodb/laravel-mongodb` constraint too low for Laravel 12+ | `d09c3d8`, `e36cbc5` |
| R3 | Orchestra Testbench constraint excluded Laravel 12+ | `d09c3d8`, `e36cbc5` |
| R4 | `src/syncUtils.php` helpers only in `autoload-dev` | `d09c3d8` |
| R5 | Test harness: suite never booted Testbench, jobs queued on a non-running driver | `a811a10` (+ regressions it uncovered: `aef0cb7`, `36af79c`) |
| R6 | `SyncTargetJob` serialised embedded models: silent no-op on real drivers | `c5a7330` |
| R7 | Two independent jobs per save (delete + save) could run out of order | `c5a7330` |
| R8 | No tests for console commands and exceptions | `644b3b4` (+ fixes it uncovered: `e2d1720`) |
| R9 | `ref_id` strict comparison (ObjectId vs string), duplicated mini models, queue config ignored | `c5a7330` |
| R10 | Unconditional `info` logging | `3b0cf91` |
| R11 | PR body claimed recursive syncing; nested sync call was broken | `aef0cb7` (call removed, TODO restored), `6a5ad0a` (docs) |
| R12 | README / changelog / upgrade guide missing or wrong | `6a5ad0a` |
| R13 | Legacy files tracked (`.phpunit.result.cache`, `.codacy.yml`, `phpcs.xml`), outdated Docker setup | `fc26ec0` |
| R14 | `composer.lock` committed for a library; missing composer scripts | `d09c3d8` |

Other commits: `c0ab993` (Pint preset of the current Pint release), `5fc65bc` (deprecated faker properties in tests).

## Decisions

| ID | Decision |
| --- | --- |
| D1 | `use_background_sync` defaults to `false` in 4.0.0 (opt-in). |
| D2 | `composer.lock` is removed from the repository. |
| D3 | Coveralls dropped; Codecov kept (upload from one matrix cell). |
| D4 | Release tags keep the format without `v` prefix (`4.0.0`). |
| D5 | `SyncTargetJob` keeps name and namespace, new constructor documented in `UPGRADE-4.0.md`. |
| D6 | `extracted_methods.php` restored under `docs/upgrade/`, not autoloaded. |
| D7 | The helpers in `src/syncUtils.php` stay public API and are autoloaded again. |

Additional decisions taken during the work:

- Supported versions: PHP ^8.4, Laravel ^12.8 | ^13.0 (Laravel 11 dropped; ^12.8 because the observer is registered with `whenBooted()`).
- Tests for the business methods extracted in 4.0 (`getBySlug*`, `getNotDeleted`, `getPublished`, `getPublic`, `getActive`, `findByAID`, `hasPermission`, `hasRole`, `checkPermission`, `getAID()`) and for the removed `getTypeOnTarget()` helper are deleted; the removals are documented in `UPGRADE-4.0.md`.
- `SyncTargetJob` reloads the source and only upserts targets the source still references, so stale or out-of-order jobs cannot re-add removed references.

## Verification (PHP 8.4.25, MongoDB 8.0)

| Check | Laravel 13.35 / Testbench 11.3 | Laravel 12.69 / Testbench 10.12 |
| --- | --- | --- |
| `vendor/bin/pest` | 155 passed (600 assertions) | 155 passed (600 assertions) |
| `vendor/bin/phpstan analyse` (level 8) | OK | OK |
| `vendor/bin/pint --test` | OK | – |

- Coverage (pcov, PHP 8.4): 81.68 % lines, up from 29.74 % on `develop` (where 113 of 140 tests failed).
- `composer validate --strict`: valid. `function_exists('ml')` is `true` with `composer install --no-dev`.
- Workflows: parsed as YAML, `actionlint` clean; the release version script computes `3.1.1` -> `4.0.0` on this branch.

## Notes

- `release.yml` only becomes active once it is on `master` (`workflow_run` is read from the default branch).
- Tags pushed by the release bot do not re-trigger CI (intended: the commit was already tested).
- Packagist picks up tags through the GitHub hook, or through the optional `PACKAGIST_USERNAME` / `PACKAGIST_TOKEN` secrets.
- PHP 8.5 cells use `continue-on-error` until they are stable.
- The corrected PR #40 description is in `docs/pr/pr-40-body.md`.
