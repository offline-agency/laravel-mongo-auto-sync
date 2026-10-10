## Summary

This PR performs a major upgrade of the package to version 4.0, modernizing the entire stack and improving stability and developer experience. It supports **PHP 8.4+** and **Laravel 12/13**, and migrates the underlying driver to the official **`mongodb/laravel-mongodb`** 5.x. Laravel 12 and 13 are both resolved and tested in CI (PHP 8.4/8.5 x MongoDB 7.0/8.0).

The test suite runs on **Pest** with Orchestra Testbench, and the codebase passes PHPStan level 8 and Laravel Pint.

Architectural additions: an opt-in **Observer-based background synchronization** (`SyncTargetJob`), batch loading of targets and dedicated **exceptions**.

The full migration guide is in [UPGRADE-4.0.md](../../UPGRADE-4.0.md) and the list of changes in [CHANGELOG.md](../../CHANGELOG.md).

## ⚠️ Deprecations

- **`RelationshipMongoTrait::handleSubTarget()`**: use `batchHandleSubTarget()`.

- **Legacy CI/CD configurations**: Scrutinizer, StyleCI, Codacy and Coveralls are removed in favour of GitHub Actions, Laravel Pint, PHPStan and Codecov.

## Breaking Changes

- **PHP requirement:** PHP ^8.4.

- **Laravel requirement:** Laravel ^12.8 or ^13.0 (Laravel 11 is out of security support).

- **MongoDB driver:** `jenssegers/mongodb` is replaced by `mongodb/laravel-mongodb` ^5.2. Namespaces change from `Jenssegers\Mongodb` to `MongoDB\Laravel`; models must use `$table` instead of `$collection`, and the primary key attribute is `id`.

- **Removal of application-specific logic:** `getBySlugAndStatus`, `getBySlug`, `getNotDeleted`, `getPublished`, `getPublic`, `getActive`, `findByAID`, `hasPermission`, `hasRole`, `checkPermission`, `getPageMetaTag`, `getAID`, `getPrimaryRequest`, `processList` and the `getTypeOnTarget()` helper are removed from the package. Their last implementation is kept in [`docs/upgrade/extracted_methods.php`](../upgrade/extracted_methods.php) (not autoloaded) for projects that want to copy them.

- **Exceptions:** the package throws exceptions extending `MongoAutoSyncException` (`InvalidConfigurationException`, `InvalidRequestException`, `InvalidRelationshipException`, `ModelNotFoundException`) instead of generic `Exception`.

- **Strict types:** stricter parameter and return types on helpers and traits.

- **`SyncTargetJob`:** same class name and namespace, new constructor (ids only); jobs queued by a previous version must be drained before deploying.

- **Ids and target writes:** string ids assigned to `id` are converted to `ObjectId`, and target writes no longer fire Eloquent events on the target models.

- **Background synchronization is opt-in:** `use_background_sync` defaults to `false`, so targets keep being updated synchronously as in 3.x. When enabled, targets become eventually consistent and a queue worker is required.

## Key Changes

- 📦 **Dependencies:**

  - PHP `^8.4`, Laravel components `^12.8|^13.0`.

  - `mongodb/laravel-mongodb: ^5.2`, Orchestra Testbench `^10.0|^11.0`, Pest `^3.7|^4.0`.

  - `nunomaduro/termwind` for console output.

  - The global helpers in `src/syncUtils.php` (`ml()`, `cl()`, `getTranslatedContent()`, ...) are autoloaded for consumers.

  - `composer.lock` is no longer committed: every CI cell resolves its own dependencies.

- ⚡ **Performance & Architecture:**

  - **Background sync (Observer pattern, opt-in):** `MongoAutoSyncObserver` dispatches a `SyncTargetJob` per relationship with a target. The job carries ids only (no request data), removes stale references before upserting, rebuilds the mini model from the stored source, and is idempotent and safe to run out of order. `queue_connection` and `queue_name` are configurable.

  - **Atomic target writes:** targets are updated with `$pull` / `$push` / `$set`, so concurrent writers do not lose entries (no Eloquent events fire on the targets for these writes).

  - **Batch loading:** the background job and the target clean-up load target documents with a single `whereIn`.

  - Nested (level > 1) target synchronization is not implemented (on either path) and stays on the roadmap.

- 🛡️ **Robustness & DX:**

  - **Custom exceptions** in the `Exceptions` namespace.

  - **`mongo-sync:check-config`** validates model relationships and exits with code 1 on errors.

  - **Logging:** opt-in `debug` logging of `storeWithSync` / `updateWithSync` (`MONGO_AUTO_SYNC_LOGGING`).

  - **Config publishing** via `vendor:publish --tag=config`.

- ✨ **New Features:**

  - `mongo-sync:analyse [--fix]` detects relationship inconsistencies.

  - `mongo-sync:sync-collection {model}` dispatches the synchronization of a whole collection.

  - `mongo-sync:benchmark {model} {count}` measures CRUD performance.

  - Sync of `HasOne` / `HasMany` referenced relationships (not covered by tests yet).

- 🛠 **Refactoring & Cleanup:**

  - Application-specific logic moved to `docs/upgrade/extracted_methods.php`.

  - Removed legacy CI configs; Dockerfile and docker-compose updated to PHP 8.4 / MongoDB 8.0.

  - PHPStan/Larastan level 8 and Laravel Pint.

- ✅ **Testing:**

  - Pest + Orchestra Testbench; the suite runs on the `sync` queue driver, plus an end-to-end test on the real `mongodb` queue driver.

  - Tests for background sync, console commands, exceptions and logging.

- 🚀 **CI/CD:**

  - GitHub Actions matrix PHP 8.4/8.5 x Laravel 12/13 x MongoDB 7.0/8.0, Pint and PHPStan jobs, coverage on Codecov.

  - `release.yml`: after a green CI run on `master`, tags and publishes a GitHub release with the version computed from conventional commits (tags without `v` prefix, as `3.1.1`).

## Checklist

- [x] All tests pass locally on Laravel 12 and Laravel 13 (PHP 8.4).

- [x] Code style checked with Pint.

- [x] Static analysis passed with PHPStan level 8.

- [x] Documentation updated: README, CHANGELOG, UPGRADE-4.0.
