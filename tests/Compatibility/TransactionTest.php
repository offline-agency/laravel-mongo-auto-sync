<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

beforeEach(function () {
    $hello = (array) DB::connection('mongodb')->getMongoDB()->command(['hello' => 1])->toArray()[0];
    if (! isset($hello['setName'])) {
        $this->markTestSkipped('Transactions need a replica set (set MONGO_PORT to the rs0 member, see docs/compatibility/test-matrix.md).');
    }
    DB::table('navigation')->truncate();
    DB::table('sub_items')->truncate();
});

function subItemRequest(Navigation $navigation, string $text): array
{
    return ['text' => $text, 'code' => 'c', 'href' => 'h', 'navigation' => test()->getMiniNavigation($navigation->id)];
}

test('a rolled back transaction leaves neither the source nor the synchronous target write', function () {
    $navigation = $this->createNavigation();

    try {
        DB::connection('mongodb')->transaction(function () use ($navigation) {
            (new SubItem)->storeWithSync(new Request, subItemRequest($navigation, 'inside'));

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('sub_items')->count())->toBe(0);
    expect(Navigation::find($navigation->id)->sub_items)->toHaveCount(0);
});

test('a committed transaction makes the source and the target write visible together', function () {
    $navigation = $this->createNavigation();

    DB::connection('mongodb')->transaction(function () use ($navigation) {
        $subItem = (new SubItem)->storeWithSync(new Request, subItemRequest($navigation, 'committed'));

        // outside the session nothing is visible before the commit
        expect(DB::connection('mongodb')->getCollection('sub_items')->countDocuments())->toBe(0);

        return $subItem;
    });

    expect(DB::table('sub_items')->count())->toBe(1);
    expect(Navigation::find($navigation->id)->sub_items)->toHaveCount(1);
});

test('with the sync queue driver, background sync inside a transaction is rolled back or committed with it', function () {
    config()->set('laravel-mongo-auto-sync.use_background_sync', true);
    $navigation = $this->createNavigation();

    try {
        DB::connection('mongodb')->transaction(function () use ($navigation) {
            (new SubItem)->storeWithSync(new Request, subItemRequest($navigation, 'rolled back'));
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    expect(Navigation::find($navigation->id)->sub_items)->toHaveCount(0);

    DB::connection('mongodb')->transaction(fn () => (new SubItem)->storeWithSync(new Request, subItemRequest($navigation, 'committed')));
    expect(collect(Navigation::find($navigation->id)->sub_items)->pluck('code')->all())->toBe(['c']);
});
