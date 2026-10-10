<?php

use Illuminate\Database\Eloquent\Collection;
use OfflineAgency\MongoAutoSync\Relationships\EmbedsMany;
use Tests\Models\ElqChild;
use Tests\Models\ElqParent;

beforeEach(function () {
    ElqParent::truncate();
});

afterEach(function () {
    ElqParent::truncate();
});

test('attach returns the given model untouched when saving is cancelled', function () {
    ElqChild::saving(fn (ElqChild $child) => $child->getAttribute('blocked') !== true);
    $parent = ElqParent::create(['name' => 'p']);
    $child = new ElqChild(['label' => 'nope', 'blocked' => true]);

    $result = $parent->children()->attach($child);

    expect($result)->toBe($child)
        ->and($child->exists)->toBeFalse()
        ->and(ElqParent::find($parent->getKey())->children)->toHaveCount(0);
});

test('count of an embeds many relation with nothing embedded is zero', function () {
    $parent = ElqParent::create(['name' => 'empty']);

    expect($parent->children()->count())->toBe(0);

    $parent->children()->create(['label' => 'one']);
    expect($parent->children()->count())->toBe(1);
});

test('embeds many forwards non collection methods to the underlying query', function () {
    $parent = ElqParent::create(['name' => 'p']);
    $relation = $parent->children();

    $returned = $relation->orderBy('label', 'desc');

    expect($returned)->toBe($relation)
        ->and($relation->getQuery()->getQuery()->orders)->toBe(['label' => -1]);
});

test('embeds many forwards collection methods to the embedded results', function () {
    $parent = ElqParent::create(['name' => 'p']);
    $parent->children()->create(['label' => 'b']);
    $parent->children()->create(['label' => 'a']);

    /** @var EmbedsMany $relation */
    $relation = $parent->children();

    expect($relation->pluck('label')->all())->toBe(['b', 'a']);
});

test('embeds one eager loading initialises and matches the embedded model', function () {
    $with = ElqParent::create(['name' => 'with']);
    $with->profile()->save(new ElqChild(['label' => 'profile']));
    ElqParent::create(['name' => 'without']);

    $parents = ElqParent::with('profile')->orderBy('name')->get();

    expect($parents)->toBeInstanceOf(Collection::class)
        ->and($parents->first()->name)->toBe('with')
        ->and($parents->first()->relationLoaded('profile'))->toBeTrue()
        ->and($parents->first()->getRelation('profile')->label)->toBe('profile')
        ->and($parents->last()->relationLoaded('profile'))->toBeTrue()
        ->and($parents->last()->getRelation('profile'))->toBeNull();
});

test('embeds one initRelation sets a null relation on every model', function () {
    $models = [new ElqParent, new ElqParent];

    $result = (new ElqParent)->profile()->initRelation($models, 'profile');

    expect($result)->toBe($models)
        ->and($models[0]->getRelation('profile'))->toBeNull()
        ->and($models[1]->relationLoaded('profile'))->toBeTrue();
});

test('deleting an empty embeds one relation deletes nothing', function () {
    $parent = ElqParent::create(['name' => 'p']);

    expect($parent->profile()->delete())->toBe(0);
});

test('deleting an embeds one relation removes the embedded document', function () {
    $parent = ElqParent::create(['name' => 'p']);
    $parent->profile()->save(new ElqChild(['label' => 'x']));

    expect($parent->profile()->delete())->toBe(1)
        ->and($parent->profile)->toBeNull()
        ->and(ElqParent::find($parent->getKey())->profile)->toBeNull();
});
