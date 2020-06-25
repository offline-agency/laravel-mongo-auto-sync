<?php

namespace Tests;

use DateTime;
use Faker\Factory;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;
use Tests\Models\Item;
use Tests\Models\Navigation;

class MongoSyncTraitTest extends SyncTestCase
{
    public function test_null_value_saved()
    {
        $request = new Request;

        $navigation = $this->createNavigation();

        $arr = [
            'text' => null,
        ];
        $options = [
            'request_type' => 'partial',
        ];

        $navigation->updateWithSync($request, $arr, $options);

        $this->assertNull($navigation->text);
    }

    public function test_store_with_sync()
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
            'target' => (object) [
                'name' => $faker->text(20),
                'code' => $faker->slug(2),
            ],
        ];

        $navigation->storeWithSync($request, $arr);

        $this->assertTrue($this->isNavigationCreated($navigation));
    }

    public function test_store_different_input_type()
    {
        $request = new Request;

        $faker = Factory::create();

        $navigation = new Navigation;

        $arr = [
            'text' => $faker->text(50),
            'code' => $faker->creditCardNumber,
            'href' => null,
            'title' => $faker->text(30),
            'date' => new UTCDateTime(new DateTime()),
            'target' => (object) [],
        ];

        $navigation->storeWithSync($request, $arr);

        $this->assertTrue($this->isNavigationCreated($navigation));
        $this->assertIsString($navigation->text);
        $this->assertNull($navigation->href);
        $this->assertIsArray($navigation->title);
        $this->assertIsObject($navigation->target);
    }

    public function test_update_with_sync_with_embeds_one_on_target()
    {
        $request = new Request;

        $faker = Factory::create();

        $navigation = Navigation::all()->last();

        $options = [
            'request_type' => 'partial',
        ];

        $arr = [
            'text' => 'Aggiornato',
            'code' => $faker->creditCardNumber,
            'href' => $faker->imageUrl(),
            'title' => $faker->text(30),
            'date' => null,
            'target' => (object) [],
        ];

        $navigation->updateWithSync($request, $arr, $options);

        $this->assertTrue($this->isUpdated($navigation));
    }

    public function test_update_with_sync_with_embeds_many_on_target()
    {
        $request = new Request;

        $faker = Factory::create();

        $navigation = Navigation::all()->last();

        $options = [
            'request_type' => 'partial',
        ];

        $arr = [
            'text' => 'Aggiornato',
            'code' => $faker->creditCardNumber,
            'href' => $faker->imageUrl(),
            'title' => $faker->text(30),
            'date' => null,
            'target' => (object) [],
        ];

        $navigation->updateWithSync($request, $arr, $options);

        $this->assertTrue($this->isUpdated($navigation));
    }

    public function test_update_different_input_type()
    {
        $request = new Request;

        $faker = Factory::create();

        $navigation = Navigation::all()->last();

        $options = [
            'request_type' => 'partial',
        ];

        $arr = [
            'text' => 'Aggiornato',
            'code' => $faker->creditCardNumber,
            'href' => $faker->text(50),
            'title' => $faker->text(30),
            'date' => new UTCDateTime(new DateTime()),
            'target' => (object) [],
        ];

        $navigation->updateWithSync($request, $arr, $options);

        $this->assertTrue($this->isUpdated($navigation));
        $this->assertIsObject($navigation->target);
        $this->assertIsArray($navigation->title);
        $this->assertIsString($navigation->href);
    }

    public function test_update_null_value_on_all_field_except_text_and_code()
    {
        $request = new Request;

        $faker = Factory::create();

        $navigation = Navigation::all()->last();

        $options = [
            'request_type' => 'partial',
        ];

        $arr = [
            'text' => 'Aggiornato',
            'code' => $faker->creditCardNumber,
            'href' => null,
            'title' => null,
            'date' => null,
            'target' => null,
        ];

        $navigation->updateWithSync($request, $arr, $options);

        $this->assertTrue($this->isUpdated($navigation));
        /*$this->assertNull($navigation->href);
        $this->assertNull($navigation->title[cl()]);
        $this->assertNull($navigation->date);
        $this->assertNull($navigation->target);*/
    }

    public function test_store_item_with_relation()
    {
        $request = new Request;
        $navigation = $this->createNavigation();
        $faker = Factory::create();

        $item = new Item;

        $arr = [
            'name' => $faker->firstName.' '.$faker->lastName,
            'code' => $faker->creditCardNumber,
            'price' => $faker->numberBetween(1, 100),
            'quantity' => $faker->numberBetween(1, 10),
            'discount' => $faker->randomElement([10, 20, 50]),
            'taxable_price' => $faker->numberBetween(10, 500),
            'partial_vat' => $faker->numberBetween(20, 50),
            'total_price' => $faker->numberBetween(20, 600),
            'vat_code' => $faker->randomElement([
                '0',
                '3',
                '4',
            ]),
            'vat_value' => $faker->randomElement([
                22,
                10,
                4,
            ]),
            'vat_label' => $faker->randomElement([
                'Iva 22%',
                'Iva 10%',
                'Iva 4%',
            ]),
            'collection_type' => null,
            'navigation_code' => $navigation->code,

            //Relation
            'navigation' => $this->getNavigation($navigation->code),
        ];

        $item = $item->storeWithSync($request, $arr);
        $mini_items = Navigation::where('sub_items.ref_id', $item->getId())->first()->sub_items;

        $this->assertTrue($this->isItemCreated($item));
        $this->assertNotNull($mini_items);
    }

    public function test_update_navigation_with_items()
    {
        $navigation = $this->createNavigation();

        $request = new Request;

        $options = [
            'request_type' => 'partial',
        ];

        $arr = [
            'text' => $navigation->text.' Aggiornato',
            'code' => $navigation->code,
            'href' => $navigation->href,
            'title' => $navigation->title[cl()],
            'date' => $navigation->date,
            'target' => $navigation->target,
        ];

        $navigation->updateWithSync($request, $arr, $options);

        $this->assertTrue($this->isNavigationUpdatedCorrectly($navigation));
    }
}
