<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Exceptions\ReferencedConflictException;
use Tests\Models\RefAuthor;
use Tests\Models\RefBook;
use Tests\Models\RefCurator;
use Tests\Models\RefNestedParent;
use Tests\Models\RefOwner;
use Tests\Models\RefSoftChild;
use Tests\Models\RefSoftParent;

/**
 * Raw documents of $collections (values and timestamps), to prove that nothing was written.
 *
 * @param  list<string>  $collections
 * @return array<string, list<array<string, mixed>>>
 */
function auditSnapshot(array $collections): array
{
    $snapshot = [];
    foreach ($collections as $collection) {
        $snapshot[$collection] = DB::connection('mongodb')->getCollection($collection)
            ->find([], ['sort' => ['_id' => 1], 'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])->toArray();
    }

    return $snapshot;
}

/**
 * A book written directly in the collection (raw), owned by $owner through $foreignKey.
 *
 * @param  array<string, mixed>  $extra
 */
function auditRawBook(string $foreignKey, string $owner, string $title, array $extra = []): string
{
    $id = new ObjectId;
    DB::connection('mongodb')->getCollection('ref_books')->insertOne(['_id' => $id, 'title' => $title, $foreignKey => $owner] + $extra);

    return (string) $id;
}

/**
 * @return array<string, mixed>|null
 */
function auditRawDocument(string $collection, string $id): ?array
{
    $document = DB::connection('mongodb')->getCollection($collection)
        ->findOne(['_id' => new ObjectId($id)], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);

    return is_array($document) ? $document : null;
}

// 4.1 – relation method constraints, not only the foreign key
it('neither updates, reveals nor deletes a child that the declared relation excludes', function () {
    $curator = (new RefCurator)->storeWithSync(new Request, ['name' => 'curator', 'books' => '[]']);
    $active = auditRawBook('ref_curator_id', $curator->id, 'active', ['status' => 'active']);
    $archived = auditRawBook('ref_curator_id', $curator->id, 'archived', ['status' => 'archived']);
    $before = auditSnapshot(['ref_curators', 'ref_books']);

    expect(fn () => RefCurator::find($curator->id)->updateWithSync(new Request, ['name' => 'curator', 'books' => json_encode([['id' => $archived, 'title' => 'taken']])]))
        ->toThrow(InvalidRequestException::class, "Referenced document not found in relationship 'books'.")
        ->and(auditSnapshot(['ref_curators', 'ref_books']))->toEqual($before);

    // the payload leaves out every child: only the active one belongs to the relation and is deleted
    RefCurator::find($curator->id)->updateWithSync(new Request, ['name' => 'curator', 'books' => '[]']);
    expect(auditRawDocument('ref_books', $active))->toBeNull()
        ->and(auditRawDocument('ref_books', $archived)['title'] ?? null)->toBe('archived');
});

it('derives the foreign key value from the declared local key', function () {
    $owner = (new RefOwner)->storeWithSync(new Request, ['name' => 'owner', 'code' => 'OWN-1', 'books' => json_encode([['title' => 'by code']])]);

    $book = RefBook::where('ref_owner_code', 'OWN-1')->first();
    expect($book?->title)->toBe('by code');

    $owner->updateWithSync(new Request, ['name' => 'owner', 'code' => 'OWN-1', 'books' => json_encode([['id' => $book->id, 'title' => 'by code v2']])]);
    expect(RefBook::find($book->id)->title)->toBe('by code v2');
});

it('rejects a configured localKey that differs from the declared relation', function () {
    $owner = new class extends RefOwner
    {
        public function getMongoRelation(): array
        {
            return ['books' => ['localKey' => 'id'] + parent::getMongoRelation()['books']];
        }
    };

    expect(fn () => $owner->storeWithSync(new Request, ['name' => 'owner', 'code' => 'OWN-2', 'books' => '[]']))
        ->toThrow(InvalidConfigurationException::class, "Relationship 'books' must be a hasOne/hasMany");
});

// 4.2 – ownership is enforced when writing, not only when planning
it('does not update a child that another parent took over after the plan was built', function () {
    $a = (new RefAuthor)->storeWithSync(new Request, ['name' => 'A', 'books' => json_encode([['title' => 'A1'], ['title' => 'A2']]), 'profile' => '[]']);
    $b = (new RefAuthor)->storeWithSync(new Request, ['name' => 'B', 'books' => '[]', 'profile' => '[]']);
    $a1 = RefBook::where('ref_author_id', $a->id)->where('title', 'A1')->first();
    $a2 = RefBook::where('ref_author_id', $a->id)->where('title', 'A2')->first();

    // barrier: while A1 is being saved, worker B takes A2 over
    RefBook::saving(function (RefBook $book) use ($a1, $a2, $b) {
        if ((string) $book->id === (string) $a1->id) {
            DB::connection('mongodb')->getCollection('ref_books')->updateOne(['_id' => new ObjectId($a2->id)], ['$set' => ['ref_author_id' => $b->id, 'title' => 'B owns it']]);
        }
    });

    expect(fn () => RefAuthor::find($a->id)->updateWithSync(new Request, ['name' => 'A', 'profile' => '[]', 'books' => json_encode([
        ['id' => $a1->id, 'title' => 'A1 v2'], ['id' => $a2->id, 'title' => 'A2 v2'],
    ])]))->toThrow(ReferencedConflictException::class, "A referenced document of relationship 'books' changed owner while it was being written.");

    $raw = auditRawDocument('ref_books', $a2->id);
    expect($raw['ref_author_id'])->toBe($b->id)
        ->and($raw['title'])->toBe('B owns it');
});

it('does not delete a left-out child that another parent took over after the plan was built', function () {
    $a = (new RefAuthor)->storeWithSync(new Request, ['name' => 'A', 'books' => json_encode([['title' => 'A1'], ['title' => 'A2'], ['title' => 'A3']]), 'profile' => '[]']);
    $b = (new RefAuthor)->storeWithSync(new Request, ['name' => 'B', 'books' => '[]', 'profile' => '[]']);
    $a1 = RefBook::where('ref_author_id', $a->id)->where('title', 'A1')->first();
    $a2 = RefBook::where('ref_author_id', $a->id)->where('title', 'A2')->first();
    $a3 = RefBook::where('ref_author_id', $a->id)->where('title', 'A3')->first();
    $deleted = [];
    RefBook::deleted(function (RefBook $book) use (&$deleted) {
        $deleted[] = $book->title;
    });
    // barrier: while A2 is being deleted, worker B takes A3 over
    RefBook::deleting(function (RefBook $book) use ($a2, $a3, $b) {
        if ((string) $book->id === (string) $a2->id) {
            DB::connection('mongodb')->getCollection('ref_books')->updateOne(['_id' => new ObjectId($a3->id)], ['$set' => ['ref_author_id' => $b->id]]);
        }
    });

    RefAuthor::find($a->id)->updateWithSync(new Request, ['name' => 'A', 'profile' => '[]', 'books' => json_encode([['id' => $a1->id, 'title' => 'A1']])]);

    expect(auditRawDocument('ref_books', $a2->id))->toBeNull()
        ->and(auditRawDocument('ref_books', $a3->id)['ref_author_id'] ?? null)->toBe($b->id)
        ->and($deleted)->toBe(['A2']);
});

// HasOne cardinality on legacy data
it('rejects writing a HasOne relationship that already holds several documents', function () {
    $a = (new RefAuthor)->storeWithSync(new Request, ['name' => 'A', 'books' => '[]', 'profile' => json_encode([['bio' => 'first']])]);
    DB::connection('mongodb')->getCollection('ref_profiles')->insertOne(['bio' => 'legacy duplicate', 'ref_author_id' => $a->id]);
    $before = auditSnapshot(['ref_authors', 'ref_profiles']);

    expect(fn () => RefAuthor::find($a->id)->updateWithSync(new Request, ['name' => 'A renamed', 'books' => '[]', 'profile' => json_encode([['bio' => 'second']])]))
        ->toThrow(ReferencedConflictException::class, "Relationship 'profile' holds more than one document for this parent: resolve the duplicates before writing it.")
        ->and(auditSnapshot(['ref_authors', 'ref_profiles']))->toEqual($before);
});

// 4.3 – preflight covers the children's own request contract
it('rejects a child payload that its own full request would reject, before any write', function (string $children, string $message) {
    $parent = (new RefNestedParent)->storeWithSync(new Request, ['name' => 'P', 'children' => json_encode([['title' => 'kept', 'notes' => '[]']])]);
    $before = auditSnapshot(['ref_nested_parents', 'ref_nested_children']);

    expect(fn () => RefNestedParent::find($parent->id)->updateWithSync(new Request, ['name' => 'P renamed', 'children' => $children]))
        ->toThrow(InvalidRequestException::class, $message)
        ->and(auditSnapshot(['ref_nested_parents', 'ref_nested_children']))->toEqual($before);
})->with([
    'a child relationship left out' => [json_encode([['title' => 'new']]), "Missing relationship 'notes' in the request."],
    'a child item left out' => [json_encode([['notes' => '[]']]), "Error - 'title' attribute not found in the request."],
    'a malformed child relationship' => [json_encode([['title' => 'new', 'notes' => '5']]), "Invalid payload for relationship 'notes'"],
]);

it('writes nothing when the second referenced relationship of the request is invalid', function () {
    $a = (new RefAuthor)->storeWithSync(new Request, ['name' => 'A', 'books' => json_encode([['title' => 'A1']]), 'profile' => json_encode([['bio' => 'A']])]);
    $b = (new RefAuthor)->storeWithSync(new Request, ['name' => 'B', 'books' => '[]', 'profile' => json_encode([['bio' => 'B']])]);
    $before = auditSnapshot(['ref_authors', 'ref_books', 'ref_profiles']);

    expect(fn () => RefAuthor::find($a->id)->updateWithSync(new Request, [
        'name' => 'A renamed',
        'books' => json_encode([['title' => 'new book']]),
        'profile' => json_encode([['id' => $b->profile->id, 'bio' => 'stolen']]),
    ]))->toThrow(InvalidRequestException::class, "Referenced document not found in relationship 'profile'.")
        ->and(auditSnapshot(['ref_authors', 'ref_books', 'ref_profiles']))->toEqual($before);
});

// 4.4 / 4.5 – soft deleted children and lifecycle on fresh state
it('treats a soft-deleted child as absent and soft-deletes left-out children', function () {
    $parent = (new RefSoftParent)->storeWithSync(new Request, ['name' => 'P', 'children' => json_encode([['title' => 'one'], ['title' => 'two']])]);
    $one = RefSoftChild::where('title', 'one')->where('ref_soft_parent_id', $parent->id)->first();
    $two = RefSoftChild::where('title', 'two')->where('ref_soft_parent_id', $parent->id)->first();
    $one->delete();

    expect(fn () => RefSoftParent::find($parent->id)->updateWithSync(new Request, ['name' => 'P', 'children' => json_encode([['id' => $one->id, 'title' => 'revived']])]))
        ->toThrow(InvalidRequestException::class, "Referenced document not found in relationship 'children'.");

    RefSoftParent::find($parent->id)->updateWithSync(new Request, ['name' => 'P', 'children' => '[]']);
    expect(RefSoftChild::withTrashed()->find($two->id)?->trashed())->toBeTrue()
        ->and(auditRawDocument('ref_soft_children', $one->id)['title'] ?? null)->toBe('one');
});

it('deletes on destroyWithSync only the children the parent owns when the delete runs', function () {
    $author = new class extends RefAuthor
    {
        public function getMongoRelation(): array
        {
            return array_map(fn (array $relation) => $relation + ['onParentDelete' => 'delete'], parent::getMongoRelation());
        }
    };
    $a = $author->storeWithSync(new Request, ['name' => 'A', 'books' => json_encode([['title' => 'A1'], ['title' => 'A2']]), 'profile' => '[]']);
    $b = (new RefAuthor)->storeWithSync(new Request, ['name' => 'B', 'books' => '[]', 'profile' => '[]']);
    $a2 = RefBook::where('ref_author_id', $a->id)->where('title', 'A2')->first();
    // the relation was loaded (cached) before another parent took A2 over
    $a->load('books');
    DB::connection('mongodb')->getCollection('ref_books')->updateOne(['_id' => new ObjectId($a2->id)], ['$set' => ['ref_author_id' => $b->id]]);

    $a->destroyWithSync();

    expect(RefBook::where('ref_author_id', $a->id)->count())->toBe(0)
        ->and(auditRawDocument('ref_books', $a2->id)['ref_author_id'] ?? null)->toBe($b->id);
});

// optional transactions (replica set): the caller's transaction makes the whole write atomic
it('rolls back the parent and every child when the caller wraps the write in a transaction', function () {
    $hello = (array) DB::connection('mongodb')->getMongoDB()->command(['hello' => 1])->toArray()[0];
    if (! isset($hello['setName'])) {
        $this->markTestSkipped('Transactions need a replica set (set MONGO_PORT to the rs0 member, see docs/compatibility/test-matrix.md).');
    }
    $a = (new RefAuthor)->storeWithSync(new Request, ['name' => 'A', 'books' => json_encode([['title' => 'A1'], ['title' => 'A2']]), 'profile' => '[]']);
    $before = auditSnapshot(['ref_authors', 'ref_books', 'ref_profiles']);
    RefBook::saving(function (RefBook $book) {
        if ($book->title === 'fails') {
            throw new RuntimeException('database failure while writing a child');
        }
    });

    expect(fn () => DB::connection('mongodb')->transaction(fn () => RefAuthor::find($a->id)->updateWithSync(new Request, [
        'name' => 'A renamed', 'profile' => '[]', 'books' => json_encode([['title' => 'new'], ['title' => 'fails']]),
    ])))->toThrow(RuntimeException::class, 'database failure while writing a child')
        ->and(auditSnapshot(['ref_authors', 'ref_books', 'ref_profiles']))->toEqual($before);
});

it('deletes no child when a parent that was never stored is destroyed', function () {
    $orphan = auditRawBook('ref_author_id', '', 'orphan without parent');
    $author = new class extends RefAuthor
    {
        public function getMongoRelation(): array
        {
            return array_map(fn (array $relation) => $relation + ['onParentDelete' => 'delete'], parent::getMongoRelation());
        }
    };

    $author->destroyWithSync();

    expect(auditRawDocument('ref_books', $orphan)['title'] ?? null)->toBe('orphan without parent');
});
