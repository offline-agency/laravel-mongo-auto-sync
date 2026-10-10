# PR #40 Review Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task (chosen by the spec). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix review findings R1-R14 of PR #40 (v4.0 upgrade) on `fix/pr-40-review` and open a PR into `develop`.

**Architecture:** One task per spec phase, one conventional commit per task. Background sync is redesigned so that the queued job carries ids only, does removals before upserts in a single job per relation, and is idempotent; everything else is build/CI/docs hygiene.

**Tech Stack:** PHP 8.4, Laravel 11/12, mongodb/laravel-mongodb ^5.2, Orchestra Testbench 9/10, Pest 3, PHPStan (Larastan) level 8, Pint.

**Spec:** the "fix all findings of the PR #40 review" prompt (Phases 0-9, decisions D1-D7). This plan follows it phase by phase; deviations are listed in the last section.

## Global Constraints

- PHP 8.4.x locally (`php84` from Herd, wrapped as `php` in the session scratchpad); never `--ignore-platform-req`.
- PHPStan level stays 8; no `@phpstan-ignore` without a one-line justification; Pint rules unchanged.
- Conventional commits, one per phase, no `Co-Authored-By` / AI attribution of any kind.
- Never force-push, never rewrite `develop`/`master`, never commit `vendor/`.
- Decisions D1-D7 of the spec are binding (background sync opt-in, no composer.lock, Codecov only, tags without `v`, `SyncTargetJob` keeps name/namespace, `extracted_methods.php` under `docs/upgrade/`, `src/syncUtils.php` autoloaded).

## Review Focus

- Re-saving a source model whose embedded targets did not change must not create duplicate mini models on the target (Task 3, test 4 + idempotent `updateRelationWithSync`).
- A queued job executed after its source was deleted must only perform removals, never resurrect data (Task 3, `handle()` stops when `find()` returns null; covered by test 6 on the queue path).
- A target save performed by the job must not re-dispatch jobs for the target's own relations (ping-pong Navigation <-> SubItem); a static re-entrancy guard in the observer is exercised by test 8 (worker drains to an empty `jobs` collection).
- `ref_id` stored as `ObjectId` vs string (`sameId`, test 10).
- Unknown model passed to `mongo-sync:sync-collection` must exit non-zero (Task 8).

---

## Phase 0 baseline (recorded, no commit)

Environment: PHP 8.4.25 (Herd `php84`, ext-mongodb loaded), MongoDB 8.0 in Docker (`mongo-lmas`). Local PHP 8.4 has no usable coverage driver (Herd's pcov/xdebug are disabled, wrong architecture), so coverage runs in a `php:8.4-cli` + pcov + mongodb container against the same worktree and Mongo instance.

| Check | Result |
|---|---|
| `vendor/bin/pint --test` | exit 0 (76 files PASS) |
| `vendor/bin/phpstan analyse --memory-limit=1G` | exit 0 (`[OK] No errors`) |
| `vendor/bin/pest` | exit 2: **140 tests, 113 failed, 27 passed** (67 assertions) |
| Coverage (pcov, clover) | **29.74 %** lines (400/1345) — this is the floor |

**Hypothesis check (R5): refuted as the primary cause.** The dominant failures are "A facade root has not been set" / "Target class [config] does not exist": `tests/Pest.php` does `uses(TestCase::class, SyncHelpers::class)->in('tests')`, which Pest resolves relative to `tests/`, i.e. `tests/tests` — so no Pest test gets the Testbench `TestCase` at all. With that path fixed (measured, then reverted) the suite is still **96 failed / 44 passed**, grouped as:

- 36 × `Call to undefined method MongoDB\Database::collection()` (laravel-mongodb 5 removed `DB::collection()`, use `DB::table()`).
- 22 × `Undefined array key "id"` (embedded-relation tests written for the pre-5.x `_id` handling).
- 15 × undefined test helpers (`prepareArticleData`, `prepareUserData`, `createNavigation` on class-based tests): the helpers lived in `tests/SyncTestCase.php` at tag `3.1.1` and were dropped; class-based tests extend `TestCase`, which does not use `SyncHelpers`.
- 2 × `Tests\getAID()` / `Tests\getTypeOnTarget()` undefined (helper autoload, R4).
- 7 × assertion failures, 5 × `InputBag::set()` stdClass, 3 × "Check target" null (`StoreWIthSyncTest`/`UpdateWithSyncTest`: these are the R5 symptom — jobs land in `jobs` and never run).

So Phase 2 has to repair the test harness (bootstrap path, helpers, laravel-mongodb 5 API) in addition to switching the queue to `sync`.

---

### Task 1: dependencies and autoload (R2, R3, R4, R14)

**Files:** Modify `composer.json`, `.gitignore`; remove `composer.lock` from the index.

- [ ] Apply the spec diff: `mongodb/laravel-mongodb ^5.2`, `orchestra/testbench ^9.0|^10.0`, `autoload.files = ["src/syncUtils.php"]` (and drop it from `autoload-dev`), add `test:coverage` and `lint:check` scripts.
- [ ] `git rm --cached composer.lock`; append `composer.lock` and `coverage.xml` to `.gitignore`.
- [ ] `composer update && composer validate --strict`; `php -r "require 'vendor/autoload.php'; var_dump(function_exists('ml'), function_exists('getTranslatedContent'));"` → `true true`.
- [ ] Laravel 12 resolution: back up `composer.json` to the scratchpad, `composer require "illuminate/support:12.*" "orchestra/testbench:10.*" --no-update && composer update`, run pest (record numbers, harness still broken until Task 2), restore `composer.json`, `composer update`.
- [ ] Verify `function_exists('ml')` with `composer install --no-dev` in a scratch copy.
- [ ] Commit `build: fix dependency constraints for Laravel 12 and restore helper autoload`.

### Task 2: test harness and safe default (R5, D1)

**Files:** Modify `config/laravel-mongo-auto-sync.php`, `tests/TestCase.php`, `tests/Pest.php`, `tests/SyncHelpers.php`, failing test files (API updates only, no deletions/skips).

- [ ] Config: `queue_connection`/`queue_name` default `null`, `use_background_sync` `(bool) env(..., false)`, new `logging` `(bool) env('MONGO_AUTO_SYNC_LOGGING', false)`.
- [ ] `TestCase::getEnvironmentSetUp()`: `queue.default = sync`, named `queue.connections.mongodb` (`driver mongodb`, `connection mongodb`, `table jobs`).
- [ ] `tests/Pest.php`: `uses(TestCase::class)->in(__DIR__)`; `TestCase` itself `use SyncHelpers;` so class-based tests get the helpers too.
- [ ] Restore missing helpers from `3.1.1:tests/SyncTestCase.php` into `SyncHelpers` (`createArticles`, `createUsers`, `createCategory`, `createPermission`, `createRole`, `prepareArticleData`, `prepareUserData`, `prepareArticleDataWithTwoCategories`, `getMini*`, `prepareSingleMini*`).
- [ ] Systematic debugging per remaining group (`DB::collection` → `DB::table`, `id` alias, etc.) until green. Root-cause fixes in `src/` if a test exposes a real bug.
- [ ] Full suite, PHPStan, Pint green. Commit `test: run the suite on the sync queue driver and make background sync opt-in`.

### Task 3: background sync correctness (R6, R7, R9) — TDD

**Files:** Create `tests/BackgroundSyncTest.php`; modify `src/Jobs/SyncTargetJob.php`, `src/Observers/MongoAutoSyncObserver.php`, `src/Helpers/SyncHelper.php`, `src/Traits/RelationshipMongoTrait.php`, `src/Console/SyncCollection.php`, `src/Console/AnalyseDatabase.php`.

**Interfaces produced:**
- `SyncHelper::refIds(mixed $value): list<string>` and `SyncHelper::sameId(mixed $a, mixed $b): bool`.
- `SyncTargetJob::__construct(string $sourceClass, string $sourceId, string $method, array $relation, array $currentTargetIds, array $removedTargetIds = [], array $requestData = [], array $targetAdditionalData = [])` (public readonly props).
- `SyncTargetJob::dispatchFor(MDModel $source, string $method, array $relation, array $currentTargetIds, array $removedTargetIds): PendingDispatch` applying `queue_connection`/`queue_name` when non-null.
- `MongoAutoSyncObserver::$syncing` static guard set while a job's `handle()` runs.

- [ ] Write the 10 tests from the spec (beforeEach enables background sync), run, confirm each fails for the expected reason (record output).
- [ ] Implement `refIds`/`sameId`, new job, new observer `saved`/`deleted`, idempotent `updateRelationWithSync`, `sameId` in `batchHandleSubTarget`, callers in console commands.
- [ ] 10 tests pass, full suite, PHPStan, Pint green. Commit `fix(sync): make background sync idempotent, serialisable and ordered`.

### Task 4: logging (R10)

- [ ] Failing Pest test (`Log::shouldReceive('debug')->once()` with flag on, `->never()` off) in `tests/LoggingTest.php`.
- [ ] `Log::info` → `Log::debug` guarded by `config('laravel-mongo-auto-sync.logging')` in `storeWithSync`/`updateWithSync`.
- [ ] Commit `fix: make sync logging opt-in and use debug level`.

### Task 5: repository hygiene (R13)

- [ ] `git rm --cached .phpunit.result.cache`; `git rm .codacy.yml phpcs.xml`.
- [ ] Dockerfile `ARG PHP_VERSION=8.4`, `ARG COMPOSER_VERSION=2`, drop `pdo_mysql` if unused (grep first); docker-compose without `version:`, `mongo:8.0`, `MONGO_HOST=mongodb`; `.gitignore` `.phpunit.cache/`.
- [ ] Commit `chore: remove legacy CI leftovers and update local Docker setup`.

### Task 6: CI and release workflows (R1)

- [ ] Write `.github/workflows/build-ci.yml` and `.github/workflows/release.yml` verbatim from the spec; YAML parse check; actionlint if installed.
- [ ] Commit `ci: rebuild matrix for PHP 8.4/8.5 x Laravel 11/12 x Mongo 7/8 and add automatic releases`.

### Task 7: docs (R11, R12)

- [ ] README fixes, `CHANGELOG.md` `[4.0.0] - Unreleased`, `UPGRADE-4.0.md`, `docs/upgrade/extracted_methods.php` from `06fd044^`, `docs/pr/pr-40-body.md`, this plan file.
- [ ] `gh pr edit 40 --body-file docs/pr/pr-40-body.md` if `gh auth status` succeeds.
- [ ] Commit `docs: add changelog and upgrade guide, fix README for v4.0`.

### Task 8: console commands and exceptions tests (R8) — TDD on existing behaviour

- [ ] `tests/Console/{CheckModelConfig,AnalyseDatabase,SyncCollection,BenchmarkCommand}Test.php`, `tests/ExceptionsTest.php`, `tests/BrokenModels/`.
- [ ] Commit `test: cover console commands and custom exceptions`.

### Task 9: final verification and PR

- [ ] Pint, PHPStan, Pest (Laravel 11 and 12 resolutions), coverage ≥ 29.74 %, `composer validate --strict`, attribution grep.
- [ ] `docs/pr/fix-branch-body.md` (R1-R14 → commit, D1-D7), push over HTTPS, `gh pr create --base develop`.

## Deviations log

- Laravel 11 dropped and Laravel 13 added (owner instruction during execution): constraints `^12.8|^13.0`, Testbench `^10.0|^11.0`, Pest `^3.7|^4.0`; CI matrix Laravel 12/13.
- Phase 2 needed far more than the queue switch: the Pest bootstrap path, the fixture helpers, the laravel-mongodb 5 API in the tests, and eight runtime regressions in `src/` (separate `fix:` commit).
- Tests for the business methods extracted in 4.0 and for `getAID()` / `getTypeOnTarget()` were removed (owner decision).
- Phase 3: the job also reloads the source and only upserts targets it still references; an 11th test covers the observer re-entrancy guard.
- Phase 8 ran before Phase 7 so the documentation covers its fixes (`check-config` / `sync-collection` exit codes, broken `mongo-sync:benchmark`).
- Coverage is measured in a `php:8.4-cli` + pcov container: the local PHP 8.4 has no coverage driver.
