<?php

namespace Tests;

use Exception;
use Illuminate\Http\Request;
use OfflineAgency\MongoAutoSync\Traits\MainMongoTrait;

class MainMongoTraitTest extends TestCase
{
    use MainMongoTrait;

    public function test_check_property_existence()
    {
        $this->expectException(Exception::class);

        $this->checkPropertyExistence(
            (object) ['key' => 'value'],
            'fake_key'
        );
    }

    public function test_check_array_existence()
    {
        $this->expectException(Exception::class);

        $this->checkArrayExistence(
            ['key' => 'value'],
            'fake_key'
        );
    }

    public function test_check_request_existence()
    {
        $request = new Request;

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
