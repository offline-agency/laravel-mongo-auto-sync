# Upgrading from 3.x to 4.0

## Requirements

| | 3.x | 4.0 |
| --- | --- | --- |
| PHP | >= 7.4 | ^8.4 |
| Laravel | 5.x - 8.x | ^12.8 \| ^13.0 |
| MongoDB driver | `jenssegers/mongodb` ^3.6 | `mongodb/laravel-mongodb` ^5.2 (`ext-mongodb` required) |

```bash
composer require offline-agency/laravel-mongo-auto-sync:^4.0
```

## 1. MongoDB driver and namespaces

`jenssegers/mongodb` is replaced by the official `mongodb/laravel-mongodb` 5.x. Follow its [upgrade guide](https://www.mongodb.com/docs/drivers/php/laravel-mongodb/current/upgrade/); the changes that affect models synced by this package are:

- Namespaces: `Jenssegers\Mongodb\...` becomes `MongoDB\Laravel\...` (for example `Jenssegers\Mongodb\Eloquent\Model` -> `MongoDB\Laravel\Eloquent\Model`). The package models (`MDModel`, `DefaultMini`, ...) already extend the new classes.
- The collection name is read from `$table`: rename `protected $collection = 'articles';` to `protected $table = 'articles';`. A model that keeps `$collection` silently uses the default (pluralised) collection name.
- The primary key attribute is `id` (stored as `_id` in MongoDB). Use `$model->id` and `'id'` in queries and when creating embedded documents; `getAttributes()` exposes `id`, not `_id`.
- `DB::collection('name')` is removed: use `DB::table('name')`. Query builder results are `stdClass` objects, not arrays.
- Embedded documents are read back as arrays. `stdClass` values passed as additional data to `storeWithSync()` / `updateWithSync()` are stored as arrays (Symfony's `InputBag` no longer accepts objects).

## 2. Removed application-specific methods

These methods contained business logic of specific projects and are no longer part of the package:

| Removed | Was in |
| --- | --- |
| `getBySlugAndStatus()`, `getBySlug()`, `getNotDeleted()`, `getPublished()`, `getPublic()`, `getActive()`, `findByAID()`, `hasPermission()`, `hasRole()`, `checkPermission()` | `MongoCollection` |
| `getPageMetaTag()` | `ModelAdditionalMethod` |
| `getAID()`, `getPrimaryRequest()`, `processList()` | `SyncHelper` and the global helpers |
| `getTypeOnTarget()` | global helper (use `Arr::get($relation, 'typeOnTarget', 'EmbedsMany')`) |

Their last implementation is kept in [`docs/upgrade/extracted_methods.php`](docs/upgrade/extracted_methods.php) (not autoloaded): copy the traits or functions you need into your application, for example a `MongoCollection` subclass returned by your models' `newCollection()`.

The other global helpers (`ml()`, `cl()`, `isML()`, `isMD()`, `getTranslatedContent()`, `is_EM()`, `is_EO()`, `hasTarget()`, ...) are unchanged and autoloaded as before.

## 3. Exceptions

Generic `Exception`s are replaced by classes extending `OfflineAgency\MongoAutoSync\Exceptions\MongoAutoSyncException`:

- `InvalidConfigurationException`: invalid model or relationship configuration (for example a missing `ref_id` on an embedded object).
- `InvalidRequestException`: a key required by the model is missing in the request.
- `InvalidRelationshipException`: unsupported relationship type.
- `ModelNotFoundException`: model class not found (console commands).

`catch (\Exception $e)` keeps working; catch `MongoAutoSyncException` to handle only package errors.

## 4. Stricter types

Helpers and traits have native parameter and return types. Code that passed loosely typed values (for example `null` where a string is expected, or an `int` id to a method expecting a string) may now get a `TypeError`.

## 5. Background sync (opt-in)

Target synchronisation runs synchronously by default, exactly as in 3.x. To move it to the queue:

```dotenv
MONGO_AUTO_SYNC_BACKGROUND_SYNC=true
MONGO_AUTO_SYNC_QUEUE_CONNECTION=redis   # optional, default connection when empty
MONGO_AUTO_SYNC_QUEUE_NAME=mongo_auto_sync # optional, default queue when empty
```

```bash
php artisan queue:work redis --queue=mongo_auto_sync
```

With background sync enabled, target collections are **eventually consistent**: a request that reads the target right after `storeWithSync()` / `updateWithSync()` may still see the previous mini model until the worker processes the job. Nested targets (level > 1) are only synchronised on the synchronous path.

### `SyncTargetJob` constructor

The job keeps its class name (`OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob`) but is now `final` and carries ids only:

```php
// 3.x / 4.0 pre-release
new SyncTargetJob($model, $relation, $method, 'save', $items, $requestData, $targetAdditionalData, $sourceId);

// 4.0
new SyncTargetJob(
    sourceClass: Article::class,
    sourceId: (string) $article->id,
    method: 'categories',
    relation: $article->getMongoRelation()['categories'],
    currentTargetIds: ['<category id>'],   // targets that must contain the mini model
    removedTargetIds: [],                  // targets that must no longer contain it
);

// or, from a model, with the configured connection and queue applied:
SyncTargetJob::dispatchFor($article, 'categories', $relation, $currentTargetIds, $removedTargetIds);
```

Jobs serialised by a previous version cannot be unserialised by 4.0: drain the queue before deploying.

## 6. Logging

`storeWithSync()` / `updateWithSync()` no longer write `info` logs on every call. Set `MONGO_AUTO_SYNC_LOGGING=true` to log them at `debug` level.

## 7. Console commands

- `mongo-sync:check-config` exits with code 1 when a configuration is invalid: it can be used as a CI step.
- New: `mongo-sync:analyse [--fix]`, `mongo-sync:sync-collection {model}`, `mongo-sync:benchmark {model} {count=100}`.
