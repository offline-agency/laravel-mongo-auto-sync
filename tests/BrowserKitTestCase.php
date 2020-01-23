<?php

namespace Tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\BrowserKit\TestCase as BaseTestCase;

abstract class BrowserKitTestCase extends BaseTestCase
{
    use CreatesApplication, MockeryPHPUnitIntegration;

    public $baseUrl = 'http://localhost';
}
