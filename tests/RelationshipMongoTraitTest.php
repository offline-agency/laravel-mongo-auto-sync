<?php

namespace Tests;

use DateTime;
use Faker\Factory;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;
use Tests\Models\Item;
use Tests\Models\Navigation;

class RelationshipMongoTraitTest extends SyncTestCase
{
    public function test_exception_on_updateRelationWithSync_method()
    {
        $request = new Request;

        $faker = Factory::create();

        $navigation = new Navigation;

        $arr = [
            'text' => $faker->text(50),
            'code' => $faker->creditCardNumber,
            'href' => $faker->url,
            'title' => $faker->text(30),
            'date' => new UTCDateTime(new DateTime()),
            'target' => [],
            'sub_items' => null
        ];

        $navigation->storeWithSync($request, $arr);

        $this->expectException('Method on target for navigation doesn\'t exist');
    }
}
