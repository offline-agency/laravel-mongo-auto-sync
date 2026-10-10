<?php

use Illuminate\Http\Request;
use Tests\Models\MiniCategory;

it('can be loaded again without redeclaring the global helpers', function () {
    $before = getTranslatedContent(['en' => 'car']);

    $result = require dirname(__DIR__, 2).'/src/syncUtils.php';

    expect($result)->toBe(1)
        ->and(getTranslatedContent(['en' => 'car']))->toBe($before)
        ->and(function_exists('getSiteGeneralValueByKey'))->toBeTrue();
});

it('builds the request to sync on target through the global helper', function () {
    $request = new Request;
    $request->merge(['name' => 'News']);

    $out = getRequestToBeSync('ref-1', MiniCategory::class, $request, 'categories');

    expect(json_decode((string) $out->input('categories'), true))->toBe([
        'ref_id' => 'ref-1',
        'name' => 'News',
        'slug' => null,
        'description' => null,
    ]);
});

it('checks request readiness through the global helper', function () {
    $ready = new Request;
    $ready->merge(['_token' => 't', 'name-en' => 'x']);
    $notReady = new Request;
    $notReady->merge(['name' => 'x']);

    expect(isRequestReadyToBeProcessed($ready))->toBeTrue()
        ->and(isRequestReadyToBeProcessed($notReady))->toBeFalse();
});

it('returns the same request from removeSubCollectionInput', function () {
    $request = new Request;

    expect(removeSubCollectionInput($request))->toBe($request);
});

it('merges additional data through prepareRequest', function () {
    $request = new Request;
    $request->merge(['a' => 1]);

    expect(prepareRequest($request, ['b' => 2])->all())->toBe(['a' => 1, 'b' => 2]);
});

it('keeps the legacy image and site value helpers as empty-string stubs', function () {
    expect(getFullUrlImgByKey('logo'))->toBe('')
        ->and(getFullUrlImgByKey(null))->toBe('')
        ->and(getSiteGeneralValueByKey('title'))->toBe('');
});
