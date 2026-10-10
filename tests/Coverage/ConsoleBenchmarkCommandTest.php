<?php

use Illuminate\Support\Facades\Artisan;

test('fails when the model class does not exist', function () {
    expect(Artisan::call('mongo-sync:benchmark', ['model' => 'Tests\Models\DoesNotExist', 'count' => 1]))->toBe(1);

    expect(Artisan::output())->toContain('Class Tests\Models\DoesNotExist does not exist.');
});

test('fails when the class is not an Eloquent model', function () {
    expect(Artisan::call('mongo-sync:benchmark', ['model' => stdClass::class, 'count' => 1]))->toBe(1);

    expect(Artisan::output())->toContain('Class stdClass is not an Eloquent model.');
});
