<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use MongoDB\Model\BSONDocument;
use OfflineAgency\MongoAutoSync\Eloquent\Builder;
use Tests\Models\ElqChild;
use Tests\Models\ElqParent;
use Tests\Models\ElqUntimed;

beforeEach(function () {
    ElqParent::truncate();
    ElqUntimed::truncate();
});

afterEach(function () {
    ElqParent::truncate();
    ElqUntimed::truncate();
});

test('insert on a top level model writes the document to its collection', function () {
    $inserted = ElqParent::query()->insert(['name' => 'plain insert']);

    expect($inserted)->toBeTrue()
        ->and(ElqParent::where('name', 'plain insert')->count())->toBe(1);
});

test('saving a non incrementing embedded model inserts it through the parent relation', function () {
    $parent = ElqParent::create(['name' => 'p']);

    $child = $parent->children()->save(new ElqChild(['label' => 'first']));

    expect($child)->toBeInstanceOf(ElqChild::class)
        ->and($child->exists)->toBeTrue()
        ->and($parent->children)->toHaveCount(1);

    $fresh = ElqParent::find($parent->getKey());
    expect($fresh->children)->toHaveCount(1)
        ->and($fresh->children->first()->label)->toBe('first')
        ->and(ElqChild::query()->getQuery()->from)->not->toBeNull();
});

test('insert on an embedded model is delegated to the parent relation', function () {
    $parent = ElqParent::create(['name' => 'p']);
    $child = new ElqChild(['label' => 'direct']);
    $child->setParentRelation($parent->children());

    $result = $child->newQuery()->insert($child->getAttributes());

    expect($result)->toBeTrue()
        ->and(ElqParent::find($parent->getKey())->children->pluck('label')->all())->toBe(['direct']);
});

test('increment and decrement on a top level query update the stored value', function () {
    $parent = ElqParent::create(['name' => 'counter', 'n' => 5]);

    $incremented = ElqParent::query()->where('_id', $parent->getKey())->increment('n', 3);
    expect($incremented)->toBe(1)
        ->and(ElqParent::find($parent->getKey())->n)->toBe(8);

    $decremented = ElqParent::query()->where('_id', $parent->getKey())->decrement('n', 2);
    expect($decremented)->toBe(1)
        ->and(ElqParent::find($parent->getKey())->n)->toBe(6);
});

test('increment and decrement on an embedded model persist through the parent relation', function () {
    $parent = ElqParent::create(['name' => 'p']);
    $child = $parent->children()->create(['label' => 'c', 'n' => 1]);

    $child->increment('n', 4);
    expect(ElqParent::find($parent->getKey())->children->first()->n)->toBe(5);

    $child->decrement('n', 2);
    expect(ElqParent::find($parent->getKey())->children->first()->n)->toBe(3);
});

test('increment and decrement with an expression column on an embedded model are a no-op', function () {
    $parent = ElqParent::create(['name' => 'p']);
    $child = $parent->children()->create(['label' => 'c', 'n' => 1]);

    expect($child->newQuery()->increment(new Expression('n')))->toBe(0)
        ->and($child->newQuery()->decrement(new Expression('n')))->toBe(0)
        ->and($child->n)->toBe(1)
        ->and(ElqParent::find($parent->getKey())->children->first()->n)->toBe(1);
});

test('chunkById iterates over every document in chunks', function () {
    foreach (range(1, 5) as $i) {
        ElqParent::create(['name' => 'chunk', 'i' => $i]);
    }

    $chunks = [];
    ElqParent::query()->where('name', 'chunk')->chunkById(2, function ($models) use (&$chunks) {
        $chunks[] = $models->pluck('i')->sort()->values()->all();
    });

    expect($chunks)->toHaveCount(3)
        ->and(array_merge(...$chunks))->toEqualCanonicalizing([1, 2, 3, 4, 5]);
});

test('raw returning a cursor is hydrated into a collection of models', function () {
    ElqParent::create(['name' => 'raw-a']);
    ElqParent::create(['name' => 'raw-b']);

    $results = ElqParent::raw(fn ($collection) => $collection->find(['name' => ['$in' => ['raw-a', 'raw-b']]]));

    expect($results)->toBeInstanceOf(Collection::class)
        ->and($results)->toHaveCount(2)
        ->and($results->first())->toBeInstanceOf(ElqParent::class)
        ->and($results->pluck('name')->sort()->values()->all())->toBe(['raw-a', 'raw-b']);
});

test('raw returning a BSON document is converted to a single model', function () {
    ElqParent::create(['name' => 'raw-bson']);

    $result = ElqParent::raw(fn ($collection) => $collection->findOne(
        ['name' => 'raw-bson'],
        ['typeMap' => ['root' => BSONDocument::class, 'document' => BSONDocument::class, 'array' => 'array']]
    ));

    expect($result)->toBeInstanceOf(ElqParent::class)
        ->and($result->name)->toBe('raw-bson')
        ->and($result->exists)->toBeTrue();
});

test('raw returning an array with an _id is converted to a single model', function () {
    ElqParent::create(['name' => 'raw-array']);

    $result = ElqParent::raw(fn ($collection) => $collection->findOne(
        ['name' => 'raw-array'],
        ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
    ));

    expect($result)->toBeInstanceOf(ElqParent::class)
        ->and($result->name)->toBe('raw-array');
});

test('raw returns scalar results untouched', function () {
    ElqParent::create(['name' => 'raw-count']);

    expect(ElqParent::raw(fn ($collection) => $collection->countDocuments(['name' => 'raw-count'])))->toBe(1);
});

test('raw requires a MongoDB query builder', function () {
    $builder = new Builder(new BaseQueryBuilder(DB::connection('mongodb')));
    $builder->setModel(new ElqParent);

    $builder->raw();
})->throws(LogicException::class, 'raw() requires a MongoDB query builder');

test('update on a model without timestamps does not add an updated_at column', function () {
    $model = ElqUntimed::create(['name' => 'before']);

    $updated = ElqUntimed::query()->where('_id', $model->getKey())->update(['name' => 'after']);

    $raw = ElqUntimed::raw(fn ($collection) => $collection->findOne(
        ['name' => 'after'],
        ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
    ));

    expect($updated)->toBe(1)
        ->and($raw)->toBeInstanceOf(ElqUntimed::class)
        ->and($raw->getAttributes())->not->toHaveKey('updated_at')
        ->and($raw->getAttributes())->not->toHaveKey('created_at');
});
