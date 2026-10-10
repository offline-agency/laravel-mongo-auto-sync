<?php

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\PackedArray;
use Tests\Models\CmpNativeParent;
use Tests\Models\CmpPackageParent;

/**
 * @param  class-string  $parent
 * @return array<string, mixed>
 */
function embeddedListScenario(string $parent, string $removal): array
{
    DB::table('cmp_parents')->truncate();
    $model = $parent::create(['name' => 'list']);
    $model->items()->create(['k' => 'a']);
    $middle = $model->items()->create(['k' => 'b']);
    $model->items()->create(['k' => 'c']);

    if ($removal === 'delete') {
        $middle->delete();
    } else {
        $model->items()->dissociate($middle);
    }
    $model->save();

    $added = $parent::find($model->getKey())->items()->create(['k' => 'd']);
    $raw = DB::connection('mongodb')->getCollection('cmp_parents')->findOne([], ['typeMap' => ['root' => 'bson']]);

    return [
        'stored as BSON array' => $raw->get('items') instanceof PackedArray,
        'create after removal' => $added->k,
        'items' => $parent::find($model->getKey())->items->pluck('k')->all(),
        'query on embedded field' => $parent::where('items.k', 'c')->count(),
    ];
}

test('removing an embedded item keeps the stored list an array, like native models', function (string $removal) {
    $native = embeddedListScenario(CmpNativeParent::class, $removal);
    $package = embeddedListScenario(CmpPackageParent::class, $removal);

    expect($native)->toBe(['stored as BSON array' => true, 'create after removal' => 'd', 'items' => ['a', 'c', 'd'], 'query on embedded field' => 1]);
    expect($package)->toBe($native);
})->with(['delete' => ['delete'], 'dissociate' => ['dissociate']]);
