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

The child model must extend `MDModel`, the relation method must be a `hasOne` / `hasMany` of the configured model on the configured `foreignKey`, otherwise `InvalidConfigurationException` is thrown.

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
| `null` | no document (an empty `HasOne`) |

Children that the parent holds and the payload leaves out are deleted (`HasMany`); a `HasOne` document without `id` replaces the current child. A partial request (`request_type => partial`) without the relationship key leaves the children untouched; a full request must carry every relationship key (#55).

Editability is the child's own contract: an item that is not editable on update is ignored by the child's `updateWithSync()`, as for any other model.

## Validation before writing

For top-level relationships, the whole payload of every referenced relationship is validated **before the parent or any child is written**: ownership, ids, duplicates, writable fields and cardinality are checked with reads only. When the relationships are written, the plan is rebuilt from fresh reads, so a validation result is never replayed on changed data.

This is a preflight for deterministic validation errors, **not a transaction**: a database error while children are written (for example a failed child `updateWithSync()`) leaves the writes already done in place. Wrap the call in `DB::transaction()` on a replica set if you need atomicity; the package does not open one.

## Lifecycle on `destroyWithSync()`

| `onParentDelete` | `destroyWithSync()` | `destroyWithSync(false)` |
| --- | --- | --- |
| not set (4.0 pre-release behaviour) | children deleted only when the relationship has a target, otherwise retained | retained |
| `'retain'` | retained | retained |
| `'delete'` | deleted (only the parent's own children) | retained |

Any other value throws `InvalidConfigurationException` before anything is deleted. There is no implicit cascade: set `'delete'` explicitly.

## Supported subset

Children are matched by the foreign key on the parent's connection (a child model declared on the same connection as the parent model follows a parent stored on another connection or tenant database, see [tenancy.md](tenancy.md)), with the child model's global scopes; extra constraints written inside the relation method are not applied.

Only `HasOne` and `HasMany` written through `storeWithSync()` / `updateWithSync()` with a foreign key on the child. `BelongsTo`, `BelongsToMany` and morph relations are not supported. A referenced relationship declared on an embedded mini model is checked by the same rules when it is written, but not in the preflight (the preflight covers the top-level relationships only). Children are written one by one through their own `storeWithSync()` / `updateWithSync()`, so their Eloquent events fire.
