<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
