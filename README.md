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
- Referenced sub documents [TO DO] 
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
| 12.x / 13.x | ≥ 8.4  | 4.x         |
| 5.x - 8.x   | ≥ 7.4  | 2.x / 3.x   |

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

Partial requests are an option of the sync methods, not a config key: `$model->updateWithSync($request, $data, ['request_type' => 'partial'])`.

### Background sync

With `use_background_sync=true` every save or delete of a model with target relationships dispatches one `SyncTargetJob` per relationship. The job carries ids only, reloads the source when it runs and can safely run twice or out of order. Run a worker on the configured connection and queue, for example:

```bash
php artisan queue:work --queue=mongo_auto_sync
```

Nested targets (level > 1) are only synchronised on the synchronous path.

## Console Commands

### Check Model Configuration
Validate your model configurations and relationships:
```bash
php artisan mongo-sync:check-config
```

### Analyse Database
Find embedded references that are missing on the target side (`--fix` dispatches a `SyncTargetJob` for each inconsistency):
```bash
php artisan mongo-sync:analyse [--fix]
```

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
- Nested target synchronisation (level > 1) on the background path.
- Refactor **save()** method in order to handle CRUD operation on relationship also without sync.
- Tests for referenced relationships (`HasOne` / `HasMany`).
- Better support for all field types.
- DestroyWithSync() without delete sub documents on other collections.
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
