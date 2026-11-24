<?php

namespace Tests;

use Exception;
use Illuminate\Http\Request;
use Tests\Support\MainMongoTraitHost;

class MainMongoTraitTest extends SyncTestCase
{
    protected MainMongoTraitHost $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = new MainMongoTraitHost;
    }

    public function test_checkPropertyExistence()
    {
        $this->expectException(Exception::class);

        $this->host->checkPropertyExistence(
            (object) ['key' => 'value'],
            'fake_key'
        );
    }

    public function test_checkArrayExistence()
    {
        $this->expectException(Exception::class);

        $this->host->checkArrayExistence(
            ['key' => 'value'],
            'fake_key'
        );
    }

    public function test_checkRequestExistence()
    {
        $request = new Request();

        $this->expectException(Exception::class);

        $request = $request->replace([
            ['key' => 'value'],
        ]);

        $this->host->callCheckRequestExistence(
            $request,
            'fake_key'
        );
    }
}
