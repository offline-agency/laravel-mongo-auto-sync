<?php

use Illuminate\Support\Facades\Artisan;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\ModelNotFoundException;

beforeEach(function () {
    config()->set('laravel-mongo-auto-sync.model_path', __DIR__.'/../ConsoleFixtures/Catalog');
    config()->set('laravel-mongo-auto-sync.model_namespace', 'Tests\ConsoleFixtures\Catalog');
});

test('documents plain, multi-language and relation properties of a model', function () {
    expect(Artisan::call('model-doc:generate', ['collection_name' => 'gadget']))->toBe(0);

    expect(Artisan::output())->toContain(implode("\n", [
        '/**',
        '*',
        '* Plain Fields',
        '* ',
        '* @property string $id',
        '* @property string $name',
        '* @property array $title',
        '*',
        '*',
        '* Relationships',
        '*',
        '* @property MiniUser $owner',
        '* @property Tests\Models\MiniItem $parts',
        '*',
        '**/',
    ]));
});

test('omits the relationships block for a model without relations', function () {
    expect(Artisan::call('model-doc:generate', ['collection_name' => 'Plain']))->toBe(0);

    expect(Artisan::output())
        ->toContain("* @property string \$id\n* @property string \$label\n*\n*\n*")
        ->not->toContain('Relationships');
});

test('finds a model in a subdirectory of the model path under its sub-namespace', function () {
    expect(Artisan::call('model-doc:generate', ['collection_name' => 'widget']))->toBe(0);

    expect(Artisan::output())->toContain('* @property string $code');
});

test('finds a model registered in other_models', function () {
    config()->set('laravel-mongo-auto-sync.other_models', [
        'gizmo' => ['model_namespace' => 'Tests\ConsoleFixtures\OtherModels'],
    ]);

    expect(Artisan::call('model-doc:generate', ['collection_name' => 'Gizmo']))->toBe(0);

    expect(Artisan::output())->toContain('* @property string $serial');
});

test('fails when no model matches the collection name', function () {
    expect(fn () => Artisan::call('model-doc:generate', ['collection_name' => 'missing']))
        ->toThrow(ModelNotFoundException::class, 'Error missing Model not found');
});

test('fails when the model path is not configured', function () {
    config()->set('laravel-mongo-auto-sync.model_path', null);

    expect(fn () => Artisan::call('model-doc:generate', ['collection_name' => 'gadget']))
        ->toThrow(Exception::class, 'Config laravel-mongo-auto-sync.model_path is not set or invalid');
});

test('fails when the model path does not exist', function () {
    config()->set('laravel-mongo-auto-sync.model_path', __DIR__.'/../ConsoleFixtures/does-not-exist');

    expect(fn () => Artisan::call('model-doc:generate', ['collection_name' => 'gadget']))
        ->toThrow(InvalidConfigurationException::class, 'not found');
});
