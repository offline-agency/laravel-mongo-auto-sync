# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] - Unreleased

See [UPGRADE-4.0.md](UPGRADE-4.0.md) for the migration guide.

### Added
- Support for Laravel 12 and 13 on PHP 8.4+ with `mongodb/laravel-mongodb` 5.x.
- Background synchronisation of target collections through `MongoAutoSyncObserver` and the queued `SyncTargetJob` (opt-in, `use_background_sync`).
- `queue_connection`, `queue_name` and `logging` configuration keys (`MONGO_AUTO_SYNC_QUEUE_CONNECTION`, `MONGO_AUTO_SYNC_QUEUE_NAME`, `MONGO_AUTO_SYNC_LOGGING`).
- `SyncTargetJob::dispatchFor()`, `SyncHelper::refIds()` and `SyncHelper::sameId()`.
- `removeRelationWithSync()` on synced models (target-side removal of a mini model).
- Console commands `mongo-sync:check-config`, `mongo-sync:analyse [--fix]`, `mongo-sync:sync-collection {model}` and `mongo-sync:benchmark {model} {count=100}`.
- Dedicated exceptions extending `MongoAutoSyncException`: `InvalidConfigurationException`, `InvalidRelationshipException`, `InvalidRequestException`, `ModelNotFoundException`.
- Sync of `HasOne` / `HasMany` referenced relationships (not covered by tests yet).
- The configuration can be published with `vendor:publish --tag=config`.
- GitHub Actions matrix (PHP 8.4/8.5 x Laravel 12/13 x MongoDB 7.0/8.0), Pint, PHPStan level 8, Codecov, and an automatic release workflow based on conventional commits.

### Changed
- Driver switched from `jenssegers/mongodb` to `mongodb/laravel-mongodb` ^5.2: namespaces moved from `Jenssegers\Mongodb` to `MongoDB\Laravel`.
- Background sync is **opt-in** (`use_background_sync` defaults to `false`): with it enabled, targets are eventually consistent and a queue worker is required.
- `SyncTargetJob` keeps its class name and namespace but has a new constructor (ids only, no serialised models) and is `final`.
- Each save dispatches one job per relationship with a target; the job performs removals before upserts and reloads the source, so it is idempotent and safe to run out of order.
- `updateRelationWithSync()` replaces an existing entry with the same `ref_id` instead of appending a duplicate.
- `storeWithSync()` / `updateWithSync()` log at `debug` level, and only when `logging` is enabled.
- Stricter parameter and return types across the helpers and traits.
- `stdClass` values passed as additional data are stored as arrays (Symfony's `InputBag` rejects objects).
- `mongo-sync:check-config` exits with code 1 when a configuration is invalid; `mongo-sync:sync-collection` exits with code 1 for an unknown model.
- The test suite runs on Pest with Orchestra Testbench.

### Deprecated
- `RelationshipMongoTrait::handleSubTarget()`: use `batchHandleSubTarget()`.

### Removed
- Support for PHP < 8.4 and Laravel < 12.8.
- Application-specific methods, now in [`docs/upgrade/extracted_methods.php`](docs/upgrade/extracted_methods.php) for copy into your project: `MongoCollection::getBySlugAndStatus()`, `getBySlug()`, `getNotDeleted()`, `getPublished()`, `getPublic()`, `getActive()`, `findByAID()`, `hasPermission()`, `hasRole()`, `checkPermission()`; `ModelAdditionalMethod::getPageMetaTag()`; the global helpers `getAID()`, `getPrimaryRequest()` and `processList()`.
- The global helper `getTypeOnTarget()` (use `Arr::get($relation, 'typeOnTarget', 'EmbedsMany')`).
- Scrutinizer, StyleCI, Codacy, Coveralls and `phpcs.xml` configurations; `composer.lock` is no longer committed.

### Fixed
- Global helpers (`ml()`, `cl()`, `isML()`, `getTranslatedContent()`, ...) are autoloaded again for consumers (they were only in `autoload-dev`).
- Fatal error with recent laravel-mongodb 5.x releases: `embedsMany()` / `embedsOne()` are declared public.
- `LogicException` on Laravel 13 when the observer was registered while the model was booting.
- Background sync was a silent no-op on real queue drivers (embedded models were restored from a non-existent collection).
- Background sync could delete a reference just written when two workers ran the delete and save jobs out of order.
- `ref_id` comparisons failed between `ObjectId` and string values.
- Synchronous target sync failed on every target with `InvalidRequestException` (level > 1 sync used a malformed request key; it is a TODO again).
- `getTranslatedContent()` returned an empty string for locales other than `en_EN` / `it_IT`.
- `getCounterForRelationships()` returned no counter for nested `EmbedsMany` items.
- Partial requests wrapped the whole `EmbedsOne` mini model in `ref_id`.
- An empty `EmbedsOne` relation returned an empty model instead of `null`.
- Embedded models did not get a generated key (the 5.x key name is `id`) and string ids were not converted to `ObjectId`.
- `mongo-sync:benchmark` always crashed (it imported a non-existent facade).

## [3.1.1] and earlier

See the [GitHub releases](https://github.com/offline-agency/laravel-mongo-auto-sync/releases).
