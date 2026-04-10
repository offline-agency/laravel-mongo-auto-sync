<?php

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Cursor;
use MongoDB\Laravel\Collection;
use MongoDB\Laravel\Eloquent\Builder;
use Tests\Models\Item;
use Tests\Models\User;

afterEach(function () {
    DB::collection('users')->truncate();
    DB::collection('items')->truncate();
});

test('delete with id', function () {
    DB::collection('users')->truncate();
    DB::collection('items')->truncate();

    $user = DB::collection('users')->insertGetId([
        ['name' => 'Jane Doe', 'age' => 20],
    ]);

    $user_id = (string) $user;

    DB::collection('items')->insert([
        ['name' => 'one thing', 'user_id' => $user_id],
        ['name' => 'last thing', 'user_id' => $user_id],
        ['name' => 'another thing', 'user_id' => $user_id],
        ['name' => 'one more thing', 'user_id' => $user_id],
    ]);

    $product = DB::collection('items')->first();

    $pid = (string) ($product['_id']);

    $result = DB::collection('items')->where('user_id', $user_id)->delete($pid);

    expect(DB::collection('items')->count())->toEqual(3);

    $product = DB::collection('items')->first();

    $pid = $product['_id'];

    DB::collection('items')->where('user_id', $user_id)->delete($pid);

    DB::collection('items')->where('user_id', $user_id)->delete(md5('random-id'));

    expect(DB::collection('items')->count())->toEqual(2);
});

test('collection', function () {
    expect(DB::collection('users'))->toBeInstanceOf(Builder::class);
});

test('get', function () {
    $users = DB::collection('users')->get();
    expect($users)->toHaveCount(0);

    DB::collection('users')->insert(['name' => 'John Doe']);

    $users = DB::collection('users')->get();
    expect($users)->toHaveCount(1);
});

test('no document', function () {
    $items = DB::collection('items')->where('name', 'nothing')->get()->toArray();
    expect($items)->toEqual([]);

    $item = DB::collection('items')->where('name', 'nothing')->first();
    expect($item)->toBeNull();

    $item = DB::collection('items')->where('_id', '51c33d8981fec6813e00000a')->first();
    expect($item)->toBeNull();
});

test('insert', function () {
    DB::collection('users')->insert([
        'tags' => ['tag1', 'tag2'],
        'name' => 'John Doe',
    ]);

    $users = DB::collection('users')->get();
    expect($users)->toHaveCount(1);

    $user = $users[0];
    expect($user['name'])->toEqual('John Doe');
    expect($user['tags'])->toBeArray();
});

test('insert get id', function () {
    $id = DB::collection('users')->insertGetId(['name' => 'John Doe']);
    expect($id)->toBeInstanceOf(ObjectId::class);
});

test('batch insert', function () {
    DB::collection('users')->insert([
        [
            'tags' => ['tag1', 'tag2'],
            'name' => 'Jane Doe',
        ],
        [
            'tags' => ['tag3'],
            'name' => 'John Doe',
        ],
    ]);

    $users = DB::collection('users')->get();
    expect($users)->toHaveCount(2);
    expect($users[0]['tags'])->toBeArray();
});

test('find', function () {
    $id = DB::collection('users')->insertGetId(['name' => 'John Doe']);

    $user = DB::collection('users')->find($id);
    expect($user['name'])->toEqual('John Doe');
});

test('find null', function () {
    $user = DB::collection('users')->find(null);
    expect($user)->toBeNull();
});

test('count', function () {
    DB::collection('users')->insert([
        ['name' => 'Jane Doe'],
        ['name' => 'John Doe'],
    ]);

    expect(DB::collection('users')->count())->toEqual(2);
});

test('update', function () {
    DB::collection('users')->insert([
        ['name' => 'Jane Doe', 'age' => 20],
        ['name' => 'John Doe', 'age' => 21],
    ]);

    DB::collection('users')->where('name', 'John Doe')->update(['age' => 100]);

    $john = DB::collection('users')->where('name', 'John Doe')->first();
    $jane = DB::collection('users')->where('name', 'Jane Doe')->first();
    expect($john['age'])->toEqual(100);
    expect($jane['age'])->toEqual(20);
});

test('delete', function () {
    DB::collection('users')->insert([
        ['name' => 'Jane Doe', 'age' => 20],
        ['name' => 'John Doe', 'age' => 25],
    ]);

    DB::collection('users')->where('age', '<', 10)->delete();
    expect(DB::collection('users')->count())->toEqual(2);

    DB::collection('users')->where('age', '<', 25)->delete();
    expect(DB::collection('users')->count())->toEqual(1);
});

test('truncate', function () {
    DB::collection('users')->insert(['name' => 'John Doe']);
    $result = DB::collection('users')->truncate();
    expect($result)->toEqual(1);
    expect(DB::collection('users')->count())->toEqual(0);
});

test('sub key', function () {
    DB::collection('users')->insert([
        [
            'name' => 'John Doe',
            'address' => ['country' => 'Belgium', 'city' => 'Ghent'],
        ],
        [
            'name' => 'Jane Doe',
            'address' => ['country' => 'France', 'city' => 'Paris'],
        ],
    ]);

    $users = DB::collection('users')->where('address.country', 'Belgium')->get();
    expect($users)->toHaveCount(1);
    expect($users[0]['name'])->toEqual('John Doe');
});

test('in array', function () {
    DB::collection('items')->insert([
        [
            'tags' => ['tag1', 'tag2', 'tag3', 'tag4'],
        ],
        [
            'tags' => ['tag2'],
        ],
    ]);

    $items = DB::collection('items')->where('tags', 'tag2')->get();
    expect($items)->toHaveCount(2);

    $items = DB::collection('items')->where('tags', 'tag1')->get();
    expect($items)->toHaveCount(1);
});

test('raw', function () {
    DB::collection('users')->insert([
        ['name' => 'Jane Doe', 'age' => 20],
        ['name' => 'John Doe', 'age' => 25],
    ]);

    $cursor = DB::collection('users')->raw(function ($collection) {
        return $collection->find(['age' => 20]);
    });

    expect($cursor)->toBeInstanceOf(Cursor::class);
    expect($cursor->toArray())->toHaveCount(1);

    $collection = DB::collection('users')->raw();
    expect($collection)->toBeInstanceOf(Collection::class);

    $collection = User::raw();
    expect($collection)->toBeInstanceOf(Collection::class);

    $results = DB::collection('users')->whereRaw(['age' => 20])->get();
    expect($results)->toHaveCount(1);
    expect($results[0]['name'])->toEqual('Jane Doe');
});

test('push', function () {
    $id = DB::collection('users')->insertGetId([
        'name' => 'John Doe',
        'tags' => [],
        'messages' => [],
    ]);

    DB::collection('users')->where('_id', $id)->push('tags', 'tag1');

    $user = DB::collection('users')->find($id);
    expect($user['tags'])->toBeArray();
    expect($user['tags'])->toHaveCount(1);
    expect($user['tags'][0])->toEqual('tag1');

    DB::collection('users')->where('_id', $id)->push('tags', 'tag2');
    $user = DB::collection('users')->find($id);
    expect($user['tags'])->toHaveCount(2);
    expect($user['tags'][1])->toEqual('tag2');

    // Add duplicate
    DB::collection('users')->where('_id', $id)->push('tags', 'tag2');
    $user = DB::collection('users')->find($id);
    expect($user['tags'])->toHaveCount(3);

    // Add unique
    DB::collection('users')->where('_id', $id)->push('tags', 'tag1', true);
    $user = DB::collection('users')->find($id);
    expect($user['tags'])->toHaveCount(3);

    $message = ['from' => 'Jane', 'body' => 'Hi John'];
    DB::collection('users')->where('_id', $id)->push('messages', $message);
    $user = DB::collection('users')->find($id);
    expect($user['messages'])->toBeArray();
    expect($user['messages'])->toHaveCount(1);
    expect($user['messages'][0])->toEqual($message);

    // Raw
    DB::collection('users')->where('_id', $id)->push([
        'tags' => 'tag3',
        'messages' => ['from' => 'Mark', 'body' => 'Hi John'],
    ]);
    $user = DB::collection('users')->find($id);
    expect($user['tags'])->toHaveCount(4);
    expect($user['messages'])->toHaveCount(2);

    DB::collection('users')->where('_id', $id)->push([
        'messages' => [
            'date' => new DateTime,
            'body' => 'Hi John',
        ],
    ]);
    $user = DB::collection('users')->find($id);
    expect($user['messages'])->toHaveCount(3);
});

test('pull', function () {
    $message1 = ['from' => 'Jane', 'body' => 'Hi John'];
    $message2 = ['from' => 'Mark', 'body' => 'Hi John'];

    $id = DB::collection('users')->insertGetId([
        'name' => 'John Doe',
        'tags' => ['tag1', 'tag2', 'tag3', 'tag4'],
        'messages' => [$message1, $message2],
    ]);

    DB::collection('users')->where('_id', $id)->pull('tags', 'tag3');

    $user = DB::collection('users')->find($id);
    expect($user['tags'])->toBeArray();
    expect($user['tags'])->toHaveCount(3);
    expect($user['tags'][2])->toEqual('tag4');

    DB::collection('users')->where('_id', $id)->pull('messages', $message1);

    $user = DB::collection('users')->find($id);
    expect($user['messages'])->toBeArray();
    expect($user['messages'])->toHaveCount(1);

    // Raw
    DB::collection('users')->where('_id', $id)->pull(['tags' => 'tag2', 'messages' => $message2]);
    $user = DB::collection('users')->find($id);
    expect($user['tags'])->toHaveCount(2);
    expect($user['messages'])->toHaveCount(0);
});

test('distinct', function () {
    DB::collection('items')->insert([
        ['name' => 'knife', 'type' => 'sharp'],
        ['name' => 'fork', 'type' => 'sharp'],
        ['name' => 'spoon', 'type' => 'round'],
        ['name' => 'spoon', 'type' => 'round'],
    ]);

    $items = DB::collection('items')->distinct('name')->get()->toArray();
    sort($items);
    expect($items)->toHaveCount(3);
    expect($items)->toEqual(['fork', 'knife', 'spoon']);

    $types = DB::collection('items')->distinct('type')->get()->toArray();
    sort($types);
    expect($types)->toHaveCount(2);
    expect($types)->toEqual(['round', 'sharp']);
});

test('custom id', function () {
    DB::collection('items')->insert([
        ['_id' => 'knife', 'type' => 'sharp', 'amount' => 34],
        ['_id' => 'fork', 'type' => 'sharp', 'amount' => 20],
        ['_id' => 'spoon', 'type' => 'round', 'amount' => 3],
    ]);

    $item = DB::collection('items')->find('knife');
    expect($item['_id'])->toEqual('knife');

    $item = DB::collection('items')->where('_id', 'fork')->first();
    expect($item['_id'])->toEqual('fork');

    DB::collection('users')->insert([
        ['_id' => 1, 'name' => 'Jane Doe'],
        ['_id' => 2, 'name' => 'John Doe'],
    ]);

    $item = DB::collection('users')->find(1);
    expect($item['_id'])->toEqual(1);
});

test('take', function () {
    DB::collection('items')->insert([
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
    ]);

    $items = DB::collection('items')->orderBy('name')->take(2)->get();
    expect($items)->toHaveCount(2);
    expect($items[0]['name'])->toEqual('fork');
});

test('skip', function () {
    DB::collection('items')->insert([
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
    ]);

    $items = DB::collection('items')->orderBy('name')->skip(2)->get();
    expect($items)->toHaveCount(2);
    expect($items[0]['name'])->toEqual('spoon');
});

test('pluck', function () {
    DB::collection('users')->insert([
        ['name' => 'Jane Doe', 'age' => 20],
        ['name' => 'John Doe', 'age' => 25],
    ]);

    $age = DB::collection('users')->where('name', 'John Doe')->pluck('age')->toArray();
    expect($age)->toEqual([25]);
});

test('list', function () {
    DB::collection('items')->insert([
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
    ]);

    $list = DB::collection('items')->pluck('name')->toArray();
    sort($list);
    expect($list)->toHaveCount(4);
    expect($list)->toEqual(['fork', 'knife', 'spoon', 'spoon']);

    $list = DB::collection('items')->pluck('type', 'name')->toArray();
    expect($list)->toHaveCount(3);
    expect($list)->toEqual(['knife' => 'sharp', 'fork' => 'sharp', 'spoon' => 'round']);

    $list = DB::collection('items')->pluck('name', '_id')->toArray();
    expect($list)->toHaveCount(4);
    expect(strlen(key($list)))->toEqual(24);
});

test('aggregate', function () {
    DB::collection('items')->insert([
        ['name' => 'knife', 'type' => 'sharp', 'amount' => 34],
        ['name' => 'fork', 'type' => 'sharp', 'amount' => 20],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 3],
        ['name' => 'spoon', 'type' => 'round', 'amount' => 14],
    ]);

    expect(DB::collection('items')->sum('amount'))->toEqual(71);
    expect(DB::collection('items')->count('amount'))->toEqual(4);
    expect(DB::collection('items')->min('amount'))->toEqual(3);
    expect(DB::collection('items')->max('amount'))->toEqual(34);
    expect(DB::collection('items')->avg('amount'))->toEqual(17.75);

    expect(DB::collection('items')->where('name', 'spoon')->count('amount'))->toEqual(2);
    expect(DB::collection('items')->where('name', 'spoon')->max('amount'))->toEqual(14);
});

test('subdocument aggregate', function () {
    DB::collection('items')->insert([
        ['name' => 'knife', 'amount' => ['hidden' => 10, 'found' => 3]],
        ['name' => 'fork', 'amount' => ['hidden' => 35, 'found' => 12]],
        ['name' => 'spoon', 'amount' => ['hidden' => 14, 'found' => 21]],
        ['name' => 'spoon', 'amount' => ['hidden' => 6, 'found' => 4]],
    ]);

    expect(DB::collection('items')->sum('amount.hidden'))->toEqual(65);
    expect(DB::collection('items')->count('amount.hidden'))->toEqual(4);
    expect(DB::collection('items')->min('amount.hidden'))->toEqual(6);
    expect(DB::collection('items')->max('amount.hidden'))->toEqual(35);
    expect(DB::collection('items')->avg('amount.hidden'))->toEqual(16.25);
});

test('subdocument array aggregate', function () {
    DB::collection('items')->insert([
        ['name' => 'knife', 'amount' => [['hidden' => 10, 'found' => 3], ['hidden' => 5, 'found' => 2]]],
        [
            'name' => 'fork',
            'amount' => [
                ['hidden' => 35, 'found' => 12],
                ['hidden' => 7, 'found' => 17],
                ['hidden' => 1, 'found' => 19],
            ],
        ],
        ['name' => 'spoon', 'amount' => [['hidden' => 14, 'found' => 21]]],
        ['name' => 'teaspoon', 'amount' => []],
    ]);

    expect(DB::collection('items')->sum('amount.*.hidden'))->toEqual(72);
    expect(DB::collection('items')->count('amount.*.hidden'))->toEqual(6);
    expect(DB::collection('items')->min('amount.*.hidden'))->toEqual(1);
    expect(DB::collection('items')->max('amount.*.hidden'))->toEqual(35);
    expect(DB::collection('items')->avg('amount.*.hidden'))->toEqual(12);
});

test('upsert', function () {
    DB::collection('items')->where('name', 'knife')
        ->update(
            ['amount' => 1],
            ['upsert' => true]
        );

    expect(DB::collection('items')->count())->toEqual(1);

    Item::where('name', 'spoon')
        ->update(
            ['amount' => 1],
            ['upsert' => true]
        );

    expect(DB::collection('items')->count())->toEqual(2);
});

test('unset', function () {
    $id1 = DB::collection('users')->insertGetId(['name' => 'John Doe', 'note1' => 'ABC', 'note2' => 'DEF']);
    $id2 = DB::collection('users')->insertGetId(['name' => 'Jane Doe', 'note1' => 'ABC', 'note2' => 'DEF']);

    DB::collection('users')->where('name', 'John Doe')->unset('note1');

    $user1 = DB::collection('users')->find($id1);
    $user2 = DB::collection('users')->find($id2);

    expect($user1)->not->toHaveKey('note1');
    expect($user1)->toHaveKey('note2');
    expect($user2)->toHaveKey('note1');
    expect($user2)->toHaveKey('note2');

    DB::collection('users')->where('name', 'Jane Doe')->unset(['note1', 'note2']);

    $user2 = DB::collection('users')->find($id2);
    expect($user2)->not->toHaveKey('note1');
    expect($user2)->not->toHaveKey('note2');
});

test('update subdocument', function () {
    $id = DB::collection('users')->insertGetId(['name' => 'John Doe', 'address' => ['country' => 'Belgium']]);

    DB::collection('users')->where('_id', $id)->update(['address.country' => 'England']);

    $check = DB::collection('users')->find($id);
    expect($check['address']['country'])->toEqual('England');
});

test('dates', function () {
    DB::collection('users')->insert([
        ['name' => 'John Doe', 'birthday' => new UTCDateTime(Date::parse('1980-01-01 00:00:00')->format('Uv'))],
        ['name' => 'Jane Doe', 'birthday' => new UTCDateTime(Date::parse('1981-01-01 00:00:00')->format('Uv'))],
        ['name' => 'Robert Roe', 'birthday' => new UTCDateTime(Date::parse('1982-01-01 00:00:00')->format('Uv'))],
        ['name' => 'Mark Moe', 'birthday' => new UTCDateTime(Date::parse('1983-01-01 00:00:00')->format('Uv'))],
    ]);

    $user = DB::collection('users')
        ->where('birthday', new UTCDateTime(Date::parse('1980-01-01 00:00:00')->format('Uv')))
        ->first();
    expect($user['name'])->toEqual('John Doe');

    $user = DB::collection('users')->where('birthday', '=', new DateTime('1980-01-01 00:00:00'))->first();
    expect($user['name'])->toEqual('John Doe');

    $start = new UTCDateTime(1000 * strtotime('1981-01-01 00:00:00'));
    $stop = new UTCDateTime(1000 * strtotime('1982-01-01 00:00:00'));

    $users = DB::collection('users')->whereBetween('birthday', [$start, $stop])->get();
    expect($users)->toHaveCount(2);
});

test('operators', function () {
    DB::collection('users')->insert([
        ['name' => 'John Doe', 'age' => 30],
        ['name' => 'Jane Doe'],
        ['name' => 'Robert Roe', 'age' => 'thirty-one'],
    ]);

    $results = DB::collection('users')->where('age', 'exists', true)->get();
    expect($results)->toHaveCount(2);
    $resultsNames = [$results[0]['name'], $results[1]['name']];
    expect($resultsNames)->toContain('John Doe');
    expect($resultsNames)->toContain('Robert Roe');

    $results = DB::collection('users')->where('age', 'exists', false)->get();
    expect($results)->toHaveCount(1);
    expect($results[0]['name'])->toEqual('Jane Doe');

    $results = DB::collection('users')->where('age', 'type', 2)->get();
    expect($results)->toHaveCount(1);
    expect($results[0]['name'])->toEqual('Robert Roe');

    $results = DB::collection('users')->where('age', 'mod', [15, 0])->get();
    expect($results)->toHaveCount(1);
    expect($results[0]['name'])->toEqual('John Doe');

    $results = DB::collection('users')->where('age', 'mod', [29, 1])->get();
    expect($results)->toHaveCount(1);
    expect($results[0]['name'])->toEqual('John Doe');

    $results = DB::collection('users')->where('age', 'mod', [14, 0])->get();
    expect($results)->toHaveCount(0);

    DB::collection('items')->insert([
        ['name' => 'fork', 'tags' => ['sharp', 'pointy']],
        ['name' => 'spork', 'tags' => ['sharp', 'pointy', 'round', 'bowl']],
        ['name' => 'spoon', 'tags' => ['round', 'bowl']],
    ]);

    $results = DB::collection('items')->where('tags', 'all', ['sharp', 'pointy'])->get();
    expect($results)->toHaveCount(2);

    $results = DB::collection('items')->where('tags', 'all', ['sharp', 'round'])->get();
    expect($results)->toHaveCount(1);

    $results = DB::collection('items')->where('tags', 'size', 2)->get();
    expect($results)->toHaveCount(2);

    $results = DB::collection('items')->where('tags', '$size', 2)->get();
    expect($results)->toHaveCount(2);

    $results = DB::collection('items')->where('tags', 'size', 3)->get();
    expect($results)->toHaveCount(0);

    $results = DB::collection('items')->where('tags', 'size', 4)->get();
    expect($results)->toHaveCount(1);

    $regex = new Regex('.*doe', 'i');
    $results = DB::collection('users')->where('name', 'regex', $regex)->get();
    expect($results)->toHaveCount(2);

    $regex = new Regex('.*doe', 'i');
    $results = DB::collection('users')->where('name', 'regexp', $regex)->get();
    expect($results)->toHaveCount(2);

    $results = DB::collection('users')->where('name', 'REGEX', $regex)->get();
    expect($results)->toHaveCount(2);

    $results = DB::collection('users')->where('name', 'regexp', '/.*doe/i')->get();
    expect($results)->toHaveCount(2);

    $results = DB::collection('users')->where('name', 'not regexp', '/.*doe/i')->get();
    expect($results)->toHaveCount(1);
});

test('increment', function () {
    DB::collection('users')->insert([
        ['name' => 'John Doe', 'age' => 30, 'note' => 'adult'],
        ['name' => 'Jane Doe', 'age' => 10, 'note' => 'minor'],
        ['name' => 'Robert Roe', 'age' => null],
        ['name' => 'Mark Moe'],
    ]);

    $user = DB::collection('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toEqual(30);

    DB::collection('users')->where('name', 'John Doe')->increment('age');
    $user = DB::collection('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toEqual(31);

    DB::collection('users')->where('name', 'John Doe')->decrement('age');
    $user = DB::collection('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toEqual(30);

    DB::collection('users')->where('name', 'John Doe')->increment('age', 5);
    $user = DB::collection('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toEqual(35);

    DB::collection('users')->where('name', 'John Doe')->decrement('age', 5);
    $user = DB::collection('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toEqual(30);

    DB::collection('users')->where('name', 'Jane Doe')->increment('age', 10, ['note' => 'adult']);
    $user = DB::collection('users')->where('name', 'Jane Doe')->first();
    expect($user['age'])->toEqual(20);
    expect($user['note'])->toEqual('adult');

    DB::collection('users')->where('name', 'John Doe')->decrement('age', 20, ['note' => 'minor']);
    $user = DB::collection('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toEqual(10);
    expect($user['note'])->toEqual('minor');

    DB::collection('users')->increment('age');
    $user = DB::collection('users')->where('name', 'John Doe')->first();
    expect($user['age'])->toEqual(11);
    $user = DB::collection('users')->where('name', 'Jane Doe')->first();
    expect($user['age'])->toEqual(21);
    $user = DB::collection('users')->where('name', 'Robert Roe')->first();
    expect($user['age'])->toBeNull();
    $user = DB::collection('users')->where('name', 'Mark Moe')->first();
    expect($user['age'])->toEqual(1);
});

test('projections', function () {
    DB::collection('items')->insert([
        ['name' => 'fork', 'tags' => ['sharp', 'pointy']],
        ['name' => 'spork', 'tags' => ['sharp', 'pointy', 'round', 'bowl']],
        ['name' => 'spoon', 'tags' => ['round', 'bowl']],
    ]);

    $results = DB::collection('items')->project(['tags' => ['$slice' => 1]])->get();

    foreach ($results as $result) {
        expect(count($result['tags']))->toEqual(1);
    }
});

test('value', function () {
    DB::collection('books')->insert([
        ['title' => 'Moby-Dick', 'author' => ['first_name' => 'Herman', 'last_name' => 'Melville']],
    ]);

    expect(DB::collection('books')->value('title'))->toEqual('Moby-Dick');
    expect(DB::collection('books')->value('author'))->toEqual(['first_name' => 'Herman', 'last_name' => 'Melville']);
    expect(DB::collection('books')->value('author.first_name'))->toEqual('Herman');
    expect(DB::collection('books')->value('author.last_name'))->toEqual('Melville');
});

test('hint options', function () {
    DB::collection('items')->insert([
        ['name' => 'fork',  'tags' => ['sharp', 'pointy']],
        ['name' => 'spork', 'tags' => ['sharp', 'pointy', 'round', 'bowl']],
        ['name' => 'spoon', 'tags' => ['round', 'bowl']],
    ]);

    $results = DB::collection('items')->hint(['$natural' => -1])->get();

    expect($results[0]['name'])->toEqual('spoon');
    expect($results[1]['name'])->toEqual('spork');
    expect($results[2]['name'])->toEqual('fork');

    $results = DB::collection('items')->hint(['$natural' => 1])->get();

    expect($results[2]['name'])->toEqual('spoon');
    expect($results[1]['name'])->toEqual('spork');
    expect($results[0]['name'])->toEqual('fork');
});
