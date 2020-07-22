<?php

namespace Tests;

use DateTime;
use Exception;
use Faker\Factory;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;
use stdClass;
use Tests\Models\Item;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

class RelationshipMongoTraitTest extends SyncTestCase
{
    public function test_exception_on_updateRelationWithSync_method()
    {
        $request = new Request;
        $faker = Factory::create();

        /* TEST */
        $this->expectException(Exception::class);

        /* CREATE NAVIGATION */
        $navigation = new Navigation();

        $arr = [
            'text' => $faker->text(50),
            'code' => $faker->creditCardNumber,
            'href' => $faker->url,
            'title' => $faker->text(30),
            'date' => new UTCDateTime(new DateTime()),
            'target' => (object)[],
            'sub_items' => null
        ];

        $navigation->storeWithSync($request, $arr);

        /* CREATE MINI NAVIGATION */
        $miniNavigationArr = [];
        $miniNavigation = new stdClass();

        $miniNavigation->ref_id = $navigation->id;
        $miniNavigation->code = $navigation->code;
        $miniNavigation->text = $navigation->text;
        $miniNavigation->title = $navigation->title[cl()];

        $miniNavigationArr[] = $miniNavigation;

        /* CREATE SUB ITEM */
        $sub_item = new SubItem;

        $arr = [
            'text' => $faker->text(200),
            'code' => $faker->slug(2),
            'href' => $faker->url,
            'navigation' => json_encode($miniNavigationArr)
        ];

        $sub_item->storeWithSync($request, $arr);
    }

    /*public function test_exception_on_updateRelationWithSync_method_two()
    {
        $request = new Request;
        $faker = Factory::create();


        $sub_item = new SubItem;

        $arr = [
            'text' => $faker->text(200),
            'code' => $faker->slug(2),
            'href' => $faker->url,
            'navigation' => null
        ];

        $sub_item->storeWithSync($request, $arr);


        $miniSubItemArr = [];
        $miniSubItem = new stdClass();

        $miniSubItem->ref_id = $sub_item->id;
        $miniSubItem->code = $sub_item->code;
        $miniSubItem->text = $sub_item->text[cl()];
        $miniSubItem->href = $sub_item->href;

        $miniSubItemArr[] = $miniSubItem;


        $navigation = new Navigation();

        $arr = [
            'text' => $faker->text(50),
            'code' => $faker->creditCardNumber,
            'href' => $faker->url,
            'title' => $faker->text(30),
            'date' => new UTCDateTime(new DateTime()),
            'target' => (object)[],
            'sub_items' => json_encode($miniSubItemArr)
        ];

        $navigation->storeWithSync($request, $arr);


        $this->expectException('A');
    }*/
}
