<?php

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Tests\Models\CmpNativeModel;
use Tests\Models\CmpPackageModel;

beforeEach(fn () => DB::table('cmp_models')->truncate());

dataset('models', [
    'package' => [CmpPackageModel::class],
    'native' => [CmpNativeModel::class],
]);

test('an id or _id containing a MongoDB operator is rejected before saving', function (string $class, string $key, array $value) {
    $model = new $class;

    expect(fn () => $model->setAttribute($key, $value))
        ->toThrow(InvalidArgumentException::class, 'cannot contain the MongoDB operator');
    expect(DB::table('cmp_models')->count())->toBe(0);
})->with('models')->with([
    'id top level' => ['id', ['$ne' => null]],
    '_id top level' => ['_id', ['$gt' => '']],
    'id nested' => ['id', ['a' => ['b' => ['$where' => 'sleep(1)']]]],
]);

test('mass assignment of an operator id is rejected and nothing is persisted', function (string $class) {
    expect(fn () => $class::create(['id' => ['$ne' => null], 'first' => 'x']))
        ->toThrow(InvalidArgumentException::class, 'cannot contain the MongoDB operator "$ne"');
    expect(DB::table('cmp_models')->count())->toBe(0);
})->with('models');

/**
 * Observable behaviour of a model class for the differential tests (ids normalised to strings).
 *
 * @param  class-string  $class
 * @return array<string, mixed>
 */
function modelScenario(string $class): array
{
    DB::table('cmp_models')->truncate();
    $collection = DB::connection('mongodb')->getCollection('cmp_models');
    $hex = '650000000000000000000001';

    $fromHex = $class::create(['id' => $hex]);
    $plain = $class::create(['id' => 'plain-string-id']);
    $composite = $class::create(['id' => ['tenant' => 't1', 'number' => 7]]);

    $model = $class::create([
        'first' => 'Ada',
        'last' => 'Lovelace',
        'nickname' => 'COUNTESS',
        'meta' => ['lang' => 'en'],
        'price' => '12.5',
        'published_at' => '2026-01-02 03:04:05',
        'temp' => 'remove me',
    ]);
    $model->unset('temp');
    $model->setAttribute('meta.city', 'London');
    $model->save();
    $fresh = $class::find($model->getKey());
    $raw = $collection->findOne(['_id' => new ObjectId((string) $model->getKey())], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);

    return [
        'hex id type' => get_debug_type($fromHex->getAttributes()['id']),
        'hex id found' => $class::find($hex)?->getKey(),
        'plain id' => $plain->getAttributes()['id'],
        'plain id found' => $class::find('plain-string-id')?->getKey(),
        'composite id' => json_encode($composite->getKey()),
        'stored ids' => array_map(fn ($doc) => get_debug_type($doc['_id']), $collection->find([], ['typeMap' => ['root' => 'array']])->toArray()),
        'accessor' => $fresh->full_name,
        'mutator' => $fresh->nickname,
        'dot read in memory' => $model->getAttribute('meta.city'),
        'meta' => $fresh->meta,
        'unset in memory' => $fresh->temp,
        'raw keys' => array_keys($raw),
        'raw meta' => $raw['meta'],
        'published_at' => $fresh->published_at->format('Y-m-d H:i:s'),
        'raw published_at type' => get_debug_type($raw['published_at']),
        'price' => $fresh->price,
        'raw price type' => get_debug_type($raw['price']),
    ];
}

test('ids, accessors, mutators, dot notation, unset, dates and decimals behave like the native model', function () {
    $native = modelScenario(CmpNativeModel::class);
    $package = modelScenario(CmpPackageModel::class);

    expect($package)->toBe($native);
    // sanity: the native reference really exercised the features
    expect($native['hex id type'])->toBe(ObjectId::class);
    expect($native['accessor'])->toBe('Ada Lovelace');
    expect($native['mutator'])->toBe('countess');
    expect($native['raw keys'])->not->toContain('temp');
    expect($native['raw price type'])->toBe(Decimal128::class);
    expect($native['raw published_at type'])->toBe(UTCDateTime::class);
});

test('an unset attribute that is assigned again is persisted', function (string $class) {
    $model = $class::create(['first' => 'Ada', 'temp' => 'x']);
    $model->unset('temp');
    $model->temp = 'back';
    $model->save();

    expect($class::find($model->getKey())->temp)->toBe('back');
})->with('models');
