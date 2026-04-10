<?php

use Faker\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use OfflineAgency\MongoAutoSync\Extensions\MongoCollection;
use Tests\Models\MiniNavigation;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

test('update with embeds one on target', function () {
    // Sub Item Test
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

    // Navigation Test
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

    expect($this->isNavigationCreated($navigation))->toBeTrue();
    expect($navigation->text)->toBeString();

    expect($navigation->text)->toEqual('example navigation text');
    expect($navigation->code)->toEqual('1234ABHFGRT5');
    expect($navigation->href)->toEqual('https://www.netflix.com/browse');
    // $this->assertEquals($date, $navigation->date); TODO: Precision to be fixed
    expect($navigation->target)->toEqual('_blank');
    expect(getTranslatedContent($navigation->title))->toEqual('Random title');
    expect($navigation->sub_items)->toBeInstanceOf(MongoCollection::class);

    // Check target
    $sub_item = SubItem::find($sub_item->id);
    $mini_navigation = $sub_item->navigation;
    expect($mini_navigation)->not->toBeNull();

    expect($mini_navigation->ref_id)->toEqual($navigation->id);
    expect($mini_navigation->code)->toEqual('1234ABHFGRT5');
    expect(getTranslatedContent($mini_navigation->title))->toEqual('Random title');
    expect($navigation->text)->toEqual('example navigation text');

    // clean data
    $navigation->delete();
    $sub_item->delete();
});

test('update with embeds many on target', function () {
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

    expect($this->isNavigationCreated($navigation))->toBeTrue();
    expect($navigation->text)->toEqual($text);

    expect($navigation->text)->toEqual($text);
    expect($navigation->code)->toEqual($code);
    expect($navigation->href)->toEqual($href);
    // $this->assertEquals($date, $navigation->date); TODO: Precision to be fixed
    expect($navigation->target)->toEqual($target);
    expect(getTranslatedContent($navigation->title))->toEqual($title);
    expect($navigation->sub_items)->toBeInstanceOf(MongoCollection::class);

    $mini_navigation = $this->getMiniNavigation($navigation->id);

    $data = [
        'navigation' => $mini_navigation,
    ];

    $sub_item = $this->createSubItems($data);

    expect($sub_item->navigation->ref_id)->toEqual($navigation->id);
    expect($sub_item->navigation)->toBeInstanceOf(MiniNavigation::class);

    // Check target
    $navigation = Navigation::find($navigation->id);

    $sub_item_mini = $navigation->sub_items[0];

    expect($navigation->sub_items)->not->toBeEmpty();
    expect($sub_item_mini->ref_id)->toEqual($sub_item->id);
    expect($sub_item_mini->text)->toEqual($sub_item->text);
    expect($sub_item_mini->code)->toEqual($sub_item->code);
    expect($sub_item_mini->title)->toEqual($sub_item->title);

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

test('update with partial request plain field', function () {
    // Create a navigation and associated to the sub item on creation
    $sub_item_original = $this->createSubItems();

    $data = [
        'text' => 'updated',
    ];

    $options = [
        'request_type' => 'partial',
    ];
    $request = new Request;
    $sub_item_new = $sub_item_original->updateWithSync($request, $data, $options);

    // text has been updated?
    expect($sub_item_new->text[cl()])->toEqual('updated');

    // all the other fields has not been updated?
    expect($sub_item_new->navigation->getAttributes())->toEqual($sub_item_original->navigation->getAttributes());
    expect($sub_item_new->code)->toEqual($sub_item_original->code);
    expect($sub_item_new->href)->toEqual($sub_item_original->href);
});

test('update with partial request relationship field', function () {
    // Create a navigation and associated to the sub item on creation
    $sub_item_original = $this->createSubItems();
    $navigation = $this->createNavigation();
    $mini_navigation = $this->getMiniNavigation($navigation->id);

    $data = [
        'navigation' => $mini_navigation,
    ];

    $options = [
        'request_type' => 'partial',
    ];
    $request = new Request;
    $sub_item_new = $sub_item_original->updateWithSync($request, $data, $options);

    // navigation has been updated?
    expect($sub_item_new->navigation->getAttributes())->not->toEqual($sub_item_original->navigation->getAttributes());

    expect($sub_item_new->navigation->ref_id)->toEqual($navigation->id);
    expect($sub_item_new->navigation->text)->toEqual($navigation->text);
    expect($sub_item_new->navigation->code)->toEqual($navigation->code);
    expect($sub_item_new->navigation->title[cl()])->toEqual($navigation->title[cl()]);

    // all the other fields has not been updated?
    expect($sub_item_new->text[cl()])->toEqual($sub_item_original->text[cl()]);
    expect($sub_item_new->code)->toEqual($sub_item_original->code);
    expect($sub_item_new->href)->toEqual($sub_item_original->href);
});
