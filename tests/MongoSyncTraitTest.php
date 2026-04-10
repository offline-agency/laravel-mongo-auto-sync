<?php

use Faker\Factory;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;
use Tests\Models\Item;
use Tests\Models\Navigation;

test('null value saved', function () {
    $request = new Request;

    $navigation = $this->createNavigation();

    $arr = [
        'text' => null,
    ];
    $options = [
        'request_type' => 'partial',
    ];

    $navigation->updateWithSync($request, $arr, $options);

    expect($navigation->text)->toBeNull();
});

test('store with sync', function () {
    $request = new Request;

    $faker = Factory::create();

    $navigation = new Navigation;

    $arr = [
        'text' => $faker->text(50),
        'code' => $faker->creditCardNumber,
        'href' => $faker->url,
        'title' => $faker->text(30),
        'date' => new UTCDateTime(new DateTime),
        'target' => (object) [
            'name' => $faker->text(20),
            'code' => $faker->slug(2),
        ],
    ];

    $navigation->storeWithSync($request, $arr);

    expect($this->isNavigationCreated($navigation))->toBeTrue();
});

test('store different input type', function () {
    $request = new Request;

    $faker = Factory::create();

    $navigation = new Navigation;

    $arr = [
        'text' => $faker->text(50),
        'code' => $faker->creditCardNumber,
        'href' => null,
        'title' => $faker->text(30),
        'date' => new UTCDateTime(new DateTime),
        'target' => (object) [],
    ];

    $navigation->storeWithSync($request, $arr);

    expect($this->isNavigationCreated($navigation))->toBeTrue();
    expect($navigation->text)->toBeString();
    expect($navigation->href)->toBeNull();
    expect($navigation->title)->toBeArray();
    expect($navigation->target)->toBeObject();
});

test('update with sync with embeds one on target', function () {
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

    expect($this->isUpdated($navigation))->toBeTrue();
});

test('update with sync with embeds many on target', function () {
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

    expect($this->isUpdated($navigation))->toBeTrue();
});

test('update different input type', function () {
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
        'date' => new UTCDateTime(new DateTime),
        'target' => (object) [],
    ];

    $navigation->updateWithSync($request, $arr, $options);

    expect($this->isUpdated($navigation))->toBeTrue();
    expect($navigation->target)->toBeObject();
    expect($navigation->title)->toBeArray();
    expect($navigation->href)->toBeString();
});

test('update null value on all field except text and code', function () {
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

    expect($this->isUpdated($navigation))->toBeTrue();
    /*$this->assertNull($navigation->href);
    $this->assertNull($navigation->title[cl()]);
    $this->assertNull($navigation->date);
    $this->assertNull($navigation->target);*/
});

test('store item with relation', function () {
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

        // Relation
        'navigation' => $this->getNavigation($navigation->code),
    ];

    $item = $item->storeWithSync($request, $arr);
    $mini_items = Navigation::where('sub_items.ref_id', $item->getId())->first()->sub_items;

    expect($this->isItemCreated($item))->toBeTrue();
    expect($mini_items)->not->toBeNull();
});

test('update navigation with items', function () {
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

    expect($this->isNavigationUpdatedCorrectly($navigation))->toBeTrue();
});
