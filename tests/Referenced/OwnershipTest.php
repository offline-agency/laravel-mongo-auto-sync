<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Relationships\ReferencedWritePlanner;
use Tests\Models\RefAuthor;
use Tests\Models\RefBook;
use Tests\Models\RefProfile;

/**
 * Raw documents of every collection touched by referenced writes, to prove that a rejected request
 * changed nothing (values and timestamps included).
 *
 * @return array<string, list<array<string, mixed>>>
 */
function ownershipSnapshot(): array
{
    $snapshot = [];
    foreach (['ref_authors', 'ref_books', 'ref_profiles'] as $collection) {
        $snapshot[$collection] = DB::connection('mongodb')->getCollection($collection)
            ->find([], ['sort' => ['_id' => 1], 'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])
            ->toArray();
    }

    return $snapshot;
}

/**
 * @param  list<array<string, mixed>>  $books
 * @param  array<string, mixed>|null  $profile
 */
function ownershipAuthor(string $name, array $books, ?array $profile = null): RefAuthor
{
    return (new RefAuthor)->storeWithSync(new Request, [
        'name' => $name,
        'books' => json_encode($books),
        'profile' => json_encode($profile === null ? [] : [$profile]),
    ]);
}

function ownershipBook(RefAuthor $author, string $title): RefBook
{
    $book = RefBook::where('ref_author_id', $author->id)->where('title', $title)->first();
    expect($book)->not->toBeNull();

    return $book;
}

it('rejects an update that references a child of another parent and changes nothing', function (callable $books) {
    $a = ownershipAuthor('A', [['title' => 'A1'], ['title' => 'A2']], ['bio' => 'A bio']);
    $b = ownershipAuthor('B', [['title' => 'B1']], ['bio' => 'B bio']);
    $sentinel = ownershipAuthor('sentinel', [['title' => 'S1']]);
    $before = ownershipSnapshot();

    expect(fn () => RefAuthor::find($a->id)->updateWithSync(new Request, [
        'name' => 'A renamed',
        'books' => json_encode($books($a, $b)),
        'profile' => json_encode([['bio' => 'A bio 2']]),
    ]))->toThrow(InvalidRequestException::class, "Referenced document not found in relationship 'books'");

    expect(ownershipSnapshot())->toEqual($before);
})->with([
    "B's child alone" => [fn (RefAuthor $a, RefAuthor $b) => [['id' => ownershipBook($b, 'B1')->id, 'title' => 'stolen']]],
    "a valid entry, then B's child" => [fn (RefAuthor $a, RefAuthor $b) => [
        ['id' => ownershipBook($a, 'A1')->id, 'title' => 'A1 v2'],
        ['id' => ownershipBook($b, 'B1')->id, 'title' => 'stolen'],
    ]],
    "B's child through _id" => [fn (RefAuthor $a, RefAuthor $b) => [['_id' => ownershipBook($b, 'B1')->id, 'title' => 'stolen']]],
    'an unknown id' => [fn (RefAuthor $a, RefAuthor $b) => [['id' => '650000000000000000000099', 'title' => 'ghost']]],
]);

it('rejects malformed referenced payloads before the parent or any child is written', function (callable $payload, string $message) {
    $a = ownershipAuthor('A', [['title' => 'A1'], ['title' => 'A2']], ['bio' => 'A bio']);
    $b = ownershipAuthor('B', [['title' => 'B1']], ['bio' => 'B bio']);
    $before = ownershipSnapshot();

    expect(fn () => RefAuthor::find($a->id)->updateWithSync(new Request, ['name' => 'A renamed'] + $payload($a, $b)))
        ->toThrow(InvalidRequestException::class, $message);

    expect(ownershipSnapshot())->toEqual($before);
})->with([
    'the same child twice' => [fn (RefAuthor $a) => ['books' => json_encode([
        ['id' => ownershipBook($a, 'A1')->id, 'title' => 'x'], ['id' => ownershipBook($a, 'A1')->id, 'title' => 'y'],
    ]), 'profile' => '[]'], "Duplicate referenced document in relationship 'books'."],
    'id and _id that disagree' => [fn (RefAuthor $a) => ['books' => json_encode([
        ['id' => ownershipBook($a, 'A1')->id, '_id' => ownershipBook($a, 'A2')->id, 'title' => 'x'],
    ]), 'profile' => '[]'], "Duplicate referenced document in relationship 'books'."],
    'a spoofed foreign key' => [fn (RefAuthor $a, RefAuthor $b) => ['books' => json_encode([
        ['id' => ownershipBook($a, 'A1')->id, 'title' => 'x', 'ref_author_id' => $b->id],
    ]), 'profile' => '[]'], "Field 'ref_author_id' is not writable in relationship 'books'."],
    'a field that is not an item of the child' => [fn (RefAuthor $a) => ['books' => json_encode([
        ['title' => 'new', 'is_admin' => true],
    ]), 'profile' => '[]'], "Field 'is_admin' is not writable in relationship 'books'."],
    'two documents in a HasOne' => [fn (RefAuthor $a) => ['books' => '[]', 'profile' => json_encode([
        ['bio' => 'one'], ['bio' => 'two'],
    ])], "Relationship 'profile' holds at most one document."],
    "B's profile in A's HasOne" => [fn (RefAuthor $a, RefAuthor $b) => ['books' => '[]', 'profile' => json_encode([
        ['id' => $b->profile->id, 'bio' => 'stolen'],
    ])], "Referenced document not found in relationship 'profile'"],
    'an operator-shaped id' => [fn (RefAuthor $a) => ['books' => json_encode([
        ['id' => ['$ne' => null], 'title' => 'x'],
    ]), 'profile' => '[]'], "Invalid id in relationship 'books'"],
]);

it('rejects an id supplied while the parent is created, without writing the parent', function () {
    $b = ownershipAuthor('B', [['title' => 'B1']]);
    $before = ownershipSnapshot();

    expect(fn () => ownershipAuthor('new', [['id' => ownershipBook($b, 'B1')->id, 'title' => 'adopted']]))
        ->toThrow(InvalidRequestException::class, "Referenced document not found in relationship 'books'");

    expect(ownershipSnapshot())->toEqual($before);
});

it('keeps legitimate referenced CRUD working in both synchronisation modes', function (bool $background) {
    config()->set('laravel-mongo-auto-sync.use_background_sync', $background);
    $a = ownershipAuthor('A', [['title' => 'A1'], ['title' => 'A2']], ['bio' => 'A bio']);
    $b = ownershipAuthor('B', [['title' => 'B1']], ['bio' => 'B bio']);
    $a1 = ownershipBook($a, 'A1');
    $profile = $a->profile;
    $bBefore = RefBook::where('ref_author_id', $b->id)->get()->map->getAttributes()->all();

    RefAuthor::find($a->id)->updateWithSync(new Request, [
        'name' => 'A renamed',
        // ObjectId-shaped id in _id form, a new child, A2 left out (deleted, as documented)
        'books' => json_encode([['_id' => (string) $a1->id, 'title' => 'A1 v2'], ['title' => 'A3']]),
        'profile' => json_encode([['id' => $profile->id, 'bio' => 'A bio 2']]),
    ]);

    expect(RefBook::where('ref_author_id', $a->id)->pluck('title')->sort()->values()->all())->toBe(['A1 v2', 'A3'])
        ->and(RefBook::find($a1->id)->title)->toBe('A1 v2')
        ->and(RefProfile::where('ref_author_id', $a->id)->get()->map->bio->all())->toBe(['A bio 2'])
        ->and((string) RefProfile::where('ref_author_id', $a->id)->first()->id)->toBe((string) $profile->id)
        ->and(RefBook::where('ref_author_id', $b->id)->get()->map->getAttributes()->all())->toEqual($bBefore);
})->with(['synchronous' => [false], 'background' => [true]]);

it('fires the Eloquent events of the children it writes', function () {
    $events = [];
    RefBook::saved(function (RefBook $book) use (&$events) {
        $events[] = 'saved:'.$book->title;
    });
    RefBook::deleted(function (RefBook $book) use (&$events) {
        $events[] = 'deleted:'.$book->title;
    });
    $a = ownershipAuthor('A', [['title' => 'E1'], ['title' => 'E2']]);

    RefAuthor::find($a->id)->updateWithSync(new Request, [
        'name' => 'A', 'books' => json_encode([['id' => ownershipBook($a, 'E1')->id, 'title' => 'E1 v2']]), 'profile' => '[]',
    ]);

    expect($events)->toContain('saved:E1', 'saved:E2', 'saved:E1 v2', 'deleted:E2');
});

it('rejects a referenced relationship whose configuration does not match the declared relation', function (array $relation, string $method, string $message) {
    expect(fn () => ReferencedWritePlanner::plan(new RefAuthor, $method, $relation, []))
        ->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'a model that is not an MDModel' => [['type' => 'HasMany', 'model' => stdClass::class, 'foreignKey' => 'ref_author_id'], 'books', "The model of relationship 'books' must extend"],
    'no foreign key' => [['type' => 'HasMany', 'model' => RefBook::class], 'books', "Relationship 'books' must declare its foreignKey."],
    'another foreign key' => [['type' => 'HasMany', 'model' => RefBook::class, 'foreignKey' => 'ref_publisher_id'], 'books', "Relationship 'books' must be a hasOne/hasMany of"],
    'another child model' => [['type' => 'HasMany', 'model' => RefProfile::class, 'foreignKey' => 'ref_author_id'], 'books', "Relationship 'books' must be a hasOne/hasMany of"],
    'no relation method' => [['type' => 'HasMany', 'model' => RefBook::class, 'foreignKey' => 'ref_author_id'], 'missing', "Relationship 'missing' must be a hasOne/hasMany of"],
]);

it('rejects an empty or non-string child id given to the planner directly', function (mixed $id) {
    $relation = ['type' => 'HasMany', 'model' => RefBook::class, 'foreignKey' => 'ref_author_id'];

    expect(fn () => ReferencedWritePlanner::plan(new RefAuthor, 'books', $relation, [(object) ['id' => $id, 'title' => 'x']]))
        ->toThrow(InvalidRequestException::class, "Invalid id in relationship 'books': expected a string id.");
})->with(['empty string' => [''], 'integer' => [7]]);
