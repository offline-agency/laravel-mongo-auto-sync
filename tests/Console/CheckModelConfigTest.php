<?php

use Illuminate\Support\Facades\Artisan;

test('passes with the test models', function () {
    expect(Artisan::call('mongo-sync:check-config'))->toBe(0);
    expect(Artisan::output())->toContain('Check completed.')->not->toContain('Error in');
});

test('fails with a readable message on a broken model', function () {
    config()->set('laravel-mongo-auto-sync.model_path', __DIR__.'/../BrokenModels');
    config()->set('laravel-mongo-auto-sync.model_namespace', 'Tests\BrokenModels');

    expect(Artisan::call('mongo-sync:check-config'))->toBe(1);
    expect(Artisan::output())->toContain("Error in Tests\\BrokenModels\\BrokenTarget: Relation author in Tests\\BrokenModels\\BrokenTarget missing 'modelTarget' for target sync");
});

test('fails when the model path does not exist', function () {
    config()->set('laravel-mongo-auto-sync.model_path', __DIR__.'/../path-that-does-not-exist');

    expect(Artisan::call('mongo-sync:check-config'))->toBe(1);
    expect(Artisan::output())->toContain('not found');
});
