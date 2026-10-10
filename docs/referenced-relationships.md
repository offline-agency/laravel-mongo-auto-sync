# Referenced relationships (`HasOne` / `HasMany`)

`storeWithSync()` and `updateWithSync()` can write the children of a referenced relationship: separate documents that hold the parent's key in a foreign key. The example below is executed by `tests/Referenced/ExampleTest.php`.

## Configuration

```php
class Author extends MDModel
{
    protected $items = ['name' => []];

    protected $mongoRelation = [
        'books' => [
            'type' => 'HasMany',
            'model' => Book::class,
            'has-target' => false,          // referenced children have no mini model on a target
            'foreignKey' => 'author_id',    // required: the only source of the foreign key
            'onParentDelete' => 'retain',   // optional: 'retain' or 'delete', see "Lifecycle"
        ],
        'profile' => [
            'type' => 'HasOne',
            'model' => Profile::class,
            'has-target' => false,
            'foreignKey' => 'author_id',
        ],
    ];

    public function books()
    {
        return $this->hasMany(Book::class, 'author_id');   // must match model and foreignKey
    }

    public function profile()
    {
        return $this->hasOne(Profile::class, 'author_id');
    }
}

class Book extends MDModel
{
    protected $items = ['title' => []];
}
```

The child model must extend `MDModel`, the relation method must be a `hasOne` / `hasMany` of the configured model on the configured `foreignKey` and `localKey` (default `id`), otherwise `InvalidConfigurationException` is thrown. Extra conditions written in the relation method (`->where('status', 'active')`) are part of the relation: a child they exclude is never updated, deleted or adopted through the payload.

## Writing children

```php
$author = (new Author)->storeWithSync(new Request, [
    'name' => 'Ada',
    'books' => json_encode([['title' => 'Notes'], ['title' => 'Sketches']]),   // no id: created
    'profile' => json_encode([['bio' => 'Mathematician']]),
]);

$notes = $author->books->firstWhere('title', 'Notes');

$author->updateWithSync(new Request, [
    'name' => 'Ada',
    'books' => json_encode([
        ['id' => $notes->id, 'title' => 'Notes, 2nd edition'],   // an id of one of this author's books: updated
        ['title' => 'Letters'],                                 // no id: created
    ]),                                                         // 'Sketches' is left out: deleted
    'profile' => json_encode([['id' => $author->profile->id, 'bio' => 'Mathematician and writer']]),
]);
```

| Payload document | Result |
| --- | --- |
| no `id` / `_id` | a new child, with the foreign key set to the parent's key |
| `id` (or `_id`) of a child that the parent holds through the relation | that child is updated through its own `updateWithSync()` |
| `id` of another parent's child, or an unknown `id` | `InvalidRequestException: Referenced document not found in relationship 'books'.` (the same message for both: the response does not reveal whether the id exists) |
| an `id` while the parent is being created | rejected as above: a new parent holds no child, existing documents are never adopted |
| the same child twice, or `id` and `_id` naming different children | `InvalidRequestException: Duplicate referenced document ...` |
| a field that is not an item or a relationship of the child, or the foreign key | `InvalidRequestException: Field 'x' is not writable ...` |
| more than one document in a `HasOne` | `InvalidRequestException: Relationship 'profile' holds at most one document.` |
| a `HasOne` that already holds several stored documents (legacy data) | `ReferencedConflictException`, nothing written: resolve the duplicates first |
| a child whose own full request would be invalid (an item or a relationship key of the child missing, a malformed nested payload) | the child's `InvalidRequestException`, before anything is written |
| `null` | no document (an empty `HasOne`) |

Children that the parent holds and the payload leaves out are deleted (`HasMany`); a `HasOne` document without `id` replaces the current child. A partial request (`request_type => partial`) without the relationship key leaves the children untouched; a full request must carry every relationship key (#55).

Editability is the child's own contract: an item that is not editable on update is ignored by the child's `updateWithSync()`, as for any other model.

## Validation before writing

For top-level relationships, the whole payload of every referenced relationship is validated **before the parent or any child is written**: ownership, ids, duplicates, writable fields, cardinality and each child's own full-request contract (its items and relationship keys, nested referenced relationships included) are checked with reads only. When the relationships are written, the plan is rebuilt from fresh reads, so a validation result is never replayed on changed data.

## Concurrency: ownership is enforced when writing

A child can change owner between the plan and the write (another request re-parents it). Every update and delete of a child therefore:
1. checks atomically, right before writing, that the child still belongs to the relation;
2. carries the relation's conditions (foreign key, local key, extra wheres) in the filter of the update or delete itself (`MDModel::withWriteConstraint()`), so a change of owner in between makes the write match no document.

| Race | Result |
| --- | --- |
| a child to **update** changed owner | `ReferencedConflictException`; that child is not written. Children written before it in the same request stay written (see below). |
| a child to **delete** (left out of the payload) changed owner | skipped: it is no longer this parent's child; no `deleted` event fires |
| `destroyWithSync()` with `onParentDelete = delete` | deletes the children owned when it runs, read again through the relation (never a cached relation) |

Not covered: two requests updating the **same** child concurrently (the last write wins), and the narrow window between step 1 and step 2, where Eloquent's `updated`/`deleted` events can fire for a write that matched nothing. Creating children is not idempotent: a retried request whose documents have no `id` creates them again; send the ids returned by the first request when retrying.

## Failures and transactions

The preflight covers deterministic validation errors; it is **not a transaction**: a database error (or a conflict above) while children are written leaves the writes already done in place. Wrap the call in `DB::connection('mongodb')->transaction(...)` on a replica set if you need atomicity: the parent and every child are then rolled back together (tested). The package does not open a transaction itself.

## Lifecycle on `destroyWithSync()`

| `onParentDelete` | `destroyWithSync()` | `destroyWithSync(false)` |
| --- | --- | --- |
| not set (4.0 pre-release behaviour) | children deleted only when the relationship has a target, otherwise retained | retained |
| `'retain'` | retained | retained |
| `'delete'` | deleted (only the parent's own children) | retained |

Any other value throws `InvalidConfigurationException` before anything is deleted. There is no implicit cascade: set `'delete'` explicitly.

## Supported subset

Children are matched through the declared relation (all its conditions) with the child model's global scopes (a soft-deleted child is absent), on the parent's connection: a child model declared on the same connection as the parent model follows a parent stored on another connection or tenant database, see [tenancy.md](tenancy.md).

Only `HasOne` and `HasMany` written through `storeWithSync()` / `updateWithSync()` with a foreign key on the child. `BelongsTo`, `BelongsToMany` and morph relations are not supported. A referenced relationship declared on an embedded mini model is checked by the same rules when it is written, but not in the top-level preflight. Children are written one by one through their own `storeWithSync()` / `updateWithSync()`, so their Eloquent events fire.
