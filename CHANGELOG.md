# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] - Unreleased

See [docs/v4/upgrading.md](docs/v4/upgrading.md) for the migration guide.

### Breaking changes
- The minimum PHP version is now 8.4. PHP 7.4 to 8.3 are no longer supported.
- Laravel 5 to 8 are no longer supported. The package requires Laravel 12 or 13 (`illuminate/support`, `illuminate/container`, `illuminate/database`, `illuminate/events`). Laravel 11 is not supported because every 11.x release is flagged by Composer security advisories.
- `jenssegers/mongodb` (abandoned) is replaced by `mongodb/laravel-mongodb` ^5.6. The namespace moves from `Jenssegers\Mongodb` to `MongoDB\Laravel`, and `MDModel` now extends `MongoDB\Laravel\Eloquent\Model`.
- `stdClass` values in the `$additionalData` argument of `storeWithSync()` and `updateWithSync()` are converted to arrays, because the request input bag of Laravel 12 and 13 rejects objects.

### Added
- Support for PHP 8.4, 8.5 and 8.6.
- Support for Laravel 12 and 13.
- Pint (`laravel` preset) and PHPStan with Larastan at level 5 (no baseline), with the `lint`, `lint:check` and `stan` composer scripts.
- CI: test matrix on PHP 8.4, 8.5 and 8.6 with Laravel 12 and 13, a `prefer-lowest` job and MongoDB 4.4 to 8.0 server versions, plus `composer validate --strict`, Pint and PHPStan jobs.
- Documentation split by major version in `docs/` (`docs/v4/`), with an upgrade guide from v3.
- `CHANGELOG.md`.
- Tests for the console commands, `is-md` date fields, the `getBySlug` 404 and partial requests: line, method and class coverage is now 100%.

### Changed
- Development dependencies: `orchestra/testbench` ^10.0 or ^11.0, `phpunit/phpunit` ^11.5, ^12.0 or ^13.0, `phpunit/phpcov` ^11.0, ^12.0 or ^13.0. `phpunit.xml` migrated to the current schema.
- The package traits are tested through plain host classes, because `Helper::isArray()` clashes with the final `PHPUnit\Framework\Assert::isArray()`.
- The Dockerfile uses PHP 8.4 and Composer 2.
- The README has a verified badge row and a compatibility table.
- Code style fixed with Pint and the issues reported by PHPStan level 5 fixed (missing imports, docblock types, dead code), without changing the public API.

### Fixed
- `MDModel` keeps honouring the `$collection` property through `getTable()`: `mongodb/laravel-mongodb` only reads `$table`, so models declaring `$collection` would otherwise silently use a different collection.
