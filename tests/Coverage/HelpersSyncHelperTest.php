<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use MongoDB\BSON\ObjectId;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use Tests\Models\MiniCategory;

it('does not treat non-array values as multi-language fields', function () {
    expect(SyncHelper::isML(null))->toBeFalse()
        ->and(SyncHelper::isML('is-ml'))->toBeFalse()
        ->and(SyncHelper::isML(true))->toBeFalse();
});

function hlpMiniCategory(string $refId): MiniCategory
{
    $mini = new MiniCategory;
    $mini->setAttribute('ref_id', $refId);

    return $mini;
}

it('extracts ref ids from a single mini model', function () {
    $mini = hlpMiniCategory('cat-1');

    expect(SyncHelper::refIds($mini))->toBe(['cat-1']);
});

it('extracts ref ids from a single plain object', function () {
    $obj = new stdClass;
    $obj->ref_id = new ObjectId('65a000000000000000000001');

    expect(SyncHelper::refIds($obj))->toBe(['65a000000000000000000001']);
});

it('extracts distinct non-empty ref ids from a mixed collection of embedded items', function () {
    $obj = new stdClass;
    $obj->ref_id = 'obj-1';
    $objWithoutRef = new stdClass;

    $items = new Collection([
        hlpMiniCategory('model-1'),
        ['ref_id' => 'array-1'],
        $obj,
        $objWithoutRef,
        ['ref_id' => 'model-1'],
        ['ref_id' => ''],
        ['ref_id' => 123],
        42,
        'not-an-item',
    ]);

    expect(SyncHelper::refIds($items))->toBe(['model-1', 'array-1', 'obj-1']);
});

it('returns no ref ids for a non iterable scalar value', function () {
    expect(SyncHelper::refIds('ref-1'))->toBe([])
        ->and(SyncHelper::refIds(42))->toBe([]);
});

it('compares ids regardless of their representation', function () {
    $oid = new ObjectId('65a000000000000000000002');

    expect(SyncHelper::sameId($oid, '65a000000000000000000002'))->toBeTrue()
        ->and(SyncHelper::sameId('a', 'b'))->toBeFalse()
        ->and(SyncHelper::sameId(null, 'a'))->toBeFalse();
});

it('never considers non stringable ids equal', function () {
    expect(SyncHelper::sameId(['a'], ['a']))->toBeFalse()
        ->and(SyncHelper::sameId('a', new stdClass))->toBeFalse();
});

it('returns both stored forms of an id', function () {
    $oid = new ObjectId('65a000000000000000000003');

    $fromObjectId = SyncHelper::idVariants($oid);
    $fromHexString = SyncHelper::idVariants('65a000000000000000000003');

    expect($fromObjectId)->toHaveCount(2)
        ->and($fromObjectId[0])->toBe($oid)
        ->and($fromObjectId[1])->toBe('65a000000000000000000003')
        ->and($fromHexString)->toHaveCount(2)
        ->and($fromHexString[0])->toBe('65a000000000000000000003')
        ->and($fromHexString[1])->toBeInstanceOf(ObjectId::class)
        ->and((string) $fromHexString[1])->toBe('65a000000000000000000003')
        ->and(SyncHelper::idVariants('custom-id'))->toBe(['custom-id']);
});

it('returns no id variants for empty or non string ids', function () {
    expect(SyncHelper::idVariants(''))->toBe([])
        ->and(SyncHelper::idVariants(null))->toBe([])
        ->and(SyncHelper::idVariants(123))->toBe([]);
});

it('builds the request to sync on target with the mini model items', function () {
    $request = new Request;
    $request->merge(['name' => 'News', 'slug' => 'news', 'extra' => 'ignored']);

    $out = SyncHelper::getRequestToBeSync('ref-9', MiniCategory::class, $request, 'categories');

    expect($out)->toBeInstanceOf(Request::class)
        ->and($out)->not->toBe($request)
        ->and(array_keys($out->all()))->toBe(['categories'])
        ->and(json_decode((string) $out->input('categories'), true))->toBe([
            'ref_id' => 'ref-9',
            'name' => 'News',
            'slug' => 'news',
            'description' => null,
        ]);
});

it('considers a request ready only when every key except the token contains a dash', function () {
    $ready = new Request;
    $ready->merge(['_token' => 'x', 'name-en' => 'a', 'slug-0' => 'b']);

    $notReady = new Request;
    $notReady->merge(['name-en' => 'a', 'title' => 'b']);

    expect(SyncHelper::isRequestReadyToBeProcessed($ready))->toBeTrue()
        ->and(SyncHelper::isRequestReadyToBeProcessed(new Request))->toBeTrue()
        ->and(SyncHelper::isRequestReadyToBeProcessed($notReady))->toBeFalse();
});

it('leaves the request untouched when removing sub collection input', function () {
    $request = new Request;
    $request->merge(['articles-0' => 'x']);

    $out = SyncHelper::removeSubCollectionInput($request);

    expect($out)->toBe($request)
        ->and($out->all())->toBe(['articles-0' => 'x']);
});

it('merges additional data into the request', function () {
    $request = new Request;
    $request->merge(['name' => 'a']);

    $out = SyncHelper::prepareRequest($request, ['slug' => 'b', 'name' => 'c']);

    expect($out)->toBe($request)
        ->and($out->all())->toBe(['name' => 'c', 'slug' => 'b']);
});
