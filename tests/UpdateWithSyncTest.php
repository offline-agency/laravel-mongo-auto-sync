<?php

namespace Tests\Feature;

use Faker\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use OfflineAgency\MongoAutoSync\Extensions\MongoCollection;
use Tests\Models\MiniNavigation;
use Tests\Models\Navigation;
use Tests\Models\SubItem;
use Tests\SyncTestCase;

class UpdateWithSyncTest extends SyncTestCase
{
    public function test_update_with_embeds_one_on_target()
    {
        //Sub Item Test
        $sub_item = $this->createSubItems(
            [
                'text' => 'example sub item test',
                'code' => 'HFGRT12345',
                'href' => 'https://google.com',
            ]
        );
        $this->assertEquals('example sub item test', getTranslatedContent($sub_item->text));
        $this->assertEquals('HFGRT12345', $sub_item->code);
        $this->assertEquals('https://google.com', $sub_item->href);

        //Navigation Test
        $sub_items = json_encode(
            [
                (object) [
                    'ref_id' => $sub_item->id,
                    'text' => getTranslatedContent($sub_item->text),
                    'code' => $sub_item->code,
                    'href' => $sub_item->href,
                ],
            ]
        );

        $navigation = new Navigation;

        $date = Date::now();

        $navigation = $this->createNavigation(
            [
                'text' => 'example navigation text',
                'code' => '1234ABHFGRT5',
                'href' => 'https://www.netflix.com/browse',
                'date' => $date,
                'target' => '_blank',
                'title' => 'Random title',
                'sub_items' => $sub_items,
            ]
        );

        $this->assertTrue($this->isNavigationCreated($navigation));
        $this->assertIsString($navigation->text);

        $this->assertEquals('example navigation text', $navigation->text);
        $this->assertEquals('1234ABHFGRT5', $navigation->code);
        $this->assertEquals('https://www.netflix.com/browse', $navigation->href);
        //$this->assertEquals($date, $navigation->date); TODO: Precision to be fixed
        $this->assertEquals('_blank', $navigation->target);
        $this->assertEquals('Random title', getTranslatedContent($navigation->title));
        $this->assertInstanceOf(MongoCollection::class, $navigation->sub_items);

        //Check target
        $sub_item = SubItem::find($sub_item->id);
        $mini_navigation = $sub_item->navigation;
        $this->assertNotNull($mini_navigation);
        $this->assertEquals($navigation->id, $mini_navigation->ref_id);
        $this->assertEquals('1234ABHFGRT5', $mini_navigation->code);
        $this->assertEquals('Random title', $mini_navigation->title);
        $this->assertEquals('example navigation text', $navigation->text);

        //clean data
        $navigation->delete();
        $sub_item->delete();
    }

    public function test_update_with_embeds_many_on_target()
    {
        $faker = Factory::create();

        $navigation = new Navigation;

        $text = $faker->text(50);
        $code = $faker->creditCardNumber;
        $href = $faker->url;
        $date = Date::now();
        $target = $faker->text(50);
        $title = null;
        $items = json_encode([]);

        $arr = [
            'text' => $text,
            'code' => $code,
            'href' => $href,
            'date' => $date,
            'target' => $target,
            'title' => $title,
            'sub_items' => $items,
        ];
        $request = new Request;
        $navigation = $navigation->storeWithSync($request, $arr);

        $this->assertTrue($this->isNavigationCreated($navigation));
        $this->assertEquals($text, $navigation->text);

        $this->assertEquals($text, $navigation->text);
        $this->assertEquals($code, $navigation->code);
        $this->assertEquals($href, $navigation->href);
        // $this->assertEquals($date, $navigation->date); TODO: Precision to be fixed
        $this->assertEquals($target, $navigation->target);
        $this->assertEquals($title, getTranslatedContent($navigation->title));
        $this->assertInstanceOf(MongoCollection::class, $navigation->sub_items);

        $mini_navigation = $this->getMiniNavigation($navigation->id);

        $data = [
            'navigation' => $mini_navigation,
        ];

        $sub_item = $this->createSubItems($data);

        $this->assertEquals($navigation->id, $sub_item->navigation->ref_id);
        $this->assertInstanceOf(MiniNavigation::class, $sub_item->navigation);

        //Check target
        $navigation = Navigation::find($navigation->id);
        $sub_item_mini = $navigation->sub_items[0];

        $this->assertNotEmpty($navigation->sub_items);
        $this->assertEquals($sub_item->id, $sub_item_mini->ref_id);
        $this->assertEquals($sub_item->text, $sub_item_mini->text);
        $this->assertEquals($sub_item->code, $sub_item_mini->code);
        $this->assertEquals($sub_item->title, $sub_item_mini->title);

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

    public function test_update_with_partial_request()
    {
        //Create a navigation and associated to the sub item on creation
        $sub_item = $this->createSubItems();
        $mini_sub_item = $this->getMiniSubItem($sub_item);
        $data = [
            'sub_items' => $mini_sub_item,
        ];

        $navigation = $this->createNavigation($data);
    }

    private function getMiniSubItem(SubItem $sub_item)
    {
        return json_encode([]);
    }
}
