<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Events\Dispatcher;
use MongoDB\BSON\ObjectId;
use Tests\Models\Address;
use Tests\Models\Book;
use Tests\Models\Client;
use Tests\Models\Group;
use Tests\Models\Item;
use Tests\Models\Photo;
use Tests\Models\Role;
use Tests\Models\User;

afterEach(function () {
    Mockery::close();

    User::truncate();
    Book::truncate();
    Item::truncate();
    Role::truncate();
    Client::truncate();
    Group::truncate();
    Photo::truncate();
});

test('embeds many save', function () {
    $user = User::create(['name' => 'John Doe']);
    $address = new Address(['city' => 'London']);

    $address->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($address), Mockery::any());
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.saving: '.get_class($address), $address)
        ->andReturn(true);
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.creating: '.get_class($address), $address)
        ->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with('eloquent.created: '.get_class($address), $address);
    $events->shouldReceive('dispatch')->once()->with('eloquent.saved: '.get_class($address), $address);

    $address = $user->addresses()->save($address);
    $address->unsetEventDispatcher();

    expect($user->addresses)->not->toBeNull();
    expect($user->addresses)->toBeInstanceOf(Collection::class);
    expect($user->addresses->pluck('city')->all())->toEqual(['London']);
    expect($address->created_at)->toBeInstanceOf(DateTime::class);
    expect($address->updated_at)->toBeInstanceOf(DateTime::class);
    expect($address->_id)->not->toBeNull();
    expect($address->_id)->toBeString();

    $raw = $address->getAttributes();
    expect($raw['_id'])->toBeInstanceOf(ObjectId::class);

    $address = $user->addresses()->save(new Address(['city' => 'Paris']));

    $user = User::find($user->_id);
    expect($user->addresses->pluck('city')->all())->toEqual(['London', 'Paris']);

    $address->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($address), Mockery::any());
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.saving: '.get_class($address), $address)
        ->andReturn(true);
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.updating: '.get_class($address), $address)
        ->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with('eloquent.updated: '.get_class($address), $address);
    $events->shouldReceive('dispatch')->once()->with('eloquent.saved: '.get_class($address), $address);

    $address->city = 'New York';
    $user->addresses()->save($address);
    $address->unsetEventDispatcher();

    expect($user->addresses)->toHaveCount(2);
    expect($user->addresses()->get())->toHaveCount(2);
    expect($user->addresses->count())->toEqual(2);
    expect($user->addresses()->count())->toEqual(2);
    expect($user->addresses->pluck('city')->all())->toEqual(['London', 'New York']);

    $freshUser = User::find($user->_id);
    expect($freshUser->addresses->pluck('city')->all())->toEqual(['London', 'New York']);

    $address = $user->addresses->first();
    expect($address->city)->toEqual('London');
    expect($address->created_at)->toBeInstanceOf(DateTime::class);
    expect($address->updated_at)->toBeInstanceOf(DateTime::class);
    expect($address->user)->toBeInstanceOf(User::class);
    expect($address->relationsToArray())->toBeEmpty(); // prevent infinite loop

    $user = User::find($user->_id);
    $user->addresses()->save(new Address(['city' => 'Bruxelles']));
    expect($user->addresses->pluck('city')->all())->toEqual(['London', 'New York', 'Bruxelles']);

    $address = $user->addresses[1];
    $address->city = 'Manhattan';
    $user->addresses()->save($address);
    expect($user->addresses->pluck('city')->all())->toEqual(['London', 'Manhattan', 'Bruxelles']);

    $freshUser = User::find($user->_id);
    expect($freshUser->addresses->pluck('city')->all())->toEqual(['London', 'Manhattan', 'Bruxelles']);
});

test('embeds to array', function () {
    $user = User::create(['name' => 'John Doe']);
    $user->addresses()->saveMany([new Address(['city' => 'London']), new Address(['city' => 'Bristol'])]);

    $array = $user->toArray();
    expect($array)->not->toHaveKey('_addresses');
    expect($array)->toHaveKey('addresses');
});

test('embeds many associate', function () {
    $user = User::create(['name' => 'John Doe']);
    $address = new Address(['city' => 'London']);

    $user->addresses()->associate($address);
    expect($user->addresses->pluck('city')->all())->toEqual(['London']);
    expect($address->_id)->not->toBeNull();

    $freshUser = User::find($user->_id);
    expect($freshUser->addresses->pluck('city')->all())->toEqual([]);

    $address->city = 'Londinium';
    $user->addresses()->associate($address);
    expect($user->addresses->pluck('city')->all())->toEqual(['Londinium']);

    $freshUser = User::find($user->_id);
    expect($freshUser->addresses->pluck('city')->all())->toEqual([]);
});

test('embeds many save many', function () {
    $user = User::create(['name' => 'John Doe']);
    $user->addresses()->saveMany([new Address(['city' => 'London']), new Address(['city' => 'Bristol'])]);
    expect($user->addresses->pluck('city')->all())->toEqual(['London', 'Bristol']);

    $freshUser = User::find($user->id);
    expect($freshUser->addresses->pluck('city')->all())->toEqual(['London', 'Bristol']);
});

test('embeds many duplicate', function () {
    $user = User::create(['name' => 'John Doe']);
    $address = new Address(['city' => 'London']);
    $user->addresses()->save($address);
    $user->addresses()->save($address);
    expect($user->addresses->count())->toEqual(1);
    expect($user->addresses->pluck('city')->all())->toEqual(['London']);

    $user = User::find($user->id);
    expect($user->addresses->count())->toEqual(1);

    $address->city = 'Paris';
    $user->addresses()->save($address);
    expect($user->addresses->count())->toEqual(1);
    expect($user->addresses->pluck('city')->all())->toEqual(['Paris']);

    $user->addresses()->create(['_id' => $address->_id, 'city' => 'Bruxelles']);
    expect($user->addresses->count())->toEqual(1);
    expect($user->addresses->pluck('city')->all())->toEqual(['Bruxelles']);
});

test('embeds many create', function () {
    $user = User::create([]);
    $address = $user->addresses()->create(['city' => 'Bruxelles']);
    expect($address)->toBeInstanceOf(Address::class);
    expect($address->_id)->toBeString();
    expect($user->addresses->pluck('city')->all())->toEqual(['Bruxelles']);

    $raw = $address->getAttributes();
    expect($raw['_id'])->toBeInstanceOf(ObjectId::class);

    $freshUser = User::find($user->id);
    expect($freshUser->addresses->pluck('city')->all())->toEqual(['Bruxelles']);

    $user = User::create([]);
    $address = $user->addresses()->create(['_id' => '', 'city' => 'Bruxelles']);
    expect($address->_id)->toBeString();

    $raw = $address->getAttributes();
    expect($raw['_id'])->toBeInstanceOf(ObjectId::class);
});

test('embeds many create many', function () {
    $user = User::create([]);
    [$bruxelles, $paris] = $user->addresses()->createMany([['city' => 'Bruxelles'], ['city' => 'Paris']]);
    expect($bruxelles)->toBeInstanceOf(Address::class);
    expect($bruxelles->city)->toEqual('Bruxelles');
    expect($user->addresses->pluck('city')->all())->toEqual(['Bruxelles', 'Paris']);

    $freshUser = User::find($user->id);
    expect($freshUser->addresses->pluck('city')->all())->toEqual(['Bruxelles', 'Paris']);
});

test('embeds many destroy', function () {
    $user = User::create(['name' => 'John Doe']);
    $user->addresses()->saveMany([
        new Address(['city' => 'London']),
        new Address(['city' => 'Bristol']),
        new Address(['city' => 'Bruxelles']),
    ]);

    $address = $user->addresses->first();

    $address->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($address), Mockery::any());
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.deleting: '.get_class($address), Mockery::type(Address::class))
        ->andReturn(true);
    $events->shouldReceive('dispatch')
        ->once()
        ->with('eloquent.deleted: '.get_class($address), Mockery::type(Address::class));

    $user->addresses()->destroy($address->_id);
    expect($user->addresses->pluck('city')->all())->toEqual(['Bristol', 'Bruxelles']);

    $address->unsetEventDispatcher();

    $address = $user->addresses->first();
    $user->addresses()->destroy($address);
    expect($user->addresses->pluck('city')->all())->toEqual(['Bruxelles']);

    $user->addresses()->create(['city' => 'Paris']);
    $user->addresses()->create(['city' => 'San Francisco']);

    $freshUser = User::find($user->id);
    expect($freshUser->addresses->pluck('city')->all())->toEqual(['Bruxelles', 'Paris', 'San Francisco']);

    $ids = $user->addresses->pluck('_id');
    $user->addresses()->destroy($ids);
    expect($user->addresses->pluck('city')->all())->toEqual([]);

    $freshUser = User::find($user->id);
    expect($freshUser->addresses->pluck('city')->all())->toEqual([]);

    [$london, $bristol, $bruxelles] = $user->addresses()->saveMany([
        new Address(['city' => 'London']),
        new Address(['city' => 'Bristol']),
        new Address(['city' => 'Bruxelles']),
    ]);
    $user->addresses()->destroy([$london, $bruxelles]);
    expect($user->addresses->pluck('city')->all())->toEqual(['Bristol']);
});

test('embeds many delete', function () {
    $user = User::create(['name' => 'John Doe']);
    $user->addresses()->saveMany([
        new Address(['city' => 'London']),
        new Address(['city' => 'Bristol']),
        new Address(['city' => 'Bruxelles']),
    ]);

    $address = $user->addresses->first();

    $address->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($address), Mockery::any());
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.deleting: '.get_class($address), Mockery::type(Address::class))
        ->andReturn(true);
    $events->shouldReceive('dispatch')
        ->once()
        ->with('eloquent.deleted: '.get_class($address), Mockery::type(Address::class));

    $address->delete();

    expect($user->addresses()->count())->toEqual(2);
    expect($user->addresses->count())->toEqual(2);

    $address->unsetEventDispatcher();

    $address = $user->addresses->first();
    $address->delete();

    $user = User::where('name', 'John Doe')->first();
    expect($user->addresses()->count())->toEqual(1);
    expect($user->addresses->count())->toEqual(1);
});

test('embeds many dissociate', function () {
    $user = User::create([]);
    $cordoba = $user->addresses()->create(['city' => 'Cordoba']);

    $user->addresses()->dissociate($cordoba->id);

    $freshUser = User::find($user->id);
    expect($user->addresses->count())->toEqual(0);
    expect($freshUser->addresses->count())->toEqual(1);
});

test('embeds many aliases', function () {
    $user = User::create(['name' => 'John Doe']);
    $address = new Address(['city' => 'London']);

    $address = $user->addresses()->attach($address);
    expect($user->addresses->pluck('city')->all())->toEqual(['London']);

    $user->addresses()->detach($address);
    expect($user->addresses->pluck('city')->all())->toEqual([]);
});

test('embeds many creating event returns false', function () {
    $user = User::create(['name' => 'John Doe']);
    $address = new Address(['city' => 'London']);

    $address->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($address), Mockery::any());
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.saving: '.get_class($address), $address)
        ->andReturn(true);
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.creating: '.get_class($address), $address)
        ->andReturn(false);

    expect($user->addresses()->save($address))->toBeFalse();
    $address->unsetEventDispatcher();
});

test('embeds many saving event returns false', function () {
    $user = User::create(['name' => 'John Doe']);
    $address = new Address(['city' => 'Paris']);
    $address->exists = true;

    $address->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($address), Mockery::any());
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.saving: '.get_class($address), $address)
        ->andReturn(false);

    expect($user->addresses()->save($address))->toBeFalse();
    $address->unsetEventDispatcher();
});

test('embeds many updating event returns false', function () {
    $user = User::create(['name' => 'John Doe']);
    $address = new Address(['city' => 'New York']);
    $user->addresses()->save($address);

    $address->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($address), Mockery::any());
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.saving: '.get_class($address), $address)
        ->andReturn(true);
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.updating: '.get_class($address), $address)
        ->andReturn(false);

    $address->city = 'Warsaw';

    expect($user->addresses()->save($address))->toBeFalse();
    $address->unsetEventDispatcher();
});

test('embeds many deleting event returns false', function () {
    $user = User::create(['name' => 'John Doe']);
    $user->addresses()->save(new Address(['city' => 'New York']));

    $address = $user->addresses->first();

    $address->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($address), Mockery::any());
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.deleting: '.get_class($address), Mockery::mustBe($address))
        ->andReturn(false);

    expect($user->addresses()->destroy($address))->toEqual(0);
    expect($user->addresses->pluck('city')->all())->toEqual(['New York']);

    $address->unsetEventDispatcher();
});

test('embeds many find or contains', function () {
    $user = User::create(['name' => 'John Doe']);
    $address1 = $user->addresses()->save(new Address(['city' => 'New York']));
    $address2 = $user->addresses()->save(new Address(['city' => 'Paris']));

    $address = $user->addresses()->find($address1->_id);
    expect($address->city)->toEqual($address1->city);

    $address = $user->addresses()->find($address2->_id);
    expect($address->city)->toEqual($address2->city);

    expect($user->addresses()->contains($address2->_id))->toBeTrue();
    expect($user->addresses()->contains('123'))->toBeFalse();
});

test('embeds many eager loading', function () {
    $user1 = User::create(['name' => 'John Doe']);
    $user1->addresses()->save(new Address(['city' => 'New York']));
    $user1->addresses()->save(new Address(['city' => 'Paris']));

    $user2 = User::create(['name' => 'Jane Doe']);
    $user2->addresses()->save(new Address(['city' => 'Berlin']));
    $user2->addresses()->save(new Address(['city' => 'Paris']));

    $user = User::find($user1->id);
    $relations = $user->getRelations();
    expect($relations)->not->toHaveKey('addresses');
    expect($user->toArray())->toHaveKey('addresses');
    expect($user->toArray()['addresses'])->toBeArray();

    $user = User::with('addresses')->get()->first();
    $relations = $user->getRelations();
    expect($relations)->toHaveKey('addresses');
    expect($relations['addresses']->count())->toEqual(2);
    expect($user->toArray())->toHaveKey('addresses');
    expect($user->toArray()['addresses'])->toBeArray();
});

test('embeds many delete all', function () {
    $user1 = User::create(['name' => 'John Doe']);
    $user1->addresses()->save(new Address(['city' => 'New York']));
    $user1->addresses()->save(new Address(['city' => 'Paris']));

    $user2 = User::create(['name' => 'Jane Doe']);
    $user2->addresses()->save(new Address(['city' => 'Berlin']));
    $user2->addresses()->save(new Address(['city' => 'Paris']));

    $user1->addresses()->delete();
    expect($user1->addresses()->count())->toEqual(0);
    expect($user1->addresses->count())->toEqual(0);
    expect($user2->addresses()->count())->toEqual(2);
    expect($user2->addresses->count())->toEqual(2);

    $user1 = User::find($user1->id);
    $user2 = User::find($user2->id);
    expect($user1->addresses()->count())->toEqual(0);
    expect($user1->addresses->count())->toEqual(0);
    expect($user2->addresses()->count())->toEqual(2);
    expect($user2->addresses->count())->toEqual(2);
});

test('embeds many collection methods', function () {
    $user = User::create(['name' => 'John Doe']);
    $user->addresses()->save(new Address([
        'city' => 'Paris',
        'country' => 'France',
        'visited' => 4,
        'created_at' => new DateTime('3 days ago'),
    ]));
    $user->addresses()->save(new Address([
        'city' => 'Bruges',
        'country' => 'Belgium',
        'visited' => 7,
        'created_at' => new DateTime('5 days ago'),
    ]));
    $user->addresses()->save(new Address([
        'city' => 'Brussels',
        'country' => 'Belgium',
        'visited' => 2,
        'created_at' => new DateTime('4 days ago'),
    ]));
    $user->addresses()->save(new Address([
        'city' => 'Ghent',
        'country' => 'Belgium',
        'visited' => 13,
        'created_at' => new DateTime('2 days ago'),
    ]));

    expect($user->addresses()->pluck('city')->all())->toEqual(['Paris', 'Bruges', 'Brussels', 'Ghent']);
    expect($user->addresses()
        ->sortBy('city')
        ->pluck('city')
        ->all())->toEqual(['Bruges', 'Brussels', 'Ghent', 'Paris']);
    expect($user->addresses()->where('city', 'New York')->pluck('city')->all())->toEqual([]);
    expect($user->addresses()
        ->where('country', 'Belgium')
        ->pluck('city')
        ->all())->toEqual(['Bruges', 'Brussels', 'Ghent']);
    expect($user->addresses()
        ->where('country', 'Belgium')
        ->sortBy('city')
        ->pluck('city')
        ->all())->toEqual(['Bruges', 'Brussels', 'Ghent']);

    $results = $user->addresses->first();
    expect($results)->toBeInstanceOf(Address::class);

    $results = $user->addresses()->where('country', 'Belgium');
    expect($results)->toBeInstanceOf(Collection::class);
    expect($results->count())->toEqual(3);

    $results = $user->addresses()->whereIn('visited', [7, 13]);
    expect($results->count())->toEqual(2);
});

test('embeds one', function () {
    $user = User::create(['name' => 'John Doe']);
    $father = new User(['name' => 'Mark Doe']);

    $father->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($father), Mockery::any());
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.saving: '.get_class($father), $father)
        ->andReturn(true);
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.creating: '.get_class($father), $father)
        ->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with('eloquent.created: '.get_class($father), $father);
    $events->shouldReceive('dispatch')->once()->with('eloquent.saved: '.get_class($father), $father);

    $father = $user->father()->save($father);
    $father->unsetEventDispatcher();

    expect($user->father)->not->toBeNull();
    expect($user->father->name)->toEqual('Mark Doe');
    expect($father->created_at)->toBeInstanceOf(DateTime::class);
    expect($father->updated_at)->toBeInstanceOf(DateTime::class);
    expect($father->_id)->not->toBeNull();
    expect($father->_id)->toBeString();

    $raw = $father->getAttributes();
    expect($raw['_id'])->toBeInstanceOf(ObjectId::class);

    $father->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($father), Mockery::any());
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.saving: '.get_class($father), $father)
        ->andReturn(true);
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.updating: '.get_class($father), $father)
        ->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with('eloquent.updated: '.get_class($father), $father);
    $events->shouldReceive('dispatch')->once()->with('eloquent.saved: '.get_class($father), $father);

    $father->name = 'Tom Doe';
    $user->father()->save($father);
    $father->unsetEventDispatcher();

    expect($user->father)->not->toBeNull();
    expect($user->father->name)->toEqual('Tom Doe');

    $father = new User(['name' => 'Jim Doe']);

    $father->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($father), Mockery::any());
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.saving: '.get_class($father), $father)
        ->andReturn(true);
    $events->shouldReceive('until')
        ->once()
        ->with('eloquent.creating: '.get_class($father), $father)
        ->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with('eloquent.created: '.get_class($father), $father);
    $events->shouldReceive('dispatch')->once()->with('eloquent.saved: '.get_class($father), $father);

    $father = $user->father()->save($father);
    $father->unsetEventDispatcher();

    expect($user->father)->not->toBeNull();
    expect($user->father->name)->toEqual('Jim Doe');
});

test('embeds one associate', function () {
    $user = User::create(['name' => 'John Doe']);
    $father = new User(['name' => 'Mark Doe']);

    $father->setEventDispatcher($events = Mockery::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->with('eloquent.retrieved: '.get_class($father), Mockery::any());
    $events->shouldReceive('until')->times(0)->with('eloquent.saving: '.get_class($father), $father);

    $father = $user->father()->associate($father);
    $father->unsetEventDispatcher();

    expect($user->father)->not->toBeNull();
    expect($user->father->name)->toEqual('Mark Doe');
});

test('embeds one null association', function () {
    $user = User::create();
    expect($user->father)->toBeNull();
});

test('embeds one delete', function () {
    $user = User::create(['name' => 'John Doe']);
    $father = $user->father()->save(new User(['name' => 'Mark Doe']));

    $user->father()->delete();
    expect($user->father)->toBeNull();
});

test('embeds many to array', function () {
    /** @var User $user */
    $user = User::create(['name' => 'John Doe']);
    $user->addresses()->save(new Address(['city' => 'New York']));
    $user->addresses()->save(new Address(['city' => 'Paris']));
    $user->addresses()->save(new Address(['city' => 'Brussels']));

    $array = $user->toArray();
    expect($array)->toHaveKey('addresses');
    expect($array['addresses'])->toBeArray();
});

test('embedded save', function () {
    /** @var User $user */
    $user = User::create(['name' => 'John Doe']);
    /** @var \Address $address */
    $address = $user->addresses()->create(['city' => 'New York']);
    $father = $user->father()->create(['name' => 'Mark Doe']);

    $address->city = 'Paris';
    $address->save();

    $father->name = 'Steve Doe';
    $father->save();

    expect($user->addresses->first()->city)->toEqual('Paris');
    expect($user->father->name)->toEqual('Steve Doe');

    $user = User::where('name', 'John Doe')->first();
    expect($user->addresses->first()->city)->toEqual('Paris');
    expect($user->father->name)->toEqual('Steve Doe');

    $address = $user->addresses()->first();
    $father = $user->father;

    $address->city = 'Ghent';
    $address->save();

    $father->name = 'Mark Doe';
    $father->save();

    expect($user->addresses->first()->city)->toEqual('Ghent');
    expect($user->father->name)->toEqual('Mark Doe');

    $user = User::where('name', 'John Doe')->first();
    expect($user->addresses->first()->city)->toEqual('Ghent');
    expect($user->father->name)->toEqual('Mark Doe');
});

test('nested embeds one', function () {
    $user = User::create(['name' => 'John Doe']);
    $father = $user->father()->create(['name' => 'Mark Doe']);
    $grandfather = $father->father()->create(['name' => 'Steve Doe']);
    $greatgrandfather = $grandfather->father()->create(['name' => 'Tom Doe']);

    $user->name = 'Tim Doe';
    $user->save();

    $father->name = 'Sven Doe';
    $father->save();

    $greatgrandfather->name = 'Ron Doe';
    $greatgrandfather->save();

    expect($user->name)->toEqual('Tim Doe');
    expect($user->father->name)->toEqual('Sven Doe');
    expect($user->father->father->name)->toEqual('Steve Doe');
    expect($user->father->father->father->name)->toEqual('Ron Doe');

    $user = User::where('name', 'Tim Doe')->first();
    expect($user->name)->toEqual('Tim Doe');
    expect($user->father->name)->toEqual('Sven Doe');
    expect($user->father->father->name)->toEqual('Steve Doe');
    expect($user->father->father->father->name)->toEqual('Ron Doe');
});

test('nested embeds many', function () {
    $user = User::create(['name' => 'John Doe']);
    $country1 = $user->addresses()->create(['country' => 'France']);
    $country2 = $user->addresses()->create(['country' => 'Belgium']);
    $city1 = $country1->addresses()->create(['city' => 'Paris']);
    $city2 = $country2->addresses()->create(['city' => 'Ghent']);
    $city3 = $country2->addresses()->create(['city' => 'Brussels']);

    $city3->city = 'Bruges';
    $city3->save();

    expect($user->addresses()->count())->toEqual(2);
    expect($user->addresses()->first()->addresses()->count())->toEqual(1);
    expect($user->addresses()->last()->addresses()->count())->toEqual(2);

    $user = User::where('name', 'John Doe')->first();
    expect($user->addresses()->count())->toEqual(2);
    expect($user->addresses()->first()->addresses()->count())->toEqual(1);
    expect($user->addresses()->last()->addresses()->count())->toEqual(2);
});

test('nested mixed embeds', function () {
    $user = User::create(['name' => 'John Doe']);
    $father = $user->father()->create(['name' => 'Mark Doe']);
    $country1 = $father->addresses()->create(['country' => 'France']);
    $country2 = $father->addresses()->create(['country' => 'Belgium']);

    $country2->country = 'England';
    $country2->save();

    $father->name = 'Steve Doe';
    $father->save();

    expect($user->father->addresses()->first()->country)->toEqual('France');
    expect($user->father->addresses()->last()->country)->toEqual('England');
    expect($user->father->name)->toEqual('Steve Doe');

    $user = User::where('name', 'John Doe')->first();
    expect($user->father->addresses()->first()->country)->toEqual('France');
    expect($user->father->addresses()->last()->country)->toEqual('England');
    expect($user->father->name)->toEqual('Steve Doe');
});

test('nested embeds one delete', function () {
    $user = User::create(['name' => 'John Doe']);
    $father = $user->father()->create(['name' => 'Mark Doe']);
    $grandfather = $father->father()->create(['name' => 'Steve Doe']);
    $greatgrandfather = $grandfather->father()->create(['name' => 'Tom Doe']);

    $grandfather->delete();

    expect($user->father->father)->toBeNull();

    $user = User::where(['name' => 'John Doe'])->first();
    expect($user->father->father)->toBeNull();
});

test('nested embeds many delete', function () {
    $user = User::create(['name' => 'John Doe']);
    $country = $user->addresses()->create(['country' => 'France']);
    $city1 = $country->addresses()->create(['city' => 'Paris']);
    $city2 = $country->addresses()->create(['city' => 'Nice']);
    $city3 = $country->addresses()->create(['city' => 'Lyon']);

    $city2->delete();

    expect($user->addresses()->first()->addresses()->count())->toEqual(2);
    expect($country->addresses()->last()->city)->toEqual('Lyon');

    $user = User::where('name', 'John Doe')->first();
    expect($user->addresses()->first()->addresses()->count())->toEqual(2);
    expect($country->addresses()->last()->city)->toEqual('Lyon');
});

test('nested mixed embeds delete', function () {
    $user = User::create(['name' => 'John Doe']);
    $father = $user->father()->create(['name' => 'Mark Doe']);
    $country1 = $father->addresses()->create(['country' => 'France']);
    $country2 = $father->addresses()->create(['country' => 'Belgium']);

    $country1->delete();

    expect($user->father->addresses()->count())->toEqual(1);
    expect($user->father->addresses()->last()->country)->toEqual('Belgium');

    $user = User::where('name', 'John Doe')->first();
    expect($user->father->addresses()->count())->toEqual(1);
    expect($user->father->addresses()->last()->country)->toEqual('Belgium');
});

test('double associate', function () {
    $user = User::create(['name' => 'John Doe']);
    $address = new Address(['city' => 'Paris']);

    $user->addresses()->associate($address);
    $user->addresses()->associate($address);
    $address = $user->addresses()->first();
    $user->addresses()->associate($address);
    expect($user->addresses()->count())->toEqual(1);

    $user = User::where('name', 'John Doe')->first();
    $user->addresses()->associate($address);
    expect($user->addresses()->count())->toEqual(1);

    $user->save();
    $user->addresses()->associate($address);
    expect($user->addresses()->count())->toEqual(1);
});

test('save empty model', function () {
    $user = User::create(['name' => 'John Doe']);
    $user->addresses()->save(new Address);
    expect($user->addresses)->not->toBeNull();
    expect($user->addresses()->count())->toEqual(1);
});

test('increment embedded', function () {
    $user = User::create(['name' => 'John Doe']);
    $address = $user->addresses()->create(['city' => 'New York', 'visited' => 5]);

    $address->increment('visited');
    expect($address->visited)->toEqual(6);
    expect($user->addresses()->first()->visited)->toEqual(6);

    $user = User::where('name', 'John Doe')->first();
    expect($user->addresses()->first()->visited)->toEqual(6);

    $user = User::where('name', 'John Doe')->first();
    $address = $user->addresses()->first();

    $address->decrement('visited');
    expect($address->visited)->toEqual(5);
    expect($user->addresses()->first()->visited)->toEqual(5);

    $user = User::where('name', 'John Doe')->first();
    expect($user->addresses()->first()->visited)->toEqual(5);
});

test('paginate embeds many', function () {
    $user = User::create(['name' => 'John Doe']);
    $user->addresses()->save(new Address(['city' => 'New York']));
    $user->addresses()->save(new Address(['city' => 'Paris']));
    $user->addresses()->save(new Address(['city' => 'Brussels']));

    $results = $user->addresses()->paginate(2);
    expect($results->count())->toEqual(2);
    expect($results->total())->toEqual(3);
});

test('get queueable relations embeds many', function () {
    $user = User::create(['name' => 'John Doe']);
    $user->addresses()->save(new Address(['city' => 'New York']));
    $user->addresses()->save(new Address(['city' => 'Paris']));

    expect($user->getQueueableRelations())->toEqual(['addresses']);
    expect($user->addresses->getQueueableRelations())->toEqual([]);
});

test('get queueable relations embeds one', function () {
    $user = User::create(['name' => 'John Doe']);
    $user->father()->save(new User(['name' => 'Mark Doe']));

    expect($user->getQueueableRelations())->toEqual(['father']);
    expect($user->father->getQueueableRelations())->toEqual([]);
});
