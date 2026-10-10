# Upgrading from 3.x to 4.0

## Requirements

| | 3.x | 4.0 |
| --- | --- | --- |
| PHP | >= 7.4 | ^8.4 |
| Laravel | 5.x - 8.x | ^12.69 \| ^13.30 |
| Framework | | `laravel/framework` ^12.69 \| ^13.30 (the package uses `Illuminate\Foundation`) |
| MongoDB driver | `jenssegers/mongodb` ^3.6 | `mongodb/laravel-mongodb` ^5.11, `ext-mongodb` ^2.4; `mongodb/mongodb` < 2.4.2 is declared as a conflict |
| MongoDB server | | 7.0 and 8.0 standalone, 8.0 replica set (tested) |

The Laravel floor is the first release of each line without the advisory CVE-2026-102279, and `mongodb/mongodb` 2.4.2 the first without CVE-2026-88023: Composer refuses the older versions.

```bash
composer require offline-agency/laravel-mongo-auto-sync:^4.0
```

## 1. MongoDB driver and namespaces

`jenssegers/mongodb` is replaced by the official `mongodb/laravel-mongodb` 5.x. Follow its [upgrade guide](https://www.mongodb.com/docs/drivers/php/laravel-mongodb/current/upgrade/); the changes that affect models synced by this package are:

- Namespaces: `Jenssegers\Mongodb\...` becomes `MongoDB\Laravel\...` (for example `Jenssegers\Mongodb\Eloquent\Model` -> `MongoDB\Laravel\Eloquent\Model`). The package models (`MDModel`, `DefaultMini`, ...) already extend the new classes.
- The collection name is read from `$table`: rename `protected $collection = 'articles';` to `protected $table = 'articles';`. A model that keeps `$collection` silently uses the default (pluralised) collection name.
- The primary key attribute is `id` (stored as `_id` in MongoDB). Use `$model->id` and `'id'` in queries and when creating embedded documents; `getAttributes()` exposes `id`, not `_id`.
- `DB::collection('name')` is removed: use `DB::table('name')`. Query builder results are `stdClass` objects, not arrays.
- String ids assigned to `id` / `_id` are converted to `ObjectId` (as laravel-mongodb does), including on embedded models.
- Model attributes go through laravel-mongodb: an `id`/`_id` containing a MongoDB operator (also nested) is rejected with `InvalidArgumentException`, and `decimal` casts are stored as `Decimal128` (they were stored as strings by the 4.0 pre-releases; such values are still read correctly and are not converted).
- `Model::raw()` returns models hydrated like laravel-mongodb does: the key attribute is `id` (not `_id`) and date fields are Carbon instances.
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

## 4. Full requests must contain every relationship ([#55](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/55))

`storeWithSync()` and `updateWithSync()` check the request after merging `additionalData`: in a full request every top-level relationship declared in `$mongoRelation` must be present, otherwise `InvalidRequestException("Missing relationship '<key>' in the request.")` is thrown before the source is written, targets are touched or jobs are dispatched.

- Send an explicit empty value (`null`, `""` or `"[]"`) to store an empty relationship.
- Use `['request_type' => 'partial']` to update only some fields: omitted relationships stay unchanged and their targets receive the updated fields of the source.
- In 3.x a full request without a relationship that has a target was silently filled from the stored document (and an `EmbedsMany` one was emptied). Code that relied on that must send the key or use a partial request.
- The check covers the top-level keys only; it is not a transaction nor a recursive validation of nested relationships.

## 4b. Stricter types

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

With background sync enabled, target collections are **eventually consistent**: a request that reads the target right after `storeWithSync()` / `updateWithSync()` may still see the previous mini model until the worker processes the job. The job rebuilds the mini model from the stored source, so values that exist only in the request (and not on the source document or in the target additional data) are not copied to the targets. Nested targets (level > 1) are not synchronised, on either path.

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

### Target writes

Targets are updated with atomic MongoDB operations (`$pull` / `$push` / `$set`) through the laravel-mongodb query builder instead of loading and saving the target model, on both the synchronous and the background path. Writes of different sources on the same target no longer overwrite each other and they join the connection's transaction, but **no Eloquent events (`saving`, `saved`, observers) fire on the target models** for these writes; `updated_at` is still refreshed.

Limits: two workers syncing the same source at the same moment can leave the older mini model on a target; with a queue outside the MongoDB transaction (for example Redis), jobs dispatched inside `DB::transaction()` wait for the commit only if the queue connection sets `after_commit => true`, and laravel-mongodb 5.11 keeps the deferred callbacks of a `transaction()` that was rolled back and runs them at the next commit (the job then only finds the stored state); (see `docs/compatibility/roadmap.md`, item B). Sources stored on another connection or in a tenant database are synced in that database on both paths; queued jobs carry a `SyncContext` (payload version 2): drain jobs queued by the pre-releases before deploying, see [docs/tenancy.md](docs/tenancy.md).

### Referenced relationships (`HasOne` / `HasMany`)

Referenced children are identified only among the children the parent holds through its declared relation, and the whole payload is validated before the parent or any child is written ([#46](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/46)). Compared with the 4.0 pre-releases:
- `foreignKey` is required and must match the relation method (`hasOne` / `hasMany` of the configured `model`); the child model must extend `MDModel`;
- an `id` of another parent's child, an unknown `id`, or any `id` while the parent is created throws `InvalidRequestException` instead of re-parenting the child or creating a new one;
- only the child's items and relationships can be written; the foreign key and any other field are rejected;
- the same child twice, and more than one document in a `HasOne`, are rejected.

`destroyWithSync()` deletes referenced children according to the new `onParentDelete` option (`'delete'` / `'retain'`); without it the pre-release behaviour is kept (deleted only for relationships with a target). See [docs/referenced-relationships.md](docs/referenced-relationships.md).

### Embedded lists stored as objects

The 4.0 pre-releases could store an `EmbedsMany` list as a BSON object after an item was deleted or dissociated (a later `create()` then failed with `Cannot apply $addToSet to non-array field`). 4.0 always writes arrays but does not convert existing documents. To find affected documents: `db.<collection>.find({ <relation>: { $type: "object" } })`.

### Relation aggregates and embedded increments

- `withCount()`, `withSum()` and the other relation aggregates of laravel-mongodb do not support the package's embedded relations (they throw `LogicException`); they work on referenced relations.
- `increment()` / `decrement()` on an embedded model persist the amount and the extra columns like laravel-mongodb, but fire `saving`/`saved` events as well (laravel-mongodb fires `updating`/`updated` only).

With background sync enabled, deleting a model removes its mini model from both `EmbedsMany` and `EmbedsOne` targets; the synchronous `destroyWithSync()` keeps the 3.x behaviour and only cleans `EmbedsMany` targets. `destroyWithSync(false)` leaves the targets untouched on both paths; `storeWithSync()` / `updateWithSync()` accept `['sync_targets' => false]` in `$options`, and `$model->withoutTargetSync(fn () => ...)` covers plain saves (see the README, "Disabling target sync for one operation"). The switch is per model instance: it no longer suspends the dispatch of other models saved during the delete.

## 6. Logging

`storeWithSync()` / `updateWithSync()` no longer write `info` logs on every call. Set `MONGO_AUTO_SYNC_LOGGING=true` to log them at `debug` level.

## 7. Console commands

- `mongo-sync:check-config` exits with code 1 when a configuration is invalid: it can be used as a CI step.
- `mongo-sync:analyse` exits with code 1 when it finds anomalies or errors, also with `--fix` (a dispatched repair is not a clean scan): re-run it after the queue worker.
- New: `mongo-sync:analyse [--fix]`, `mongo-sync:sync-collection {model}`, `mongo-sync:benchmark {model} {count=100}`.
