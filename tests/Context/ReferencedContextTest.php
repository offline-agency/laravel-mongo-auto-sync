<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use Tests\Models\RefAuthor;
use Tests\Models\RefBook;

beforeEach(function () {
    $mongodb = config('database.connections.mongodb');
    config()->set('database.connections.tenant_b', array_merge($mongodb, ['database' => $mongodb['database'].'_b']));
    DB::purge('tenant_b');
});

/**
 * @return list<array<string, mixed>>
 */
function referencedContextBooks(string $connection): array
{
    return DB::connection($connection)->getCollection('ref_books')
        ->find([], ['sort' => ['_id' => 1], 'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']])->toArray();
}

// F01 x F02: the children of a parent stored on another connection live on that connection.
it('writes and scopes referenced children on the connection of their parent', function () {
    $defaultBooks = referencedContextBooks('mongodb');
    $author = (new RefAuthor)->setConnection('tenant_b');
    $author = $author->storeWithSync(new Request, ['name' => 'tenant author', 'books' => json_encode([['title' => 'T1']]), 'profile' => '[]']);

    $tenantBook = RefBook::on('tenant_b')->where('ref_author_id', $author->id)->first();
    expect($tenantBook?->title)->toBe('T1')
        ->and(referencedContextBooks('mongodb'))->toEqual($defaultBooks);

    $author->updateWithSync(new Request, ['name' => 'tenant author', 'books' => json_encode([['id' => $tenantBook->id, 'title' => 'T1 v2']]), 'profile' => '[]']);
    expect(RefBook::on('tenant_b')->find($tenantBook->id)?->title)->toBe('T1 v2');

    // a child of the default database is not reachable from the tenant parent, even with the same parent id
    $foreign = new RefBook;
    $foreign->title = 'default';
    $foreign->ref_author_id = $author->id;
    $foreign->save();
    $before = referencedContextBooks('mongodb');
    expect(fn () => $author->updateWithSync(new Request, ['name' => 'tenant author', 'books' => json_encode([['id' => $foreign->id, 'title' => 'taken']]), 'profile' => '[]']))
        ->toThrow(InvalidRequestException::class, "Referenced document not found in relationship 'books'.")
        ->and(referencedContextBooks('mongodb'))->toEqual($before);
});

it('isolates overlapping tenants through create, update, omission delete and a retried request', function () {
    $author = (new RefAuthor)->setConnection('tenant_b')->storeWithSync(new Request, ['name' => 'tenant', 'books' => json_encode([['title' => 'T1'], ['title' => 'T2']]), 'profile' => '[]']);
    $t1 = RefBook::on('tenant_b')->where('ref_author_id', $author->id)->where('title', 'T1')->first();
    $t2 = RefBook::on('tenant_b')->where('ref_author_id', $author->id)->where('title', 'T2')->first();
    // the default database holds documents with the SAME ids and the same parent id
    foreach ([$t1, $t2] as $book) {
        DB::connection('mongodb')->getCollection('ref_books')->insertOne(['_id' => new ObjectId($book->id), 'title' => 'default copy', 'ref_author_id' => $author->id]);
    }
    $default = referencedContextBooks('mongodb');
    $request = ['name' => 'tenant', 'books' => json_encode([['id' => $t1->id, 'title' => 'T1 v2']]), 'profile' => '[]'];

    $author->updateWithSync(new Request, $request);
    $author->updateWithSync(new Request, $request);

    expect(RefBook::on('tenant_b')->where('ref_author_id', $author->id)->pluck('title')->all())->toBe(['T1 v2'])
        ->and(referencedContextBooks('mongodb'))->toEqual($default);
});
