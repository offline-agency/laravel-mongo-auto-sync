<?php

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Tests\Models\Address;
use Tests\Models\CmpNativeModel;
use Tests\Models\CmpPackageModel;
use Tests\Models\User;

beforeEach(fn () => DB::table('cmp_models')->truncate());

dataset('builder models', [
    'native' => [CmpNativeModel::class],
    'package' => [CmpPackageModel::class],
]);

/**
 * @param  class-string  $class
 * @return array<string, mixed>
 */
function builderScenario(string $class): array
{
    DB::table('cmp_models')->truncate();
    $id = new ObjectId('650000000000000000000042');
    DB::connection('mongodb')->getCollection('cmp_models')->insertMany([
        ['_id' => $id, 'first' => 'Ada', 'n' => 1, 'published_at' => new UTCDateTime(1700000000000)],
        ['_id' => new ObjectId('650000000000000000000043'), 'first' => 'Bob', 'n' => 2],
    ]);
    $cursor = $class::raw(fn ($collection) => $collection->find([], ['sort' => ['n' => 1]]));
    $aggregate = $class::raw(fn ($collection) => $collection->aggregate([['$sort' => ['n' => 1]]]));
    $one = $class::raw(fn ($collection) => $collection->findOne(['_id' => $id]));
    $array = $class::raw(fn ($collection) => $collection->findOne(['_id' => $id], ['typeMap' => ['root' => 'array', 'document' => 'array']]));
    $scalar = $class::raw(fn ($collection) => $collection->countDocuments());

    return [
        'cursor class' => get_debug_type($cursor),
        'cursor keys' => $cursor->map(fn ($m) => array_keys($m->getAttributes()))->all(),
        'cursor ids' => $cursor->map(fn ($m) => (string) $m->getKey())->all(),
        'cursor date' => $cursor->first()->published_at?->toIso8601String(),
        'aggregate ids' => $aggregate->map(fn ($m) => (string) $m->getKey())->all(),
        'findOne class' => get_debug_type($one),
        'findOne key' => (string) $one?->getKey(),
        'findOne attribute keys' => array_keys($one?->getAttributes() ?? []),
        'array key' => (string) $array?->getKey(),
        'scalar' => $scalar,
    ];
}

test('raw() hydrates cursors, documents and arrays like the native builder', function () {
    $native = builderScenario(CmpNativeModel::class);
    $package = builderScenario(CmpPackageModel::class);

    expect($package)->toBe(array_merge($native, [
        'cursor class' => str_replace('CmpNativeModel', 'CmpPackageModel', $native['cursor class']),
        'findOne class' => CmpPackageModel::class,
    ]));
    expect($native['cursor ids'])->toBe(['650000000000000000000042', '650000000000000000000043']);
    expect($native['findOne key'])->toBe('650000000000000000000042');
    expect($native['scalar'])->toBe(2);
});

test('an explicit updated_at inside $set is kept without a path conflict', function (string $class) {
    $model = $class::create(['first' => 'Ada']);
    $explicit = new UTCDateTime(1600000000000);

    $updated = $class::where('id', $model->getKey())->update(['$set' => ['updated_at' => $explicit, 'first' => 'Grace']]);

    $raw = DB::connection('mongodb')->getCollection('cmp_models')->findOne(['_id' => new ObjectId((string) $model->getKey())]);
    expect($updated)->toBe(1);
    expect($raw['first'])->toBe('Grace');
    expect($raw['updated_at'])->toEqual($explicit);
})->with('builder models');

test('firstOrCreate, chunkById and paginate behave like the native builder', function (string $class) {
    foreach (range(1, 7) as $n) {
        $class::create(['first' => "user{$n}", 'n' => $n]);
    }

    $existing = $class::firstOrCreate(['first' => 'user3']);
    $created = $class::firstOrCreate(['first' => 'new'], ['n' => 99]);
    $chunked = [];
    $class::query()->chunkById(3, function ($models) use (&$chunked) {
        foreach ($models as $model) {
            $chunked[] = $model->first;
        }
    });
    $paged = [];
    foreach ([1, 2, 3] as $page) {
        $paged = array_merge($paged, $class::orderBy('n')->paginate(3, ['*'], 'page', $page)->pluck('first')->all());
    }

    expect($existing->n)->toBe(3);
    expect($created->wasRecentlyCreated)->toBeTrue();
    expect($class::count())->toBe(8);
    expect($chunked)->toHaveCount(8)->and(array_unique($chunked))->toHaveCount(8);
    expect($paged)->toBe(['user1', 'user2', 'user3', 'user4', 'user5', 'user6', 'user7', 'new']);
})->with('builder models');

test('incrementing an embedded model persists the amount and the extra columns with one save', function () {
    $user = User::create(['name' => 'embed']);
    $address = $user->addresses()->save(new Address(['city' => 'Paris', 'visits' => 1]));
    $saves = 0;
    Address::saved(function () use (&$saves) {
        $saves++;
    });

    $address->increment('visits', 2, ['note' => 'after increment']);
    $address->decrement('visits', 1, ['checked' => true]);

    $stored = User::find($user->id)->addresses->first();
    expect($stored->visits)->toBe(2);
    expect($stored->note)->toBe('after increment');
    expect($stored->checked)->toBeTrue();
    expect($address->isDirty())->toBeFalse();
    expect($address->getOriginal('visits'))->toBe(2);
    expect($saves)->toBe(2);
});
