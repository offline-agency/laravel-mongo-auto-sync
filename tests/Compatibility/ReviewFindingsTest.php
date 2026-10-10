<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use Tests\Models\CmpGuardedItem;
use Tests\Models\CmpPackageParent;
use Tests\Models\MiniSubItem;
use Tests\Models\Navigation;
use Tests\Models\RefAuthor;
use Tests\Models\SubItem;

const REVIEW_SECRET = 'review-secret-91c2';

beforeEach(function () {
    foreach (['cmp_parents', 'navigation', 'sub_items', 'ref_authors', 'ref_books'] as $collection) {
        DB::table($collection)->truncate();
    }
});

test('delete() on an embedded relation refuses an id instead of removing every item, like laravel-mongodb', function () {
    $parent = CmpPackageParent::create(['name' => 'p']);
    $first = $parent->items()->create(['k' => 'a']);
    $parent->items()->create(['k' => 'b']);

    expect(fn () => $parent->items()->delete($first->getKey()))->toThrow(LogicException::class, 'The id parameter should not be used.');
    expect(CmpPackageParent::find($parent->getKey())->items)->toHaveCount(2);
});

test('incrementing a non fillable column of an embedded model persists the new value', function () {
    $parent = CmpPackageParent::create(['name' => 'p']);
    $item = new CmpGuardedItem(['name' => 'i']);
    $item->visits = 1;
    $parent->embedsMany(CmpGuardedItem::class, 'guarded')->save($item);

    $item->increment('visits', 2);

    expect($item->visits)->toBe(3);
    expect(CmpPackageParent::find($parent->getKey())->embedsMany(CmpGuardedItem::class, 'guarded')->first()->visits)->toBe(3);
});

test('targets whose _id is stored as a hexadecimal string are synced', function () {
    $id = (string) new ObjectId;
    DB::connection('mongodb')->getCollection('navigation')->insertOne(['_id' => $id, 'text' => 'string id', 'sub_items' => []]);
    $target = Navigation::where('text', 'string id')->first();
    $mini = new MiniSubItem;
    $mini->ref_id = (string) new ObjectId;
    $mini->code = 'c';

    $target->updateRelationWithSync($mini, 'sub_items', false, true);
    expect(DB::connection('mongodb')->getCollection('navigation')->findOne(['_id' => $id])['sub_items'])->toHaveCount(1);

    $target->removeRelationWithSync('sub_items', $mini->ref_id, false, true);
    expect(DB::connection('mongodb')->getCollection('navigation')->findOne(['_id' => $id])['sub_items'])->toHaveCount(0);
});

dataset('untrusted relationship payloads', [
    'object instead of a list' => [fn () => json_encode(['x' => ['ref_id' => ['$ne' => null], 'text' => 't', 'code' => 'c', 'title' => 'x']])],
    'array instead of a JSON string' => [fn () => [['ref_id' => ['$ne' => null]]]],
    'integer ref_id' => [fn () => json_encode([['ref_id' => 7, 'text' => 't', 'code' => 'c', 'title' => 'x']])],
]);

test('untrusted relationship payloads are rejected before anything is written', function (Closure $payload, bool $background) {
    config()->set('laravel-mongo-auto-sync.use_background_sync', $background);

    expect(fn () => (new SubItem)->storeWithSync(new Request, ['text' => 'x', 'code' => 'c', 'href' => 'h', 'navigation' => $payload()]))
        ->toThrow(InvalidRequestException::class, "relationship 'navigation'");
    expect(DB::table('sub_items')->count())->toBe(0);
})->with('untrusted relationship payloads')->with(['sync path' => false, 'background' => true]);

test('a referenced relationship id that is not a plain id is rejected before anything is written', function () {
    expect(fn () => (new RefAuthor)->storeWithSync(new Request, ['name' => 'a', 'books' => json_encode([['id' => ['$ne' => null], 'title' => 't']]), 'profile' => '[]']))
        ->toThrow(InvalidRequestException::class, "relationship 'books'");
    expect(DB::table('ref_authors')->count())->toBe(0);
    expect(DB::table('ref_books')->count())->toBe(0);
});

test('configuration and request errors do not include payload or request content', function () {
    try {
        (new SubItem)->storeWithSync(new Request(['password' => REVIEW_SECRET]), ['text' => 'x', 'code' => 'c', 'href' => 'h', 'navigation' => json_encode([['text' => REVIEW_SECRET]])]);
    } catch (InvalidConfigurationException $e) {
        expect($e->getMessage())->toContain('ref_id')->not->toContain(REVIEW_SECRET);
    }

    try {
        (new SubItem)->storeWithSync(new Request(['password' => REVIEW_SECRET]), ['text' => 'x', 'code' => 'c', 'navigation' => '[]']);
        $this->fail('missing href must be rejected');
    } catch (InvalidRequestException $e) {
        expect($e->getMessage())->toContain('href')->not->toContain(REVIEW_SECRET);
    }
});
