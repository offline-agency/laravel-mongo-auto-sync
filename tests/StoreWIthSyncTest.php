<?php

namespace Tests;

use Faker\Factory;
use Illuminate\Support\Facades\Date;
use OfflineAgency\MongoAutoSync\Extensions\MongoCollection;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

class StoreWIthSyncTest extends SyncTestCase
{
    public function test_store_with_embeds_many_on_target()
    {
        //Create a navigation and test if the data is store correctly
        $navigation = $this->createNavigation();

        $this->assertTrue($this->isNavigationCreated($navigation));
        $this->assertInstanceOf(MongoCollection::class, $navigation->sub_items);
        $mini_navigation = $this->getMiniNavigation($navigation->id);

        $sub_item = $this->createSubItems(
            [
                'text' => 'example sub item test',
                'code' => 'HFGRT12345',
                'href' => 'https://google.com',
                'navigation' => $mini_navigation,
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
        $this->assertEquals(getTranslatedContent($sub_item->text), getTranslatedContent($sub_item_mini->text));
        $this->assertEquals($sub_item->code, $sub_item_mini->code);
        $this->assertEquals($sub_item->href, $sub_item_mini->href);

        $faker = Factory::create();
        //Add more sub items and restart test
        $navigation->sub_items = [
            [
                'ref_id' => $faker->uuid,
                'text' => $faker->text,
                'code' => $faker->name,
                'href' => $faker->url,
            ],
            [
                'ref_id' => $faker->uuid,
                'text' => $faker->text,
                'code' => $faker->name,
                'href' => $faker->url,
            ],
            [
                'ref_id' => $faker->uuid,
                'text' => $faker->text,
                'code' => $faker->name,
                'href' => $faker->url,
            ],
        ];

        $navigation->save();

        $mini_navigation = $this->getMiniNavigation($navigation->id);
        $data = [
            'navigation' => $mini_navigation,
        ];

        $sub_item = $this->createSubItems($data);
        $navigation = Navigation::find($navigation->id);

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
}
