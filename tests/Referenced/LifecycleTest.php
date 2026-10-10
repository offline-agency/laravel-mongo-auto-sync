<?php

use Illuminate\Http\Request;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use Tests\Models\RefAuthor;
use Tests\Models\RefBook;
use Tests\Models\RefProfile;

/**
 * A RefAuthor (relationships without target) whose referenced relationships declare $policy as onParentDelete.
 */
function lifecycleAuthor(mixed $policy): RefAuthor
{
    $author = new class extends RefAuthor
    {
        public static mixed $policy = null;

        public function getMongoRelation(): array
        {
            $relations = parent::getMongoRelation();
            foreach ($relations as $method => $relation) {
                if (self::$policy !== null) {
                    $relations[$method]['onParentDelete'] = self::$policy;
                }
            }

            return $relations;
        }
    };
    $author::$policy = $policy;

    return $author->storeWithSync(new Request, [
        'name' => 'lifecycle '.uniqid(),
        'books' => json_encode([['title' => 'L1'], ['title' => 'L2']]),
        'profile' => json_encode([['bio' => 'L bio']]),
    ]);
}

/**
 * @return array{books: int, profile: int}
 */
function lifecycleChildren(RefAuthor $author): array
{
    return [
        'books' => RefBook::where('ref_author_id', $author->id)->count(),
        'profile' => RefProfile::where('ref_author_id', $author->id)->count(),
    ];
}

it('applies the onParentDelete policy of referenced relationships on destroyWithSync()', function (mixed $policy, bool $syncTargets, array $expected) {
    // the policy is static on the fixture class: the other author is created first
    $other = lifecycleAuthor(null);
    $author = lifecycleAuthor($policy);

    $author->destroyWithSync($syncTargets);

    expect(RefAuthor::find($author->id))->toBeNull()
        ->and(lifecycleChildren($author))->toBe($expected)
        ->and(lifecycleChildren($other))->toBe(['books' => 2, 'profile' => 1]);
})->with([
    'no policy, no target: children retained (4.0 pre-release behaviour)' => [null, true, ['books' => 2, 'profile' => 1]],
    'delete' => ['delete', true, ['books' => 0, 'profile' => 0]],
    'retain' => ['retain', true, ['books' => 2, 'profile' => 1]],
    'delete, but destroyWithSync(false)' => ['delete', false, ['books' => 2, 'profile' => 1]],
]);

it('rejects an unknown onParentDelete policy before deleting anything', function () {
    $author = lifecycleAuthor(null);
    $author::$policy = 'cascade';

    expect(fn () => $author->destroyWithSync())
        ->toThrow(InvalidConfigurationException::class, "Invalid onParentDelete policy \"cascade\": expected 'delete' or 'retain'.")
        ->and(RefAuthor::find($author->id))->not->toBeNull()
        ->and(lifecycleChildren($author))->toBe(['books' => 2, 'profile' => 1]);
});
