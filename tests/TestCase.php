<?php

namespace Tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OfflineAgency\MongoAutoSync\MongoAutoSyncServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, MockeryPHPUnitIntegration;

    protected function setUp() : void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            MongoAutoSyncServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('laravel-mongo-auto-sync.model_path', '../tests/models');
        $app['config']->set('laravel-mongo-auto-sync.model_namespace', 'Tests\Models');
    }
}
