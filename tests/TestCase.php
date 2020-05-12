<?php

namespace Tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, MockeryPHPUnitIntegration;

    protected function setUp()
    {
        parent::setUp();
    }

    /*protected function getEnvironmentSetUp($app)
    {
        $app['path.base'] = __DIR__ . '/../config/app.php';

        $config = require 'config/app.php';
    }*/
}
