<?php

use Illuminate\Http\Request;
use Tests\Models\RefAuthor;
use Tests\Models\RefBook;
use Tests\Models\RefProfile;
use Tests\Models\RefPublisher;

/**
 * @param  list<array<string, mixed>>  $books
 * @param  array<string, mixed>|null  $profile
 */
function traitsStoreAuthor(array $books, ?array $profile = null): RefAuthor
{
    return (new RefAuthor)->storeWithSync(new Request, [
        'name' => 'author '.uniqid(),
        'books' => json_encode($books),
        'profile' => json_encode($profile === null ? [] : [$profile]),
    ]);
}

/**
 * @return list<string>
 */
function traitsBookTitles(string $authorId): array
{
    return RefBook::where('ref_author_id', $authorId)->get()->pluck('title')->sort()->values()->all();
}

test('storeWithSync creates HasMany and HasOne children holding the foreign key', function () {
    $author = traitsStoreAuthor([['title' => 'First'], ['title' => 'Second']], ['bio' => 'hello']);

    expect(traitsBookTitles($author->id))->toBe(['First', 'Second']);
    expect($author->books)->toHaveCount(2);
    expect($author->profile)->toBeInstanceOf(RefProfile::class);
    expect($author->profile->bio)->toBe('hello');
    expect($author->profile->ref_author_id)->toBe($author->id);
});

test('storeWithSync with empty referenced relationships creates no children', function () {
    $author = traitsStoreAuthor([]);

    expect(traitsBookTitles($author->id))->toBe([]);
    expect($author->profile)->toBeNull();
});

test('updateWithSync updates kept children, creates new ones and deletes the missing ones', function () {
    $author = traitsStoreAuthor([['title' => 'Kept'], ['title' => 'Dropped']], ['bio' => 'old']);
    $kept = RefBook::where('ref_author_id', $author->id)->where('title', 'Kept')->first();
    $dropped = RefBook::where('ref_author_id', $author->id)->where('title', 'Dropped')->first();
    $profile = $author->profile;

    RefAuthor::find($author->id)->updateWithSync(new Request, [
        'name' => $author->name,
        'books' => json_encode([['id' => $kept->id, 'title' => 'Kept v2'], ['title' => 'Added']]),
        // children can also be identified by _id
        'profile' => json_encode([['_id' => $profile->id, 'bio' => 'new']]),
    ]);

    expect(traitsBookTitles($author->id))->toBe(['Added', 'Kept v2']);
    expect(RefBook::find($kept->id)->title)->toBe('Kept v2');
    expect(RefBook::find($dropped->id))->toBeNull();

    $profiles = RefProfile::where('ref_author_id', $author->id)->get();
    expect($profiles)->toHaveCount(1);
    expect((string) $profiles->first()->id)->toBe((string) $profile->id);
    expect($profiles->first()->bio)->toBe('new');
});

test('updateWithSync replaces a HasOne child sent without id', function () {
    $author = traitsStoreAuthor([], ['bio' => 'first']);
    $old = $author->profile;

    RefAuthor::find($author->id)->updateWithSync(new Request, [
        'name' => $author->name,
        'books' => json_encode([]),
        'profile' => json_encode([['bio' => 'second']]),
    ]);

    expect(RefProfile::find($old->id))->toBeNull();
    $profiles = RefProfile::where('ref_author_id', $author->id)->get();
    expect($profiles)->toHaveCount(1);
    expect($profiles->first()->bio)->toBe('second');
});

test('updateWithSync with empty referenced relationships deletes every child', function () {
    $author = traitsStoreAuthor([['title' => 'One'], ['title' => 'Two']], ['bio' => 'bio']);
    $other = traitsStoreAuthor([['title' => 'Other']], ['bio' => 'other']);

    RefAuthor::find($author->id)->updateWithSync(new Request, [
        'name' => $author->name,
        'books' => '[]',
        'profile' => '',
    ]);

    expect(traitsBookTitles($author->id))->toBe([]);
    expect(RefProfile::where('ref_author_id', $author->id)->count())->toBe(0);
    // children of other parents are untouched
    expect(traitsBookTitles($other->id))->toBe(['Other']);
    expect($other->profile->bio)->toBe('other');
});

test('a partial updateWithSync without the relationship keys leaves the children untouched', function () {
    $author = traitsStoreAuthor([['title' => 'Stays']], ['bio' => 'stays']);

    $updated = RefAuthor::find($author->id)->updateWithSync(new Request, ['name' => 'renamed'], ['request_type' => 'partial']);

    expect($updated->name)->toBe('renamed');
    expect(traitsBookTitles($author->id))->toBe(['Stays']);
    expect($updated->profile->bio)->toBe('stays');
});

test('destroyWithSync deletes the HasMany and HasOne children of relationships with target', function () {
    $publisher = new RefPublisher;
    $publisher->name = 'publisher';
    $publisher->save();
    $otherPublisher = new RefPublisher;
    $otherPublisher->name = 'other publisher';
    $otherPublisher->save();

    $books = collect(['A', 'B'])->map(function (string $title) use ($publisher) {
        $book = new RefBook;
        $book->title = $title;
        $book->ref_publisher_id = $publisher->id;
        $book->save();

        return $book;
    });
    $profile = new RefProfile;
    $profile->bio = 'publisher bio';
    $profile->ref_publisher_id = $publisher->id;
    $profile->save();
    $foreignBook = new RefBook;
    $foreignBook->title = 'not mine';
    $foreignBook->ref_publisher_id = $otherPublisher->id;
    $foreignBook->save();

    RefPublisher::find($publisher->id)->destroyWithSync();

    expect(RefPublisher::find($publisher->id))->toBeNull();
    foreach ($books as $book) {
        expect(RefBook::find($book->id))->toBeNull();
    }
    expect(RefProfile::find($profile->id))->toBeNull();
    expect(RefBook::find($foreignBook->id))->not->toBeNull();
});

test('destroyWithSync of a parent without referenced children only deletes the parent', function () {
    $publisher = new RefPublisher;
    $publisher->name = 'lonely';
    $publisher->save();

    RefPublisher::find($publisher->id)->destroyWithSync();

    expect(RefPublisher::find($publisher->id))->toBeNull();
});
