# Laravel MongoDB Relationships
[![Latest Stable Version](https://poser.pugx.org/offline-agency/laravel-mongo-auto-sync/v/stable)](https://packagist.org/packages/offline-agency/laravel-mongo-auto-sync)
[![Total Downloads](https://img.shields.io/packagist/dt/offline-agency/laravel-mongo-auto-sync.svg?style=flat-square)](https://packagist.org/packages/offline-agency/laravel-mongo-auto-sync)
[![Build Status](https://img.shields.io/github/actions/workflow/status/offline-agency/laravel-mongo-auto-sync/build-ci.yml)](https://github.com/offline-agency/laravel-mongo-auto-sync/actions)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

This package provides a better support for [MongoDB](https://www.mongodb.com) relationships in [Laravel](https://laravel.com/) Projects.
At low level all CRUD operations has been handled by [mongodb/laravel-mongodb](https://github.com/mongodb/laravel-mongodb)

## Features
- Sync changes between collection with relationships after CRUD operations
    - EmbedsOne & EmbedsMany 
    
#### Example without our package
  
  ``` php
  //create a new Article with title "Game of Thrones" with Category "TV Series"
  //assign data to $article       
  $article->save();
  /*
  Article::class {
    'title' => 'Game of Thrones',
    'category' => Category::class {
        'name' => 'TV Series'
     }
  }
  */
  
  //Retrieve 'TV Series' category
  $category = Category::where('name', 'TV Series')->first();
  /*
    Category::class {
        'name' => 'Game of Thrones',
        'articles' => null
    }
  */ 
  ```
  
The sub document article has not been updated with the new article. So you will need some extra code to write in order to see the new article it in the category page. The number of sync depends on the number of the relationships and on the number of the entry in every single EmbedsMany relationships.
  
Total updates = ∑ (entry in all EmbedsMany relationships) + ∑ (EmbedsOne relationships)
  
As you can see the lines of extra code can rapidly increase, and you will write many redundant code.
 
#### Example with our package
  
  ``` php
  //create a new Article with title "Game of Thrones" with Category "TV Series"
  $article->storeWithSync($request);
  /*
  Article::class {
    'title' => 'Game of Thrones',
    'category' => Category::class {
        'name' => 'TV Series'
    }
  }
   */
  //Retrieve 'TV Series' category
  $category = Category::where('name', 'TV Series')->first();   
 /*
  Category::class {
    'name' => 'Game of Thrones',
    'articles' => Article::class {
        'title' => 'Game of Thrones'
    }
  }
  */ 
  ```
The sub document article has been updated with the new article, with no need of extra code :tada: 


You can see the new article on the category page because the package synchronizes the information for you by reading the Model Setup.
  
**These example can be applied for all write operations on the database.**
- Referenced sub documents (`HasOne` / `HasMany`): see [docs/referenced-relationships.md](docs/referenced-relationships.md)
- Handle sub document as Model in order to exploit Laravel ORM support during write operation (without sync feature) [TO BE TEST] 
- Handle referenced sub document as Model in order to exploit Laravel ORM support during write operation (without sync feature) [TO DO] 
- Advance cast field support

## Use cases
- Blog: see demo [here](https://github.com/offline-agency/laravel-mongodb-blog)
- Ecommerce
- API System for mobile application o for generated static site
- Any projects that require fast read operations and (slow) write operations that can be run on background

## Installation

```bash
composer require offline-agency/laravel-mongo-auto-sync
```
### Laravel version Compatibility

| Laravel     | PHP    | Package     |
| ----------- | ------ | ----------- |
| 12.69+ / 13.30+ | 8.4, 8.5 | 4.x     |
| 5.x - 8.x   | ≥ 7.4  | 2.x / 3.x   |

Package 4.x requires `laravel/framework` ^12.69 | ^13.30, `mongodb/laravel-mongodb` ^5.11 and `ext-mongodb` ^2.4 (with `mongodb/mongodb` 2.4.2 or later), and is tested on MongoDB 7.0 and 8.0 standalone and on a MongoDB 8.0 replica set. Details and the exact verified combinations: [docs/compatibility](docs/compatibility/test-matrix.md).

Upgrading from 3.x? Read [UPGRADE-4.0.md](UPGRADE-4.0.md).

## Documentation
You can find the documentation [here](https://docs.offlineagency.com/laravel-mongo-auto-sync/)

## Configuration

Publish the configuration file using:
```bash
php artisan vendor:publish --provider="OfflineAgency\MongoAutoSync\MongoAutoSyncServiceProvider" --tag="config"
```

Available options:

| Key | Env variable | Default | Description |
| --- | --- | --- | --- |
| `model_path` | | `app_path('Models')` | Path to your models (used by the console commands). |
| `model_namespace` | | `App\Models` | Namespace of your models. |
| `use_background_sync` | `MONGO_AUTO_SYNC_BACKGROUND_SYNC` | `false` | `false`: target collections are updated synchronously inside `storeWithSync()` / `updateWithSync()` / `destroyWithSync()`. `true`: the target updates are dispatched as a queued `SyncTargetJob` (eventual consistency): a queue worker must be running. |
| `queue_connection` | `MONGO_AUTO_SYNC_QUEUE_CONNECTION` | `null` | Queue connection used by `SyncTargetJob` (`null` = the application default). |
| `queue_name` | `MONGO_AUTO_SYNC_QUEUE_NAME` | `null` | Queue name used by `SyncTargetJob` (`null` = the connection default). |
| `logging` | `MONGO_AUTO_SYNC_LOGGING` | `false` | Log `storeWithSync` / `updateWithSync` calls at `debug` level. |

A full request must contain every relationship declared in `$mongoRelation` (send `null`, `""` or `"[]"` for an empty one), otherwise `InvalidRequestException` is thrown before anything is written: see [UPGRADE-4.0.md](UPGRADE-4.0.md#4-full-requests-must-contain-every-relationship-55). Partial requests are an option of the sync methods, not a config key: `$model->updateWithSync($request, $data, ['request_type' => 'partial'])`.

### Background sync

With `use_background_sync=true`, saving or deleting a model dispatches a `SyncTargetJob` for each relationship with a target whose references or data changed. The job carries ids only (no request data), rebuilds the mini model from the stored source when it runs, and writes the targets with atomic MongoDB updates: running a job twice, or the jobs of one source out of order, gives the same final state, and jobs of different sources on the same target do not overwrite each other. Two workers syncing **the same source at the same time** can still leave the older values on a target (see [roadmap B](docs/compatibility/roadmap.md)). Inside `DB::transaction()` the target writes join the transaction; with a queue that is not part of the transaction, jobs wait for the commit only when the queue connection has `after_commit => true`, and laravel-mongodb 5.11 can still run the deferred dispatches of a rolled-back `transaction()` at the next commit (see UPGRADE-4.0.md). Run a worker on the configured connection and queue, for example:

```bash
php artisan queue:work --queue=mongo_auto_sync
```

Nested targets (level > 1) are not synchronised, on either path.

### Disabling target sync for one operation

Write a source (and its own embedded / referenced relationships) without touching or scheduling the targets of that operation ([#45](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/45), [#47](https://github.com/offline-agency/laravel-mongo-auto-sync/issues/47)):

```php
$article->storeWithSync($request, [], ['sync_targets' => false]);
$article->updateWithSync($request, [], ['sync_targets' => false]);
$article->destroyWithSync(false);           // the source is deleted, targets and referenced children are left as they are

$article->withoutTargetSync(function (Article $article) {
    $article->title = ['en' => 'Draft'];
    $article->save();                        // no target write, no SyncTargetJob for this instance
});
```

The switch applies to the model instance and the operation only: validation (#55), referenced-child ownership (#46) and every Eloquent / package event still run; other models saved inside the callback keep syncing; scopes nest and are restored after an exception. It does not cancel jobs queued by **earlier** operations: such a job rebuilds the mini model from the stored source when it runs, so it can still propagate values written with the switch off.

## Console Commands

### Check Model Configuration
Validate your model configurations and relationships:
```bash
php artisan mongo-sync:check-config
```

### Analyse Database
Check that every embedded reference with a target (`EmbedsOne` / `EmbedsMany`) points to an existing target document that embeds the source back:
```bash
php artisan mongo-sync:analyse [--fix]
```
- Models are discovered under `model_path` (sub-directories keep their sub-namespace) and in `other_models`; abstract and non-model classes are skipped, an absent optional `other_models` class is only noted.
- Exit code `0` only when the scan finds no anomaly and no error; `1` for a missing target, a missing back reference, an invalid configuration, an unreadable relation or a `HasOne`/`HasMany` relation with a target (not supported by the analyser).
- Without `--fix` nothing is written and no job is dispatched. With `--fix` at most one `SyncTargetJob` per source document and relation is dispatched (on `queue_connection` / `queue_name`) for missing back references; deleted targets are never recreated. The exit code stays `1`: run the command again after the worker to confirm.
- `--fix` treats the scanned document (the side holding the reference) as authoritative. A target whose `EmbedsOne` inverse already embeds another document is reported as a conflict and not repaired. The synchronous `updateWithSync()` does not clear `EmbedsOne` targets that a source stops referencing (3.x behaviour), so such stale copies appear as conflicts.
- Class names are compared case-insensitively; a model that cannot be loaded, a namespace that matches no class in `model_path` and a malformed `other_models` are configuration errors.
- The scan reads documents one by one: it is not a consistent snapshot, it does not compare embedded values and it does not look for orphan copies on the target side.

### Sync Collection
Dispatch a `SyncTargetJob` for every document of a model:
```bash
php artisan mongo-sync:sync-collection "App\Models\Article"
```

### Benchmark
Measure insert, read, update and delete times for a model:
```bash
php artisan mongo-sync:benchmark "App\Models\Article" 100
```

### Generate Model Documentation
Generate PHPDoc for your models:
```bash
php artisan model-doc:generate {collection_name}
```

## Exceptions

The package throws specific exceptions for better error handling:
- `InvalidConfigurationException`: Model or relationship configuration errors.
- `ModelNotFoundException`: Target model cannot be found.
- `InvalidRelationshipException`: Invalid relationship type.
- `InvalidRequestException`: Invalid request data.

## Troubleshooting

- **Sync not working?** Set `MONGO_AUTO_SYNC_LOGGING=true` and check the debug logs starting with `storeWithSync` or `updateWithSync`. With background sync enabled, make sure a queue worker is running.
- **Model not found?** Ensure your `model_path` and `model_namespace` in config are correct.
- **Invalid configuration?** Run `php artisan mongo-sync:check-config` to identify issues.

## Testing
Run the tests with:
``` bash
composer test
```

## Roadmap :rocket:
- Nested target synchronisation (level > 1).
- Better support for all field types.
- Add more tests.
- Nested relationships.
- Benchmark MongoDB vs Mysql (write and read operation).
- Fix typo errors.

## Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security
If you discover any security-related issues, please email support@offlineagency.com instead of using the issue tracker.

## Credits
- [Giacomo Fabbian](https://github.com/Giacomo92)

- [All Contributors](https://github.com/offline-agency/laravel-mongo-auto-sync/graphs/contributors)

## About us
Offline Agency is a web design agency based in Padua, Italy. You'll find an overview of our projects [on our website](https://offlineagency.it/#home).

## License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
