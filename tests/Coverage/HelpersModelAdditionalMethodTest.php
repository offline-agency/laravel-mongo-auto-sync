<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use Tests\Models\Category;
use Tests\Models\HlpPlainBuilderSample;
use Tests\Models\HlpSample;
use Tests\Models\HlpScalarRawSample;
use Tests\Models\MiniArticle;
use Tests\Models\MiniCategory;

function hlpSeedSamples(int $count): void
{
    DB::connection('mongodb')->table('hlp_samples')->truncate();
    for ($i = 1; $i <= $count; $i++) {
        $sample = new HlpSample;
        $sample->name = 'sample '.$i;
        $sample->save();
    }
}

afterEach(function () {
    DB::connection('mongodb')->table('hlp_samples')->truncate();
    DB::connection('mongodb')->table('hlp_plain_builder_samples')->truncate();
});

it('rejects a non positive number of random rows', function () {
    hlpSeedSamples(2);

    (new HlpSample)->getRandomRow(0);
})->throws(InvalidRequestException::class, 'Invalid # of random record requested');

it('rejects more random rows than the collection holds', function () {
    hlpSeedSamples(3);

    (new HlpSample)->getRandomRow(4);
})->throws(InvalidRequestException::class, 'bigger than the count collection record ( 3)');

it('returns a single hydrated model when one random row is requested', function () {
    hlpSeedSamples(3);

    $row = (new HlpSample)->getRandomRow(1);

    expect($row)->toBeInstanceOf(HlpSample::class)
        ->and($row->exists)->toBeTrue()
        ->and($row->getKey())->not->toBeNull()
        ->and($row->name)->toStartWith('sample ');
});

it('returns a collection of distinct models when several random rows are requested', function () {
    hlpSeedSamples(3);

    $rows = (new HlpSample)->getRandomRow(2);

    expect($rows)->toBeInstanceOf(Collection::class)
        ->and($rows)->toHaveCount(2)
        ->and($rows->pluck('id')->unique())->toHaveCount(2)
        ->and($rows->first())->toBeInstanceOf(HlpSample::class);
});

it('requires the package builder to pick random rows', function () {
    DB::connection('mongodb')->table('hlp_plain_builder_samples')->truncate();
    $sample = new HlpPlainBuilderSample;
    $sample->name = 'plain';
    $sample->save();

    (new HlpPlainBuilderSample)->getRandomRow(1);
})->throws(LogicException::class, 'getRandomRow() requires the MongoAutoSync Eloquent builder');

test('getRandomRow throws when the builder does not return a collection of models', function () {
    $sample = new HlpScalarRawSample;
    $sample->getConnection()->table('hlp_scalar_raw_samples')->truncate();
    $sample->getConnection()->table('hlp_scalar_raw_samples')->insert(['name' => 'one']);

    $sample->getRandomRow(1);
})->throws(LogicException::class, 'getRandomRow() expected the aggregation to return a collection of models');

it('reads the stored value when no request has been set', function () {
    $model = new HlpSample;
    $model->setRawAttributes(['name' => 'stored']);

    expect($model->getRequest())->toBeNull()
        ->and($model->getObjValueToBeSaved('name', MiniCategory::class))->toBe('stored');
});

it('casts a date string to UTCDateTime for an is-md field', function () {
    $model = new HlpSample;
    $model->setRawAttributes(['published' => '2024-05-01 10:00:00']);

    $value = $model->castValueToBeSaved('published', ['is-md' => true], MiniCategory::class);

    expect($value)->toBeInstanceOf(UTCDateTime::class)
        ->and($value->toDateTime()->format('Y-m-d H:i:s'))->toBe('2024-05-01 10:00:00');
});

it('keeps an UTCDateTime value as is for an is-carbon-date field', function () {
    $date = new UTCDateTime(1700000000000);
    $model = new HlpSample;
    $model->setRawAttributes(['when' => $date]);

    $value = $model->castValueToBeSaved('when', ['is-carbon-date' => true], MiniCategory::class);

    expect($value)->toBe($date);
});

it('encodes the ref ids of every embedded item of an EmbedsMany relation', function () {
    $withRef = new MiniArticle;
    $withRef->setAttribute('ref_id', 'article-1');
    $withoutRef = new MiniArticle;
    $withoutRef->setAttribute('title', 'no ref');

    $category = new Category;
    $category->setRelation('articles', new Collection([$withRef, $withoutRef]));

    $json = $category->getObjWithRefId('articles', $category->getMongoRelation()['articles']);

    expect(json_decode($json, true))->toBe([
        ['ref_id' => 'article-1'],
        ['ref_id' => null],
    ]);
});

it('returns an empty string when the embedded value cannot be JSON encoded', function () {
    $model = new HlpSample;
    $model->setRawAttributes(['blob' => "\xB1\x31"]);

    expect($model->getObjWithRefId('blob', ['type' => 'EmbedsOne']))->toBe('');
});
