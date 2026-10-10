<?php

use Illuminate\Support\Facades\Artisan;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\ModelNotFoundException;
use Tests\ConsoleFixtures\Catalog\Nested\Widget;
use Tests\ConsoleFixtures\Catalog\Plain;
use Tests\ConsoleFixtures\OtherModels\Gizmo;

beforeEach(function () {
    config()->set('laravel-mongo-auto-sync.model_path', __DIR__.'/../ConsoleFixtures/Catalog');
    config()->set('laravel-mongo-auto-sync.model_namespace', 'Tests\ConsoleFixtures\Catalog');
    Widget::truncate();
    Plain::truncate();
    Gizmo::truncate();
});

test('destroys every document of a model found in a subdirectory of the model path', function () {
    foreach (['a', 'b'] as $code) {
        $widget = new Widget;
        $widget->code = $code;
        $widget->save();
    }
    $ids = Widget::all()->pluck('id')->all();

    expect(Artisan::call('drop:collection', ['collection_name' => 'widget']))->toBe(0);

    $output = Artisan::output();
    expect(Widget::count())->toBe(0);
    expect($output)
        ->toContain('1) Destroy item document with id #'.$ids[0])
        ->toContain('2) Destroy item document with id #'.$ids[1]);
});

test('destroys the documents of a model registered in other_models', function () {
    config()->set('laravel-mongo-auto-sync.other_models', [
        'gizmo' => ['model_namespace' => 'Tests\ConsoleFixtures\OtherModels'],
    ]);
    $gizmo = new Gizmo;
    $gizmo->serial = 'S-1';
    $gizmo->save();

    expect(Artisan::call('drop:collection', ['collection_name' => 'Gizmo']))->toBe(0);

    expect(Gizmo::count())->toBe(0);
    expect(Artisan::output())->toContain('1) Destroy item document with id #'.$gizmo->id);
});

test('warns when the collection is already empty', function () {
    expect(Artisan::call('drop:collection', ['collection_name' => 'Plain']))->toBe(0);

    expect(Artisan::output())->toContain('No record found on collection plain');
});

test('fails when no model matches the collection name', function () {
    expect(fn () => Artisan::call('drop:collection', ['collection_name' => 'missing']))
        ->toThrow(ModelNotFoundException::class, 'Error missing Model not found');
});

test('fails when the model path is not configured', function () {
    config()->set('laravel-mongo-auto-sync.model_path', null);

    expect(fn () => Artisan::call('drop:collection', ['collection_name' => 'widget']))
        ->toThrow(Exception::class, 'Config laravel-mongo-auto-sync.model_path is not set or invalid');
});

test('fails when the model path does not exist', function () {
    $path = __DIR__.'/../ConsoleFixtures/does-not-exist';
    config()->set('laravel-mongo-auto-sync.model_path', $path);

    expect(fn () => Artisan::call('drop:collection', ['collection_name' => 'widget']))
        ->toThrow(InvalidConfigurationException::class, 'Error directory '.$path.' not found');
});
