# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] - Unreleased

See [UPGRADE-4.0.md](UPGRADE-4.0.md) for the migration guide.

### Added
- Support for Laravel 12 and 13 on PHP 8.4+ with `mongodb/laravel-mongodb` 5.x.
- `docs/compatibility/`: map of the package against laravel-mongodb 5.11.0, source lock, test matrix, issue triage and roadmap; `tests/Compatibility/` compares package models with native laravel-mongodb models; CI jobs for the exact reference versions, the lowest safe versions and a replica set (transactions).
- Background synchronisation of target collections through `MongoAutoSyncObserver` and the queued `SyncTargetJob` (opt-in, `use_background_sync`).
- `queue_connection`, `queue_name` and `logging` configuration keys (`MONGO_AUTO_SYNC_QUEUE_CONNECTION`, `MONGO_AUTO_SYNC_QUEUE_NAME`, `MONGO_AUTO_SYNC_LOGGING`).
- `SyncTargetJob::dispatchFor()`, `SyncHelper::refIds()`, `SyncHelper::sameId()` and `SyncHelper::idVariants()`.
- `removeRelationWithSync()` on synced models (target-side removal of a mini model).
- Per-operation target-sync opt-out: the `sync_targets` option of `storeWithSync()` / `updateWithSync()` and `withoutTargetSync()` for plain saves, scoped to the model instance ([#45](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/45)); `destroyWithSync(false)` uses the same scope ([#47](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/47)).
- Console commands `mongo-sync:check-config`, `mongo-sync:analyse [--fix]`, `mongo-sync:sync-collection {model}` and `mongo-sync:benchmark {model} {count=100}`.
- Dedicated exceptions extending `MongoAutoSyncException`: `InvalidConfigurationException`, `InvalidRelationshipException`, `InvalidRequestException`, `ModelNotFoundException`.
- Sync of `HasOne` / `HasMany` referenced relationships (not covered by tests yet).
- The configuration can be published with `vendor:publish --tag=config`.
- GitHub Actions matrix (PHP 8.4/8.5 x Laravel 12/13 x MongoDB 7.0/8.0), Pint, PHPStan at the maximum level (no baseline), Codecov, and an automatic release workflow based on conventional commits.

### Changed
- `storeWithSync()` / `updateWithSync()` with a full request require every top-level relationship key (after merging `additionalData`): a missing key throws `InvalidRequestException` before the top-level model, its targets or any job are written or dispatched (referenced children are still validated when they are saved), with or without target ([#55](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/55)). An explicit `null`, `""` or `"[]"` counts as present (empty relationship). Partial requests (`request_type => partial`) are unchanged.
- Driver switched from `jenssegers/mongodb` to `mongodb/laravel-mongodb` ^5.11 (`laravel/framework` ^12.69 | ^13.30 and `ext-mongodb` ^2.4 required directly, `mongodb/mongodb` < 2.4.2 declared as a conflict): namespaces moved from `Jenssegers\Mongodb` to `MongoDB\Laravel`.
- Background sync is **opt-in** (`use_background_sync` defaults to `false`): with it enabled, targets are eventually consistent and a queue worker is required.
- `SyncTargetJob` keeps its class name and namespace but has a new constructor (ids only, no serialised models) and is `final`.
- Each `storeWithSync()` / `updateWithSync()` operation dispatches at most one job per relationship with a target, carrying the destinations of the final stored state and every destination left during the operation (`withCoalescedTargetSync()` groups other saves the same way; a plain `save()` outside a scope dispatches per save); the job carries ids only, performs removals before upserts and rebuilds the mini model from the stored source, so it is idempotent and the jobs of one source can run out of order (two workers syncing the same source at the same moment can still leave older values on a target).
- Target collections are written with atomic MongoDB updates (`$pull` / `$push` / `$set`) through the laravel-mongodb query builder: writes of different sources no longer lose entries, they join the connection's transaction, and no Eloquent events fire on the target models for these writes.
- `Eloquent\Model` delegates attribute handling to laravel-mongodb (`DocumentModel`) and `Eloquent\Builder` delegates `raw()`, `update()`, `insert()`, `insertGetId()`, `delete()` and the `updated_at` handling: `raw()` returns `id` and Carbon dates, decimals are stored as `Decimal128`.
- `updateRelationWithSync()` replaces an existing entry with the same `ref_id` instead of appending a duplicate.
- `storeWithSync()` / `updateWithSync()` log at `debug` level, and only when `logging` is enabled.
- Stricter parameter and return types across the helpers and traits (PHPStan `max`). Inputs that used to raise a PHP error or warning now raise a package exception:
  - a relationship payload that decodes to a scalar (`"5"`), or whose items are not JSON objects or `null` (`["a"]`): `InvalidRequestException`, raised before the source is written. **Breaking:** `"0"` and `"false"` used to be read as an empty relationship (clearing it on update) and are now rejected; send `""`, `"[]"` or `"null"` to clear a relationship;
  - a carbon date field holding a value that is not a date string, a date, an integer timestamp in milliseconds or `Int64` (numeric strings and floats, which the driver already rejected, included), and an `is-array` field holding a scalar: `InvalidRequestException`;
  - a model whose `$items`, `$mongoRelation` or relationship configuration is not an array, a referenced relationship whose `model` is not an Eloquent class: `InvalidConfigurationException`;
  - `mongo-sync:benchmark` with a class that is not an Eloquent model exits with code 1.
  A multi-language field whose stored value is not an array is rewritten as a new multi-language value instead of failing.
- `stdClass` values passed as additional data are stored as arrays (Symfony's `InputBag` rejects objects).
- `mongo-sync:check-config` exits with code 1 when a configuration is invalid; `mongo-sync:sync-collection` exits with code 1 for an unknown model.
- The test suite runs on Pest with Orchestra Testbench.

### Deprecated
- `RelationshipMongoTrait::handleSubTarget()`: use `batchHandleSubTarget()`.

### Removed
- Support for PHP < 8.4 and for Laravel releases with known advisories (< 12.69 and 13.0 - 13.29).
- Application-specific methods, now in [`docs/upgrade/extracted_methods.php`](docs/upgrade/extracted_methods.php) for copy into your project: `MongoCollection::getBySlugAndStatus()`, `getBySlug()`, `getNotDeleted()`, `getPublished()`, `getPublic()`, `getActive()`, `findByAID()`, `hasPermission()`, `hasRole()`, `checkPermission()`; `ModelAdditionalMethod::getPageMetaTag()`; the global helpers `getAID()`, `getPrimaryRequest()` and `processList()`.
- The global helper `getTypeOnTarget()` (use `Arr::get($relation, 'typeOnTarget', 'EmbedsMany')`).
- Scrutinizer, StyleCI, Codacy, Coveralls and `phpcs.xml` configurations; `composer.lock` is no longer committed.

### Fixed
- A request without an `EmbedsMany` relationship that has a target (partial requests, and full requests before #55) emptied the stored relationship; in background mode the loss reached the targets too. Omitted relationships are now kept and only their targets receive the updated fields.
- `mongo-sync:analyse` ([#43](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/43)): exits with `1` when it finds anomalies or errors (also with `--fix`), discovers models in sub-directories and `other_models`, skips abstract and non-model classes, reports invalid configurations, unreadable relations and unsupported `HasOne`/`HasMany` targets, dispatches at most one repair job per source and relation, never repairs a missing target nor an `EmbedsOne` inverse that embeds another document (reported as a conflict), and reports models that cannot be loaded or a namespace that matches no class. Its messages are written to the command output instead of Termwind.
- Relationship payloads that are not a JSON string, objects holding an operator `ref_id`, numeric ids and non plain referenced `id`/`_id` are rejected before anything is written; error messages no longer include the request or the payload.
- `delete($id)` on an embedded relation removed every item (the id guard of laravel-mongodb was missing).
- `increment()` / `decrement()` on an embedded model lost the value of columns that are not mass assignable.
- Target documents whose `_id` is stored as a hexadecimal string were not synced.
- An `id`/`_id` containing a MongoDB operator was not rejected by package models; a relationship `ref_id` that is an object or a list is now rejected before anything is written (it used to save the source and then crash).
- Re-assigning an unset attribute, dot-notation reads and `decimal` casts on package models.
- An explicit `updated_at` inside `$set` was overwritten by the builder.
- Target writes inside `DB::transaction()` survived a rollback.
- Target sync failed on models without an `updated_at` column (`UPDATED_AT = null`).
- `EmbedsMany` lists were stored as objects after deleting or dissociating an item.
- Global helpers (`ml()`, `cl()`, `isML()`, `getTranslatedContent()`, ...) are autoloaded again for consumers (they were only in `autoload-dev`).
- Fatal error with recent laravel-mongodb 5.x releases: `embedsMany()` / `embedsOne()` are declared public.
- `LogicException` on Laravel 13 when the observer was registered while the model was booting.
- Background sync was a silent no-op on real queue drivers (embedded models were restored from a non-existent collection).
- Background sync could delete a reference just written when two workers ran the delete and save jobs out of order.
- `ref_id` comparisons failed between `ObjectId` and string values.
- Synchronous target sync failed on every target with `InvalidRequestException` (level > 1 sync used a malformed request key; nested sync is not implemented, on either path).
- A partial update of a model whose `EmbedsOne` target relation is empty crashed with a `TypeError`.
- `destroyWithSync(false)` still synchronised the targets when background sync was enabled.
- `getTranslatedContent()` returned an empty string for locales other than `en_EN` / `it_IT`.
- `getCounterForRelationships()` returned no counter for nested `EmbedsMany` items.
- Partial requests wrapped the whole `EmbedsOne` mini model in `ref_id`.
- An empty `EmbedsOne` relation returned an empty model instead of `null` (as in 3.x).
- Embedded models did not get a generated key (the 5.x key name is `id`) and string ids were not converted to `ObjectId`.
- `mongo-sync:benchmark` always crashed (it imported a non-existent facade) and exited 0 for an unknown model.
- `drop:collection`, `model-doc:generate` and `mongo-sync:check-config` did not find models in subdirectories of `model_path`.
- `mongo-sync:check-config` exited 0 when the model directory could not be read.
- `Builder::chunkById()` always failed (it forced the `_id` column, returned as `id` by laravel-mongodb 5).
- `increment()` / `decrement()` on an embedded model crashed on an expression column.
- Eager loading an `EmbedsOne` relation crashed.
- `getRandomRow()` returned models without id or attributes.

### Known issues
- Referenced relationships (`HasOne` / `HasMany`) do not check that a child belongs to the parent being saved (#46): see UPGRADE-4.0.md.
- laravel-mongodb 5.11 runs the deferred callbacks of a rolled-back `transaction()` at the next commit.

## [3.1.1] and earlier

See the [GitHub releases](https://github.com/offline-agency/laravel-mongo-auto-sync/releases).
