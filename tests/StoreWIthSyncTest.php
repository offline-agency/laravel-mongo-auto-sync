<?php

use Faker\Factory;
use Illuminate\Support\Facades\Date;
use OfflineAgency\MongoAutoSync\Extensions\MongoCollection;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

test('store with embeds one on target', function () {
    // Create a SubItem and test if the data is store correctly
    $sub_item = $this->createSubItems(
        [
            'text' => 'example sub item test',
            'code' => 'HFGRT12345',
            'href' => 'https://google.com',
        ]
    );

    expect(getTranslatedContent($sub_item->text))->toEqual('example sub item test');
    expect($sub_item->code)->toEqual('HFGRT12345');
    expect($sub_item->href)->toEqual('https://google.com');

    // Create a mini Sub Item to associate to the new navigation
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

    // Create a navigation and test if the data is store correctly
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

    expect($this->isNavigationCreated($navigation))->toBeTrue();
    expect($navigation->text)->toBeString();
    expect($navigation->title)->toBeArray();

    expect($navigation->text)->toEqual('example navigation text');
    expect($navigation->code)->toEqual('1234ABHFGRT5');
    expect($navigation->href)->toEqual('https://www.netflix.com/browse');
    // $this->assertEquals($date, $navigation->date); TODO: fix precision date
    expect($navigation->target)->toEqual('_blank');
    expect(getTranslatedContent($navigation->title))->toEqual('Random title');
    expect($navigation->sub_items)->toBeInstanceOf(MongoCollection::class);

    // Check if the subitem target is updated correctly
    $sub_item = SubItem::find($sub_item->id);
    $mini_navigation = $sub_item->navigation;
    expect($mini_navigation)->not->toBeNull();
    expect($mini_navigation->ref_id)->toEqual($navigation->id);
    expect($mini_navigation->code)->toEqual('1234ABHFGRT5');
    expect(getTranslatedContent($mini_navigation->title))->toEqual('Random title');
    expect($navigation->text)->toEqual('example navigation text');

    // Clean all data that has been stored
    $this->cleanUp($navigation, $sub_item);
});

test('store with embeds many on target', function () {
    // Create a navigation and test if the data is store correctly
    $navigation = $this->createNavigation();

    expect($this->isNavigationCreated($navigation))->toBeTrue();
    expect($navigation->sub_items)->toBeInstanceOf(MongoCollection::class);
    $mini_navigation = $this->getMiniNavigation($navigation->id);

    $sub_item = $this->createSubItems(
        [
            'text' => 'example sub item test',
            'code' => 'HFGRT12345',
            'href' => 'https://google.com',
            'navigation' => $mini_navigation,
        ]
    );

    expect(getTranslatedContent($sub_item->text))->toEqual('example sub item test');
    expect($sub_item->code)->toEqual('HFGRT12345');
    expect($sub_item->href)->toEqual('https://google.com');

    // Check target
    $navigation = Navigation::find($navigation->id);

    $sub_item_mini = $navigation->sub_items[0];

    expect($navigation->sub_items)->not->toBeEmpty();
    expect($sub_item_mini->ref_id)->toEqual($sub_item->id);
    expect(getTranslatedContent($sub_item_mini->text))->toEqual(getTranslatedContent($sub_item->text));
    expect($sub_item_mini->code)->toEqual($sub_item->code);
    expect($sub_item_mini->href)->toEqual($sub_item->href);

    $faker = Factory::create();
    // Add more sub items and restart test
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

    expect($navigation->sub_items->count())->toEqual(4);

    $sub_item_mini = $navigation->sub_items->where('ref_id', $sub_item->id)->first();

    expect($sub_item_mini->ref_id)->toEqual($sub_item->id);
    expect($sub_item_mini->text)->toEqual($sub_item->text);
    expect($sub_item_mini->code)->toEqual($sub_item->code);
    expect($sub_item_mini->title)->toEqual($sub_item->title);

    // clean data
    $navigation->delete();
    $sub_item->delete();
});
