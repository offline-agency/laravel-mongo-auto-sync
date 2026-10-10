<?php

use Illuminate\Http\Request;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use Tests\Models\RefAuthor;
use Tests\Models\RefBook;

// The example of docs/referenced-relationships.md, on the RefAuthor / RefBook / RefProfile fixtures.
it('runs the documented referenced relationship example', function () {
    $author = (new RefAuthor)->storeWithSync(new Request, [
        'name' => 'Ada',
        'books' => json_encode([['title' => 'Notes'], ['title' => 'Sketches']]),
        'profile' => json_encode([['bio' => 'Mathematician']]),
    ]);

    $notes = $author->books->firstWhere('title', 'Notes');

    $author->updateWithSync(new Request, [
        'name' => 'Ada',
        'books' => json_encode([
            ['id' => $notes->id, 'title' => 'Notes, 2nd edition'],
            ['title' => 'Letters'],
        ]),
        'profile' => json_encode([['id' => $author->profile->id, 'bio' => 'Mathematician and writer']]),
    ]);

    $fresh = RefAuthor::find($author->id);
    expect($fresh->books->pluck('title')->sort()->values()->all())->toBe(['Letters', 'Notes, 2nd edition'])
        ->and((string) $fresh->books->firstWhere('title', 'Notes, 2nd edition')->id)->toBe((string) $notes->id)
        ->and($fresh->profile->bio)->toBe('Mathematician and writer');

    $other = (new RefAuthor)->storeWithSync(new Request, ['name' => 'Bob', 'books' => '[]', 'profile' => '[]']);
    expect(fn () => $other->updateWithSync(new Request, [
        'name' => 'Bob', 'books' => json_encode([['id' => $notes->id, 'title' => 'mine']]), 'profile' => '[]',
    ]))->toThrow(InvalidRequestException::class, "Referenced document not found in relationship 'books'.")
        ->and(RefBook::find($notes->id)->title)->toBe('Notes, 2nd edition');
});
