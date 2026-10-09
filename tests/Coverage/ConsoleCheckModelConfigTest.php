<?php

use Illuminate\Support\Facades\Artisan;
use Tests\ConsoleFixtures\UnreadableDirectoryStream;

afterEach(function () {
    UnreadableDirectoryStream::unregister();
});

test('reports every invalid relation, including nested and other_models classes', function () {
    config()->set('laravel-mongo-auto-sync.model_path', __DIR__.'/../ConsoleFixtures/CheckConfig');
    config()->set('laravel-mongo-auto-sync.model_namespace', 'Tests\ConsoleFixtures\CheckConfig');
    config()->set('laravel-mongo-auto-sync.other_models', [
        'gizmo' => ['model_namespace' => 'Tests\ConsoleFixtures\OtherModels'],
    ]);

    expect(Artisan::call('mongo-sync:check-config'))->toBe(1);

    $ns = 'Tests\ConsoleFixtures\CheckConfig\\';
    expect(Artisan::output())
        ->toContain("Error in {$ns}MissingType: Relation author in {$ns}MissingType missing 'type'")
        ->toContain("Error in {$ns}InvalidType: Relation author in {$ns}InvalidType has invalid type 'BelongsTo'")
        ->toContain("Error in {$ns}MissingModel: Relation author in {$ns}MissingModel missing 'model'")
        ->toContain("Error in {$ns}UnknownModel: Relation author in {$ns}UnknownModel refers to non-existent model 'Tests\Models\DoesNotExist'")
        ->toContain("Error in {$ns}UnknownTarget: Relation author in {$ns}UnknownTarget refers to non-existent target model 'Tests\Models\DoesNotExist'")
        ->toContain("Error in {$ns}Nested\NestedMissingType: Relation author in {$ns}Nested\NestedMissingType missing 'type'")
        ->toContain("Checking {$ns}NotAModel...")
        ->not->toContain("Error in {$ns}NotAModel")
        ->toContain('Checking Tests\ConsoleFixtures\OtherModels\Gizmo...')
        ->not->toContain('Error in Tests\ConsoleFixtures\OtherModels\Gizmo')
        ->toContain('Check completed.');
});

test('fails when the model path is not a string', function () {
    config()->set('laravel-mongo-auto-sync.model_path', ['not', 'a', 'path']);

    expect(Artisan::call('mongo-sync:check-config'))->toBe(1);
    expect(Artisan::output())->toContain('Model path is not a string')->not->toContain('Check completed.');
});

test('fails when the model directory cannot be read', function () {
    UnreadableDirectoryStream::register();
    config()->set('laravel-mongo-auto-sync.model_path', UnreadableDirectoryStream::PROTOCOL.'://models');

    expect(Artisan::call('mongo-sync:check-config'))->toBe(1);
    expect(Artisan::output())
        ->toContain('Directory '.UnreadableDirectoryStream::PROTOCOL.'://models is not readable')
        ->not->toContain('Check completed.');
});
