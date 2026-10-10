<?php

use Illuminate\Http\Request;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use Tests\Models\RefAuthor;
use Tests\Models\RefBook;

// F01 x F07: disabling target sync is not a way around referenced-child ownership, and children are still written.
it('keeps referenced ownership and writes children when target sync is disabled', function () {
    $a = ownershipAuthor('A', [['title' => 'A1']]);
    $b = ownershipAuthor('B', [['title' => 'B1']]);
    $before = ownershipSnapshot();

    expect(fn () => RefAuthor::find($a->id)->updateWithSync(new Request, [
        'name' => 'A', 'books' => json_encode([['id' => ownershipBook($b, 'B1')->id, 'title' => 'stolen']]), 'profile' => '[]',
    ], ['sync_targets' => false]))->toThrow(InvalidRequestException::class, "Referenced document not found in relationship 'books'.");
    expect(ownershipSnapshot())->toEqual($before);

    RefAuthor::find($a->id)->updateWithSync(new Request, [
        'name' => 'A', 'books' => json_encode([['id' => ownershipBook($a, 'A1')->id, 'title' => 'A1 v2'], ['title' => 'A2']]), 'profile' => '[]',
    ], ['sync_targets' => false]);

    expect(RefBook::where('ref_author_id', $a->id)->pluck('title')->sort()->values()->all())->toBe(['A1 v2', 'A2']);
});
