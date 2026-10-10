<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

const UNTRUSTED_SECRET = 'sentinel-secret-7f3a';

beforeEach(function () {
    DB::table('navigation')->truncate();
    DB::table('sub_items')->truncate();
});

/** A navigation that already exists: an injected filter would match it. */
function existingNavigation(): Navigation
{
    return test()->createNavigation(['text' => 'victim']);
}

function miniNavigationWithRefId(mixed $refId): string
{
    return json_encode([(object) ['ref_id' => $refId, 'text' => 't', 'code' => 'c', 'title' => 'x']]);
}

dataset('untrusted ref ids', [
    'operator' => [(object) ['$ne' => null]],
    'nested operator' => [(object) ['a' => (object) ['$gt' => '']]],
    'composite key' => [(object) ['tenant' => 't1', 'number' => 7]],
    'list' => [['650000000000000000000001']],
    'boolean' => [true],
]);

test('storeWithSync rejects an untrusted ref_id before any lookup or write', function (mixed $refId, bool $background) {
    config()->set('laravel-mongo-auto-sync.use_background_sync', $background);
    $victim = existingNavigation();
    Queue::fake();

    $request = new Request(['password' => UNTRUSTED_SECRET]);
    $error = null;
    try {
        (new SubItem)->storeWithSync($request, ['text' => 'x', 'code' => 'c', 'href' => 'h', 'navigation' => miniNavigationWithRefId($refId)]);
    } catch (InvalidRequestException $e) {
        $error = $e;
    }

    expect($error)->not->toBeNull();
    expect($error->getMessage())->toContain("'navigation'")->toContain('ref_id')->not->toContain(UNTRUSTED_SECRET);
    expect(DB::table('sub_items')->count())->toBe(0);
    expect(Navigation::find($victim->id)->sub_items)->toHaveCount(0);
    Queue::assertNothingPushed();
})->with('untrusted ref ids')->with(['sync path' => false, 'background' => true]);

test('updateWithSync rejects an untrusted ref_id and leaves the stored documents unchanged', function (mixed $refId) {
    $victim = existingNavigation();
    $subItem = $this->createSubItems(['text' => 'original']);
    $before = (array) DB::table('sub_items')->where('id', $subItem->id)->first();

    expect(fn () => $subItem->updateWithSync(new Request, ['text' => 'changed', 'code' => 'c', 'href' => 'h', 'navigation' => miniNavigationWithRefId($refId)]))
        ->toThrow(InvalidRequestException::class, "'navigation'");

    expect((array) DB::table('sub_items')->where('id', $subItem->id)->first())->toEqual($before);
    expect(Navigation::find($victim->id)->sub_items)->toHaveCount(0);
})->with('untrusted ref ids');

test('a ref_id passed through additionalData is validated like the request body', function () {
    existingNavigation();
    $request = new Request(['navigation' => miniNavigationWithRefId('650000000000000000000001')]);

    expect(fn () => (new SubItem)->storeWithSync($request, ['text' => 'x', 'code' => 'c', 'href' => 'h', 'navigation' => miniNavigationWithRefId((object) ['$ne' => null])]))
        ->toThrow(InvalidRequestException::class);
    expect(DB::table('sub_items')->count())->toBe(0);
});

test('string, hexadecimal and null ref_ids are accepted', function () {
    $plainTarget = new Navigation;
    $plainTarget->id = 'plain-navigation-id';
    $plainTarget->text = 'plain';
    $plainTarget->sub_items = [];
    $plainTarget->save();
    $hexTarget = existingNavigation();

    $plainSource = $this->createSubItems(['navigation' => miniNavigationWithRefId('plain-navigation-id')]);
    $hexSource = $this->createSubItems(['navigation' => miniNavigationWithRefId((string) $hexTarget->id)]);
    $empty = $this->createSubItems(['navigation' => miniNavigationWithRefId(null)]);

    expect(collect(Navigation::find('plain-navigation-id')->sub_items)->pluck('ref_id')->all())->toBe([(string) $plainSource->id]);
    expect(collect(Navigation::find($hexTarget->id)->sub_items)->pluck('ref_id')->all())->toBe([(string) $hexSource->id]);
    expect($empty->navigation?->ref_id)->toBeNull();
});

test('package models keep the native protections of find, whereIn, where and destroy', function () {
    $victim = existingNavigation();

    expect(Navigation::find(['$ne' => null]))->toHaveCount(0);
    expect(fn () => Navigation::whereIn('id', [['$ne' => null]])->count())->toThrow(InvalidArgumentException::class, 'cannot contain the MongoDB operator');
    expect(fn () => Navigation::where('id', '=', ['$ne' => null])->get())->toThrow(InvalidArgumentException::class, 'cannot contain the MongoDB operator');
    expect(fn () => Navigation::destroy([['$ne' => null]]))->toThrow(InvalidArgumentException::class, 'cannot contain the MongoDB operator');
    expect(Navigation::find($victim->id))->not->toBeNull();
});
