<?php

namespace Tests;

use Exception;
use Illuminate\Http\Request;
use OfflineAgency\MongoAutoSync\Traits\MainMongoTrait;

class MainMongoTraitTest extends SyncTestCase
{
    use MainMongoTrait;

    public function test_checkPropertyExistence()
    {
        $this->expectException(Exception::class);

        $this->checkPropertyExistence(
            (object) ['key' => 'value'],
            'fake_key'
        );
    }

    public function test_checkArrayExistence()
    {
        $this->expectException(Exception::class);

        $this->checkArrayExistence(
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

        $this->checkRequestExistence(
            $request,
            'fake_key'
        );
    }
}
