<?php

namespace Tests;

use Tests\Models\Navigation;
use Tests\Models\SubItem;
use Faker\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use OfflineAgency\MongoAutoSync\Extensions\MongoCollection;

class StoreWIthSyncTest extends SyncTestCase
{
    public function test_store_with_embeds_one_on_target()
    {
        //Create a SubItem and test if the data is store correctly
        $sub_item = $this->createSubItems(
            [
                'text' => 'example sub item test',
                'code' => 'HFGRT12345',
                'href' => 'https://google.com'
            ]
        );

        $this->assertEquals('example sub item test', getTranslatedContent($sub_item->text));
        $this->assertEquals('HFGRT12345', $sub_item->code);
        $this->assertEquals('https://google.com', $sub_item->href);

        //Create a mini Sub Item to associate to the new navigation
        $sub_items = json_encode(
            [
                (object)[
                    'ref_id' => $sub_item->id,
                    'text' => getTranslatedContent($sub_item->text),
                    'code' => $sub_item->code,
                    'href' => $sub_item->href
                ]
            ]
        );

        $date = Date::now();

        //Create a navigation and test if the data is store correctly
        $navigation = $this->createNavigation(
            [
                'text' => 'example navigation text',
                'code' => '1234ABHFGRT5',
                'href' => 'https://www.netflix.com/browse',
                'date' => $date,
                'target' => '_blank',
                'title' => 'Random title',
                'sub_items' => $sub_items
            ]
        );

        $this->assertTrue($this->isNavigationCreated($navigation));
        $this->assertIsString($navigation->text);
        $this->assertIsArray($navigation->title);


        $this->assertEquals('example navigation text', $navigation->text);
        $this->assertEquals('1234ABHFGRT5', $navigation->code);
        $this->assertEquals('https://www.netflix.com/browse', $navigation->href);
        $this->assertEquals($date, $navigation->date);
        $this->assertEquals('_blank', $navigation->target);
        $this->assertEquals('Random title', getTranslatedContent($navigation->title));
        $this->assertInstanceOf(MongoCollection::class, $navigation->sub_items);

        //Check if the subitem target is updated correctly
        $sub_item = SubItem::find($sub_item->id);
        $mini_navigation = $sub_item->navigation;
        $this->assertNotNull($mini_navigation);
        $this->assertEquals($navigation->id, $mini_navigation->ref_id);
        $this->assertEquals('1234ABHFGRT5', $mini_navigation->code);
        $this->assertEquals('Random title', $mini_navigation->title);
        $this->assertEquals('example navigation text', $navigation->text);

        //Clean all data that has been stored
        $this->cleanUp($navigation, $sub_item);
    }

    public function test_store_with_embeds_many_on_target()
    {
        //Create a navigation and test if the data is store correctly
        $navigation = $this->createNavigation();

        $this->assertTrue($this->isNavigationCreated($navigation));
        $this->assertInstanceOf(MongoCollection::class, $navigation->sub_items);


        $sub_item = $this->createSubItems(
            [
                'text' => 'example sub item test',
                'code' => 'HFGRT12345',
                'href' => 'https://google.com'
            ]
        );

        $this->assertEquals('example sub item test', getTranslatedContent($sub_item->text));
        $this->assertEquals('HFGRT12345', $sub_item->code);
        $this->assertEquals('https://google.com', $sub_item->href);

        //Check target
        $navigation = Navigation::find($navigation->id);
        $sub_item_mini = $navigation->sub_items[0];

        $this->assertNotEmpty($navigation->sub_items);
        $this->assertEquals($sub_item->id, $sub_item_mini->ref_id);
        $this->assertEquals($sub_item->text, getTranslatedContent($sub_item_mini->text));
        $this->assertEquals($sub_item->code, $sub_item_mini->code);
        $this->assertEquals($sub_item->href, $sub_item_mini->href);

        $faker = Factory::create();
        //Add more sub items and restart test
        $navigation->sub_items = [
            [
                'ref_id' => $faker->uuid,
                'text' => $faker->text,
                'code' => $faker->name,
                'href' => $faker->url
            ],
            [
                'ref_id' => $faker->uuid,
                'text' => $faker->text,
                'code' => $faker->name,
                'href' => $faker->url
            ],
            [
                'ref_id' => $faker->uuid,
                'text' => $faker->text,
                'code' => $faker->name,
                'href' => $faker->url
            ]
        ];

        $navigation->save();

        $sub_item = $this->storeSubItem($navigation);
        $navigation = Navigation::find($navigation->id);

        echo "\n Navigation id: " . $navigation->id ;
        $this->assertTrue($navigation->sub_items->count() == 4);

        $sub_item_mini = $navigation->sub_items->where('ref_id', $sub_item->id)->first();

        $this->assertEquals($sub_item->id, $sub_item_mini->ref_id);
        $this->assertEquals($sub_item->text, $sub_item_mini->text);
        $this->assertEquals($sub_item->code, $sub_item_mini->code);
        $this->assertEquals($sub_item->title, $sub_item_mini->title);

        //clean data
        $navigation->delete();
        $sub_item->delete();
    }

    private function storeSubItem($navigation)
    {
        $sub_item = new SubItem;
        $request = new Request;

        $arr = [
            'text' => 'test',
            'code' => 'fff',
            'href' => 'eeee',
            'navigation' => json_encode(
                [
                    (object)[
                        'ref_id' => $navigation->id,
                        'text' => $navigation->text,
                        'code' => $navigation->code,
                        'title' => getTranslatedContent($navigation->title),
                    ]
                ]
            )
        ];

        return $sub_item->storeWithSync($request, $arr);
    }
}
