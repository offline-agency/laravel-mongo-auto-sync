<?php

use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * A plain model whose stored value of $key is $value (no request: the stored value is the one cast).
 */
function narrowingModel(string $key, mixed $value): MDModel
{
    $model = new class extends MDModel
    {
        protected $collection = 'narrowing_samples';
    };
    $model->setRawAttributes([$key => $value]);

    return $model;
}

it('rejects $items and $mongoRelation that are not arrays', function (string $property, string $method) {
    $model = new class extends MDModel
    {
        protected $items = 'not an array';

        protected $mongoRelation = 'not an array';
    };

    expect(fn () => $model->$method())->toThrow(InvalidConfigurationException::class, 'The $'.$property.' property of '.$model::class.' must be an array.');
})->with([
    'items' => ['items', 'getItems'],
    'mongoRelation' => ['mongoRelation', 'getMongoRelation'],
]);

it('rejects a relationship configuration that is not an array', function () {
    $model = new class extends MDModel
    {
        protected $mongoRelation = ['category' => 'EmbedsOne'];
    };

    expect(fn () => $model->getMongoRelation())->toThrow(InvalidConfigurationException::class, "The configuration of relationship 'category' in ".$model::class.' must be an array.');
});

it('returns the configured items and relationships as declared', function () {
    $model = new class extends MDModel
    {
        protected $items = ['name' => [], 0 => []];

        protected $mongoRelation = ['category' => ['type' => 'EmbedsOne', 'model' => 'X']];
    };

    expect(array_keys($model->getItems()))->toBe(['name', 0])
        ->and($model->getMongoRelation())->toBe(['category' => ['type' => 'EmbedsOne', 'model' => 'X']]);
});

it('casts numeric and Int64 carbon dates to UTCDateTime', function (mixed $stored) {
    $value = narrowingModel('published_at', $stored)->castValueToBeSaved('published_at', ['is-carbon-date' => true], '');

    expect($value)->toBeInstanceOf(UTCDateTime::class)
        ->and((string) $value)->toBe('1700000000000');
})->with([
    'int' => [1700000000000],
    'numeric string' => ['1700000000000'],
    'Int64' => [new Int64(1700000000000)],
]);

it('rejects a carbon date that is neither a date nor a number', function () {
    expect(fn () => narrowingModel('published_at', ['2024-01-01'])->castValueToBeSaved('published_at', ['is-carbon-date' => true], ''))
        ->toThrow(InvalidRequestException::class, "Invalid date for 'published_at': got array.");
});

it('rejects an array field that holds a scalar', function () {
    expect(fn () => narrowingModel('tags', 'php')->castValueToBeSaved('tags', ['is-array' => true], ''))
        ->toThrow(InvalidRequestException::class, "Invalid value for 'tags': expected an array, got string.");
});

it('reads the documents of a relationship payload as the parser iterates them', function (string $payload, array $expected) {
    expect(SyncHelper::payloadDocuments('category', $payload))->toEqual($expected);
})->with([
    'list' => ['[{"ref_id":"a"},null]', [(object) ['ref_id' => 'a'], null]],
    'object' => ['{"x":{"ref_id":"a"}}', [(object) ['ref_id' => 'a']]],
    'null' => ['null', []],
    'not JSON' => ['not json', []],
]);

it('rejects relationship payloads that hold no documents', function (string $payload, string $message) {
    expect(fn () => SyncHelper::payloadDocuments('category', $payload))->toThrow(InvalidRequestException::class, $message);
})->with([
    'scalar' => ['5', "Invalid payload for relationship 'category': expected a JSON list or object, got int."],
    'list of scalars' => ['["a"]', "Invalid payload for relationship 'category': expected a JSON object or null for each document, got string."],
]);

it('casts like (string) but names the type of arrays and plain objects', function (mixed $value, string $expected) {
    expect(SyncHelper::toString($value))->toBe($expected);
})->with([
    'string' => ['a', 'a'],
    'int' => [5, '5'],
    'null' => [null, ''],
    'array' => [[1], 'array'],
    'object' => [new stdClass, 'stdClass'],
]);
