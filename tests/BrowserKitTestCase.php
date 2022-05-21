<?php

namespace Tests;

use Orchestra\Testbench\BrowserKit\TestCase as BaseTestCase;

abstract class BrowserKitTestCase extends BaseTestCase
{
    use CreatesApplication;

    public $baseUrl = 'http://localhost';
}
