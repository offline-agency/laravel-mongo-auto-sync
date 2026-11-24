# Upgrading from v3 to v4

Version 4 raises the minimum PHP version and replaces the abandoned `jenssegers/mongodb` package with `mongodb/laravel-mongodb`.

## Requirements

- PHP 8.4, 8.5 or 8.6 (v3 supported PHP 7.4 and later).
- Laravel 12 or 13. Support for Laravel 5 to 8 has been dropped. Laravel 11 is not supported: Composer reports security advisories for every 11.x release.

## Replace jenssegers/mongodb

`jenssegers/mongodb` is abandoned in favour of `mongodb/laravel-mongodb`, which is now a dependency of this package. In your application:

```bash
composer remove jenssegers/mongodb
composer require offline-agency/laravel-mongo-auto-sync:^4.0
```

Then update the namespaces used in your code:

| v3 (jenssegers/mongodb) | v4 (mongodb/laravel-mongodb) |
|-------------------------|------------------------------|
| `Jenssegers\Mongodb\Eloquent\Model` | `MongoDB\Laravel\Eloquent\Model` |
| `Jenssegers\Mongodb\MongodbServiceProvider` | `MongoDB\Laravel\MongoDBServiceProvider` |
| `Jenssegers\Mongodb\MongodbQueueServiceProvider` | removed: the queue connector is registered by `MongoDBServiceProvider` |

`OfflineAgency\MongoAutoSync\Http\Models\MDModel` now extends `MongoDB\Laravel\Eloquent\Model`.

## Behaviour changes

- **`$collection` is still honoured.** `mongodb/laravel-mongodb` only reads `$table`. `MDModel` overrides `getTable()` so models that declare `protected $collection = '...'` keep using that collection.
- **`stdClass` values in `$additionalData`.** `storeWithSync()` and `updateWithSync()` merge `$additionalData` into the request. The request input bag of Laravel 12 and 13 rejects objects, so `stdClass` values are converted to arrays before the merge. A field that used to be saved as an object is now saved as an array.

## Tests

If you mix the package traits (for example `Helper`) into your own PHPUnit test cases, `Helper::isArray()` clashes with the final `PHPUnit\Framework\Assert::isArray()` of recent PHPUnit versions. Use the traits in a plain host class instead, as the package test suite does.
