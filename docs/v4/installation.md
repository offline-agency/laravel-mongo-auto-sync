# Installation

## Requirements

- PHP 8.4, 8.5 or 8.6
- Laravel 12 or 13 (`illuminate/support`, `illuminate/container`, `illuminate/database` and `illuminate/events`)
- `mongodb/laravel-mongodb` ^5.6, installed automatically as a dependency
- The `mongodb` and `json` PHP extensions

Make sure you have the MongoDB PHP driver installed. You can find installation instructions at [https://www.php.net/manual/en/mongodb.installation.php](https://www.php.net/manual/en/mongodb.installation.php).

## Install the package

```bash
composer require offline-agency/laravel-mongo-auto-sync
```

The service provider `OfflineAgency\MongoAutoSync\MongoAutoSyncServiceProvider` is registered automatically through Laravel package discovery.

If you are upgrading from version 3, follow the [upgrade guide](upgrading.md).
