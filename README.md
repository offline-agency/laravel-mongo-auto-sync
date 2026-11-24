# Laravel MongoDB Relationships
[![Latest Release](https://img.shields.io/github/v/release/offline-agency/laravel-mongo-auto-sync?sort=semver)](https://github.com/offline-agency/laravel-mongo-auto-sync/releases)
[![Packagist Version](https://img.shields.io/packagist/v/offline-agency/laravel-mongo-auto-sync)](https://packagist.org/packages/offline-agency/laravel-mongo-auto-sync)
[![Total Downloads](https://img.shields.io/packagist/dt/offline-agency/laravel-mongo-auto-sync)](https://packagist.org/packages/offline-agency/laravel-mongo-auto-sync)
[![PHP Version](https://img.shields.io/packagist/dependency-v/offline-agency/laravel-mongo-auto-sync/php)](https://packagist.org/packages/offline-agency/laravel-mongo-auto-sync)
[![Tests](https://github.com/offline-agency/laravel-mongo-auto-sync/actions/workflows/build-ci.yml/badge.svg?branch=master)](https://github.com/offline-agency/laravel-mongo-auto-sync/actions/workflows/build-ci.yml)
[![License](https://img.shields.io/github/license/offline-agency/laravel-mongo-auto-sync)](LICENSE.md)
[![Quality Score](https://img.shields.io/scrutinizer/g/offline-agency/laravel-mongo-auto-sync.svg?style=flat-square)](https://scrutinizer-ci.com/g/offline-agency/laravel-mongo-auto-sync)
[![codecov](https://codecov.io/gh/offline-agency/laravel-mongo-auto-sync/branch/master/graph/badge.svg?token=0BHADJQYAW)](https://codecov.io/gh/offline-agency/laravel-mongo-auto-sync)

This package provides a better support for [MongoDB](https://www.mongodb.com) relationships in [Laravel](https://laravel.com/) Projects.
At low level all CRUD operations are handled by [mongodb/laravel-mongodb](https://packagist.org/packages/mongodb/laravel-mongodb)

## Requirements

- PHP 8.4, 8.5 or 8.6
- Laravel 12 or 13
- The [MongoDB PHP driver](https://www.php.net/manual/en/mongodb.installation.php) (`ext-mongodb`)

## Installation

```bash
composer require offline-agency/laravel-mongo-auto-sync
```

Upgrading from version 3? Read the [upgrade guide](docs/v4/upgrading.md).

## Compatibility

| Version | PHP | Laravel | MongoDB package |
|---------|-----|---------|-----------------|
| 4.x | 8.4, 8.5, 8.6 | 12.x, 13.x | mongodb/laravel-mongodb ^5.6 |
| 3.x | >=7.4 | 5.x - 8.x | jenssegers/mongodb ^3.6 - ^3.8 |
| 2.x | >=7.4 | 5.x - 8.x | jenssegers/mongodb ^3.6 - ^3.8 |
| 1.x | >=7.1 | 5.8, 6.x | jenssegers/mongodb 3.6.7 |

## Features
- Sync changes between collection with relationships after CRUD operations
    - EmbedsOne & EmbedsMany

See the [usage guide](docs/v4/usage.md) for examples with and without the package.

## Use cases
- Blog: see demo [here](https://github.com/offline-agency/laravel-mongodb-blog)
- Ecommerce
- API System for mobile application o for generated static site
- Any projects that require fast read operations and (slow) write operations that can be run on background

## Documentation
- [Documentation index](docs/README.md)
- [Documentation for version 4](docs/v4/index.md)
- Documentation for previous versions: [docs.offlineagency.com](https://docs.offlineagency.com/laravel-mongo-auto-sync/)

## Testing

```bash
docker-compose up
composer test
```

Code style and static analysis: `composer lint:check` and `composer stan`. See [testing](docs/v4/testing.md).

## Roadmap :rocket:
- Refactor target synchronization to Observer pattern, so all this operation can be run on background using [Laravel Queue System](https://laravel.com/docs/5.8/queues). This will also speed up all the operations in the collection that is primary involved in write operations.
- Command Analyse Database: This command will analyse the database in order to find some relationship error. 
Ex: An article with a category associated that is not present on the Category's sub document.
- Refactor **save()** method in order to handle CRUD operation on relationship also without sync.
- Support for [referenced relationships](https://docs.mongodb.com/manual/tutorial/model-referenced-one-to-many-relationships-between-documents/).
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
