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
| R6 | `SyncTargetJob` serialised embedded models: silent no-op on real drivers | `c5a7330`, `bf13709` |
| R7 | Two independent jobs per save (delete + save) could run out of order | `c5a7330`, `bf13709` |
| R8 | No tests for console commands and exceptions | `644b3b4` (+ fixes it uncovered: `e2d1720`) |
| R9 | `ref_id` strict comparison (ObjectId vs string), duplicated mini models, queue config ignored | `c5a7330`, `bf13709` |
| R10 | Unconditional `info` logging | `3b0cf91` |
| R11 | PR body claimed recursive syncing; nested sync call was broken | `aef0cb7` (call removed, TODO restored), `6a5ad0a`, `d753705` (docs) |
| R12 | README / changelog / upgrade guide missing or wrong | `6a5ad0a`, `d753705` |
| R13 | Legacy files tracked (`.phpunit.result.cache`, `.codacy.yml`, `phpcs.xml`), outdated Docker setup | `fc26ec0` |
| R14 | `composer.lock` committed for a library; missing composer scripts | `d09c3d8` |

Raising coverage to 100 % uncovered more bugs, fixed test-first in `5817cd3` (`chunkById()`, embedded `increment()` with an expression, `EmbedsOne` eager loading), `bee0ba9` (console commands could not find models in subdirectories; exit codes) and `3ecee63` (`getRandomRow()` returned empty models); `4b3fb57` adds tests only.

Other commits: `c0ab993` (Pint preset of the current Pint release), `5fc65bc` (deprecated faker properties in tests).

A fresh review of the whole branch found further issues, fixed test-first in `bf13709` / `d753705`: the job no longer carries the request input (stale values, wrong locale on multilingual fields, posted secrets in the queue payload), target writes are atomic (`$pull` / `$push` / `$set`, no lost entries with concurrent workers), `destroyWithSync(false)` no longer syncs in background mode, and a partial update with an empty `EmbedsOne` target relation no longer crashes.

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
- `SyncTargetJob` reloads the source, only upserts targets the source still references and builds the mini model from the stored document, so stale or out-of-order jobs cannot re-add removed references or write old values.
- Target writes bypass Eloquent (atomic updates): no model events fire on the target models for them (documented in `UPGRADE-4.0.md`).

## Verification (PHP 8.4.25, MongoDB 8.0)

| Check | Laravel 13.35 / Testbench 11.3 | Laravel 12.69 / Testbench 10.12 |
| --- | --- | --- |
| `vendor/bin/pest` | 262 passed (879 assertions) | 262 passed (879 assertions) |
| `vendor/bin/phpstan analyse` (level 8) | OK | OK |
| `vendor/bin/pint --test` | OK | – |

- Coverage (pcov, PHP 8.4): **100 % lines** (1353/1353), up from 29.74 % on `develop` (where 113 of 140 tests failed).
- `composer validate --strict`: valid. `function_exists('ml')` is `true` with `composer install --no-dev`.
- Workflows: parsed as YAML, `actionlint` clean; the release version script computes `3.1.1` -> `4.0.0` on this branch.

## Notes

- `release.yml` only becomes active once it is on `master` (`workflow_run` is read from the default branch).
- Tags pushed by the release bot do not re-trigger CI (intended: the commit was already tested).
- Packagist picks up tags through the GitHub hook, or through the optional `PACKAGIST_USERNAME` / `PACKAGIST_TOKEN` secrets.
- PHP 8.5 cells use `continue-on-error` until they are stable.
- The corrected PR #40 description is in `docs/pr/pr-40-body.md`.

## Known follow-ups (not in this PR)

- Each `storeWithSync()` / `updateWithSync()` can dispatch 2-3 idempotent jobs per relationship (one per internal save).
- `mongo-sync:sync-collection` also dispatches no-op jobs for documents with empty relations.
- CI does not test the lowest allowed versions (`--prefer-lowest`).
- A missing request key on a relationship *with* target is not reported as `InvalidRequestException` (same as 3.x).
- `release.yml` manual runs are not restricted to `master`.
- `HasOne` / `HasMany` children saved by `storeWithSync()` (which requires `'has-target' => false`) are not deleted by `destroyWithSync()` (which only deletes them when the relation has a target).
