# Laravel MongoDB Relationships
[![Latest Stable Version](https://poser.pugx.org/offline-agency/laravel-mongo-auto-sync/v/stable)](https://packagist.org/packages/offline-agency/laravel-mongo-auto-sync)
[![Total Downloads](https://img.shields.io/packagist/dt/offline-agency/laravel-mongo-auto-sync.svg?style=flat-square)](https://packagist.org/packages/offline-agency/laravel-mongo-auto-sync)
[![Build Status](https://github.com/offline-agency/laravel-mongo-auto-sync/actions/workflows/build-ci.yml/badge.svg)](https://github.com/offline-agency/laravel-mongo-auto-sync/actions/workflows/build-ci.yml)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Quality Score](https://img.shields.io/scrutinizer/g/offline-agency/laravel-mongo-auto-sync.svg?style=flat-square)](https://scrutinizer-ci.com/g/offline-agency/laravel-mongo-auto-sync)
[![StyleCI](https://github.styleci.io/repos/167277388/shield)](https://styleci.io/repos/167277388)
[![codecov](https://codecov.io/gh/offline-agency/laravel-mongo-auto-sync/branch/master/graph/badge.svg?token=0BHADJQYAW)](https://codecov.io/gh/offline-agency/laravel-mongo-auto-sync)

This package provides a better support for [MongoDB](https://www.mongodb.com) relationships in [Laravel](https://laravel.com/) Projects.
At low level all CRUD operations has been handled by [jenssegers/laravel-mongodb](https://github.com/jenssegers/laravel-mongodb)

## Installation

```bash
composer require offline-agency/laravel-mongo-auto-sync
```

### Prerequisites
Make sure you have the MongoDB PHP driver installed. You can find installation instructions in the [manual](http://php.net/manual/en/mongodb.installation.php)

### Package version Compatibility

| This package | Laravel | Laravel MongoDB |
|--------------|---------|-----------------|
| 1.x          | 5.8.x   | 3.5.x           |
| 1.x          | 6.x     | 3.6.x           |
| 2.x          | 5.8.x   | 3.5.x           |
| 2.x          | 6.x     | 3.6.x           |
| 2.x          | 7.x     | 3.7.x           |
| 2.x          | 8.x     | 3.8.x           |
| 2.x          | 9.x     | 3.9.x           |
| 3.x          | 5.8.x   | 3.5.x           |
| 3.x          | 6.x     | 3.6.x           |
| 3.x          | 7.x     | 3.7.x           |
| 3.x          | 8.x     | 3.8.x           |
| 3.x          | 9.x     | 3.9.x           |


## Documentation
You can find the documentation [here](https://docs.offlineagency.com/laravel-mongo-auto-sync/)

## Testing

Run this command inside your project's route
``` bash
docker-compose up
```

Now run the tests with:
``` bash
composer test
```

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
