<?php

namespace Tests;

use Jenssegers\Mongodb\MongodbQueueServiceProvider;
use Jenssegers\Mongodb\MongodbServiceProvider;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OfflineAgency\MongoAutoSync\MongoAutoSyncServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            MongodbServiceProvider::class,
            MongodbQueueServiceProvider::class,
            MongoAutoSyncServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('laravel-mongo-auto-sync.model_path', 'tests/Models');
        $app['config']->set('laravel-mongo-auto-sync.model_namespace', 'Tests\Models');

        $config = require 'config/database.php';

        $app['config']->set('app.key', 'ZsZewWyUJ5FsKp9lMwv4tYbNlegQilM7');

        $app['config']->set('database.default', 'mongodb');
        $app['config']->set('database.connections.mongodb', $config['connections']['mongodb']);
        $app['config']->set('database.connections.mongodb2', $config['connections']['mongodb']);
        $app['config']->set('database.connections.dsn_mongodb', $config['connections']['dsn_mongodb']);
        $app['config']->set('database.connections.dsn_mongodb_db', $config['connections']['dsn_mongodb_db']);

        $app['config']->set('auth.model', 'User');
        $app['config']->set('auth.providers.users.model', 'User');
        $app['config']->set('cache.driver', 'array');

        $app['config']->set('queue.default', 'database');
        $app['config']->set('queue.connections.database', [
            'driver' => 'mongodb',
            'table' => 'jobs',
            'queue' => 'default',
            'expire' => 60,
        ]);
        $app['config']->set('queue.failed.database', 'mongodb2');
    }
}
