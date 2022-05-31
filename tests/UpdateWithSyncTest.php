<?php

namespace Tests;

use Faker\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use OfflineAgency\MongoAutoSync\Extensions\MongoCollection;
use Tests\Models\MiniNavigation;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

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
        $this->assertEquals('Random title', getTranslatedContent($mini_navigation->title));
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
        //Check target TODO

        $mini_navigation = $this->getMiniNavigation($navigation->id);

        $data = [
            'navigation' => $mini_navigation,
        ];

        $sub_item = $this->createSubItems($data);

        $this->assertEquals($navigation->id, $sub_item->navigation->ref_id);
        $this->assertInstanceOf(MiniNavigation::class, $sub_item->navigation);

        //Check target
        $navigation_original = Navigation::find($navigation->id);

        /*$sub_item_mini = $navigation->sub_items[0];

        $this->assertNotEmpty($navigation_original->sub_items);
        $this->assertEquals($sub_item->id, $sub_item_mini->ref_id);
        $this->assertEquals($sub_item->text, $sub_item_mini->text);
        $this->assertEquals($sub_item->code, $sub_item_mini->code);
        $this->assertEquals($sub_item->title, $sub_item_mini->title);*/

        //Add more sub items and restart test
        $sub_items = $this->getMiniSubItem($sub_item->id);
        $date = Carbon::now();
        $data = [
            'text' => 'text_updated',
            'title' => 'title_updated',
            'code' => 'code_updated',
            'href' => 'href_updated',
            'date' => $date,
            'target' => 'target_updated',
            'sub_items' => $sub_items,
        ];

        $options = [];
        $request = new Request;
        $navigation_updated = $navigation_original->updateWithSync($request, $data, $options);

        $this->assertEquals('text_updated', $navigation_updated->text);
        $this->assertEquals('title_updated', getTranslatedContent($navigation_updated->title));
        $this->assertEquals('code_updated', $navigation_updated->code);
        $this->assertEquals('href_updated', $navigation_updated->href);
        //$this->assertEquals($date,$navigation_updated->date );TODO: fix precision
        $this->assertEquals('target_updated', $navigation_updated->target);
        $this->assertEquals(1, $navigation_updated->sub_items->count());

        $mini_navigation = $this->getMiniNavigation($navigation->id);
        $data = [
            'navigation' => $mini_navigation,
        ];

        $sub_item = $this->createSubItems($data);
        $navigation = Navigation::find($navigation->id);

        /*$this->assertTrue($navigation->sub_items->count() == 4);

        $sub_item_mini = $navigation->sub_items->where('ref_id', $sub_item->id)->first();

        $this->assertEquals($sub_item->id, $sub_item_mini->ref_id);
        $this->assertEquals($sub_item->text, $sub_item_mini->text);
        $this->assertEquals($sub_item->code, $sub_item_mini->code);
        $this->assertEquals($sub_item->title, $sub_item_mini->title);*/

        //clean data
        $navigation->delete();
        $sub_item->delete();
    }

    public function test_update_with_partial_request_plain_field()
    {
        //Navigation
        $this->partialUpdateNavigation();

        //SubItems
        $this->partialUpdateSubItems();
    }

    public function test_update_with_partial_request_relationship_field()
    {
        //Navigation
        $this->partialUpdateNavigationRelationship();

        //SubItem
        $this->partialUpdateSubItemsRelationship();
    }

    private function partialUpdateNavigation()
    {
        //Create a navigation and associated to the sub item on creation
        $navigation_original = $this->createNavigation();

        $data = [
            'text' => 'updated',
            'title' => 'updated',
        ];

        $options = [
            'request_type' => 'partial',
        ];
        $request = new Request;
        $navigation_new = $navigation_original->updateWithSync($request, $data, $options);

        //text has been updated?
        $this->assertEquals('updated', $navigation_new->text);
        $this->assertEquals('updated', $navigation_new->title[cl()]);

        //all the other fields has not been updated?
        $this->assertEquals($navigation_original->code, $navigation_new->code);
        $this->assertEquals($navigation_original->href, $navigation_new->href);
        $this->assertEquals($navigation_original->date, $navigation_new->date);
        $this->assertEquals($navigation_original->target, $navigation_new->target);
        $this->assertEquals($navigation_original->sub_items, $navigation_new->sub_items);

        //clean data
        $navigation_new->delete();
    }

    private function partialUpdateSubItems()
    {
        //Create a navigation and associated to the sub item on creation
        $sub_item_original = $this->createSubItems();

        $data = [
            'text' => 'updated',
            'code' => 'updated',
        ];

        $options = [
            'request_type' => 'partial',
        ];
        $request = new Request;
        $sub_item_new = $sub_item_original->updateWithSync($request, $data, $options);

        //text has been updated?
        $this->assertEquals('updated', $sub_item_new->text[cl()]);
        $this->assertEquals('updated', $sub_item_new->code);

        //all the other fields has not been updated?
        $this->assertEquals($sub_item_original->navigation->getAttributes(), $sub_item_new->navigation->getAttributes());
        $this->assertEquals($sub_item_original->href, $sub_item_new->href);

        //clean data
        $sub_item_new->delete();
    }

    private function partialUpdateNavigationRelationship()
    {
        //Create a sub_items and associated to the navigation on update
        $navigation_original = $this->createNavigation();
        $sub_item_original = $this->createSubItems();

        //Test Update from SubItem
        $mini_sub_items = $this->getMiniSubItem($sub_item_original->id);
        $data = [
            'sub_items' => $mini_sub_items,
        ];

        $options = [
            'request_type' => 'partial',
        ];
        $request = new Request;
        $navigation_updated = $navigation_original->updateWithSync($request, $data, $options);
        $mini_sub_item_updated = $navigation_updated->sub_items[0];
        $mini_sub_item_original = json_decode($mini_sub_items)[0];

        //navigation has been updated?
        $this->assertNotEmpty($navigation_updated->sub_items);
        $this->assertNotNull($mini_sub_item_updated);

        $this->assertEquals($mini_sub_item_original->ref_id, $mini_sub_item_updated->ref_id);
        $this->assertEquals($mini_sub_item_original->text, $mini_sub_item_updated->text[cl()]);
        $this->assertEquals($mini_sub_item_original->code, $mini_sub_item_updated->code);
        $this->assertEquals($mini_sub_item_original->href, $mini_sub_item_updated->href);

        //all the other fields has not been updated?
        $this->assertEquals($navigation_original->title[cl()], $navigation_updated->title[cl()]);
        $this->assertEquals($navigation_original->code, $navigation_updated->code);
        $this->assertEquals($navigation_original->href, $navigation_updated->href);
        $this->assertEquals($navigation_original->title, $navigation_updated->title);
        $this->assertEquals($navigation_original->date, $navigation_updated->date);
        $this->assertEquals($navigation_original->target, $navigation_updated->target);

        //check target - Sub_item
        $sub_item = SubItem::all()->where('id', $sub_item_original->id)->first();

        $this->assertEquals($navigation_updated->id, $sub_item->navigation->ref_id);

        $sub_item_original->delete();
        $navigation_original->delete();
    }

    private function partialUpdateSubItemsRelationship()
    {
        $navigation_original = $this->createNavigation();
        $mini_navigation_original = $this->getMiniNavigation($navigation_original->id);

        $sub_item_original = $this->createSubItems(['navigation' => $mini_navigation_original]);
        $navigation = $this->createNavigation();

        //Test Update from SubItem
        $mini_navigation = $this->getMiniNavigation($navigation->id);
        $data = [
            'navigation' => $mini_navigation,
        ];

        $options = [
            'request_type' => 'partial',
        ];
        $request = new Request;

        $sub_item_updated = $sub_item_original->updateWithSync($request, $data, $options);

        //navigation has been updated?
        $this->assertNotEquals($sub_item_original->navigation->getAttributes(), $sub_item_updated->navigation->getAttributes());
        $this->assertNotNull($sub_item_updated->navigation);

        $this->assertEquals($navigation->id, $sub_item_updated->navigation->ref_id);
        $this->assertEquals($navigation->text, $sub_item_updated->navigation->text);
        $this->assertEquals($navigation->code, $sub_item_updated->navigation->code);
        $this->assertEquals($navigation->title[cl()], $sub_item_updated->navigation->title[cl()]);

        //all the other fields has not been updated?
        $this->assertEquals($sub_item_original->text[cl()], $sub_item_updated->text[cl()]);
        $this->assertEquals($sub_item_original->code, $sub_item_updated->code);
        $this->assertEquals($sub_item_original->href, $sub_item_updated->href);

        //check target - Navigation
        $navigation = Navigation::all()->where('id', $navigation->id)->first();

        $this->assertTrue($navigation->sub_items->where('ref_id', $sub_item_updated->id)->count() === 1);

        //check target - Navigation subitem has been detached from navigation original?
        $navigation = Navigation::all()->where('id', $navigation_original->id)->first();

        $this->assertTrue($navigation->sub_items->where('ref_id', $sub_item_updated->id)->count() === 0);

        $sub_item_original->delete();
        $navigation->delete();
    }
}
