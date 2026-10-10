<?php

use Illuminate\Support\Facades\DB;
use Tests\Models\CmpNativeParent;
use Tests\Models\CmpPackageParent;

/**
 * @param  class-string  $parent
 */
function seedAggregateScenario(string $parent): void
{
    DB::table('cmp_parents')->truncate();
    DB::table('cmp_notes')->truncate();
    $full = $parent::create(['name' => 'full']);
    $full->items()->create(['price' => 10]);
    $full->items()->create(['price' => 5]);
    $parent::create(['name' => 'empty']);
    $full->notes()->getRelated()::create(['parent_id' => (string) $full->getKey(), 'score' => 3]);
}

test('native embedded relations support withCount, aliases and withSum, including empty ones', function () {
    seedAggregateScenario(CmpNativeParent::class);

    $rows = CmpNativeParent::query()->withCount('items')->withCount('items as items_alias')->withSum('items', 'price')
        ->orderBy('name')->get()->map(fn ($m) => [$m->name, $m->items_count, $m->items_alias, $m->items_sum_price])->all();

    expect($rows)->toBe([['empty', 0, 0, null], ['full', 2, 2, 15]]);
});

test('package embedded relations are not recognised by native aggregates and fail explicitly', function (string $method, array $args) {
    seedAggregateScenario(CmpPackageParent::class);

    expect(fn () => CmpPackageParent::query()->{$method}(...$args)->get())
        ->toThrow(LogicException::class, 'EmbedsMany is not supported for relation aggregates.');
})->with([
    'withCount' => ['withCount', ['items']],
    'withSum' => ['withSum', ['items', 'price']],
]);

test('aggregates on referenced relations behave the same on native and package models', function () {
    $rows = [];
    foreach ([CmpNativeParent::class, CmpPackageParent::class] as $parent) {
        seedAggregateScenario($parent);
        $rows[$parent] = $parent::query()->withCount('notes')->withMax('notes', 'score')->withCount('notes as notes_alias')
            ->orderBy('name')->get()->map(fn ($m) => [$m->name, $m->notes_count, $m->notes_max_score, $m->notes_alias])->all();
    }

    expect($rows[CmpNativeParent::class])->toBe([['empty', 0, null, 0], ['full', 1, 3, 1]]);
    expect($rows[CmpPackageParent::class])->toBe($rows[CmpNativeParent::class]);
});

test('ordering by an aggregate computed after the read is rejected like on native models', function (string $parent) {
    expect(fn () => $parent::query()->withCount('notes')->orderBy('notes_count')->get())
        ->toThrow(LogicException::class);
})->with([
    'native' => [CmpNativeParent::class],
    'package' => [CmpPackageParent::class],
]);
