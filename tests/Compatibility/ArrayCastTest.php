<?php

use Illuminate\Support\Facades\DB;
use Tests\Models\CmpNativeArrayCast;
use Tests\Models\CmpPackageArrayCast;

test('the deprecated array cast keeps the native behaviour: deprecation and JSON string storage', function (string $class) {
    DB::table('cmp_array_casts')->truncate();
    $deprecations = [];
    set_error_handler(function (int $errno, string $message) use (&$deprecations) {
        $deprecations[] = $message;

        return true;
    }, E_USER_DEPRECATED);

    try {
        $model = $class::create(['tags' => ['a', 'b']]);
        $fresh = $class::find($model->getKey());
    } finally {
        restore_error_handler();
    }

    $raw = DB::connection('mongodb')->getCollection('cmp_array_casts')->findOne([], ['typeMap' => ['root' => 'array']]);
    expect($deprecations)->not->toBeEmpty();
    expect($deprecations[0])->toContain('The "array" cast on attribute "tags"');
    expect($raw['tags'])->toBe('["a","b"]');
    expect($fresh->tags)->toBe(['a', 'b']);
})->with([
    'native' => [CmpNativeArrayCast::class],
    'package' => [CmpPackageArrayCast::class],
]);
