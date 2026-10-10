<?php

use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;
use Tests\Models\MiniSubItem;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

function traitsMiniSubItem(string $refId, string $code): MiniSubItem
{
    $mini = new MiniSubItem;
    $mini->ref_id = $refId;
    $mini->text = ['en' => $code];
    $mini->code = $code;
    $mini->href = 'https://example.com/'.$code;

    return $mini;
}

/**
 * @return list<string>
 */
function traitsNavigationSubItemRefIds(string $navigationId): array
{
    return collect(Navigation::find($navigationId)->sub_items ?? [])
        ->map(fn ($mini) => (string) $mini->ref_id)
        ->values()
        ->all();
}

test('updating the sub items of a navigation replaces the list and links the new sub item', function () {
    $navigation = $this->createNavigation();
    $removed = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);
    $added = $this->createSubItems();
    expect(traitsNavigationSubItemRefIds($navigation->id))->toBe([(string) $removed->id]);

    $navigation = Navigation::find($navigation->id);
    $navigation->updateWithSync(new Request, [
        'text' => $navigation->text,
        'code' => $navigation->code,
        'href' => $navigation->href,
        'date' => $navigation->date,
        'target' => $navigation->target,
        'title' => getTranslatedContent($navigation->title),
        'sub_items' => json_encode([[
            'ref_id' => $added->id,
            'text' => getTranslatedContent($added->text),
            'code' => $added->code,
            'href' => $added->href,
        ]]),
    ]);

    expect(traitsNavigationSubItemRefIds($navigation->id))->toBe([(string) $added->id]);
    expect((string) SubItem::find($added->id)->navigation->ref_id)->toBe((string) $navigation->id);
    // the synchronous path only cleans EmbedsMany targets: the EmbedsOne of the removed sub item is left as is
    expect((string) SubItem::find($removed->id)->navigation->ref_id)->toBe((string) $navigation->id);
});

test('destroyWithSync of a navigation leaves the EmbedsOne of its sub items as is on the synchronous path', function () {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);

    Navigation::find($navigation->id)->destroyWithSync();

    expect(Navigation::find($navigation->id))->toBeNull();
    expect((string) SubItem::find($subItem->id)->navigation->ref_id)->toBe((string) $navigation->id);
});

test('handleSubTarget removes the mini model of the source from an EmbedsMany target', function () {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);
    $other = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);
    expect(traitsNavigationSubItemRefIds($navigation->id))->toBe([(string) $subItem->id, (string) $other->id]);

    $subItem->handleSubTarget($navigation->id, Navigation::class, 'sub_items', false, true);

    expect(traitsNavigationSubItemRefIds($navigation->id))->toBe([(string) $other->id]);
});

test('storing a source whose EmbedsOne points to a missing target does not create the target', function () {
    $ghostId = (string) new ObjectId;

    $subItem = $this->createSubItems(['navigation' => json_encode([[
        'ref_id' => $ghostId,
        'text' => 'ghost',
        'code' => 'ghost',
        'title' => 'ghost',
    ]])]);

    expect((string) SubItem::find($subItem->id)->navigation->ref_id)->toBe($ghostId);
    expect(Navigation::find($ghostId))->toBeNull();
});

test('removeRelationWithSync clears an EmbedsOne target only while it points at the source', function () {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);

    $target = SubItem::find($subItem->id);
    $target->removeRelationWithSync('navigation', (string) new ObjectId, true, false);
    expect((string) SubItem::find($subItem->id)->navigation->ref_id)->toBe((string) $navigation->id);

    $target->removeRelationWithSync('navigation', $navigation->id, true, false);
    expect(SubItem::find($subItem->id)->navigation)->toBeNull();
    expect($target->navigation)->toBeNull();
});

test('removeRelationWithSync ignores a missing source id and a relation that is not embedded', function () {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);
    $target = SubItem::find($subItem->id);

    $target->removeRelationWithSync('navigation', null, true, false);
    $target->removeRelationWithSync('navigation', $navigation->id, false, false);

    expect((string) SubItem::find($subItem->id)->navigation->ref_id)->toBe((string) $navigation->id);
});

test('updateRelationWithSync does not resurrect a target deleted meanwhile', function () {
    $navigation = $this->createNavigation();
    $stale = Navigation::find($navigation->id);
    Navigation::find($navigation->id)->delete();

    $stale->updateRelationWithSync(traitsMiniSubItem((string) new ObjectId, 'late'), 'sub_items', false, true);

    expect(Navigation::find($navigation->id))->toBeNull();
    expect($stale->sub_items)->toHaveCount(0);
});

test('target sync refuses a non MongoDB connection', function () {
    config()->set('database.connections.traits_sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $navigation = new Navigation;
    $navigation->setConnection('traits_sqlite');

    expect(fn () => $navigation->updateRelationWithSync(traitsMiniSubItem((string) new ObjectId, 'x'), 'sub_items', false, true))
        ->toThrow(LogicException::class, 'Target sync requires a MongoDB connection');
});

test('a stale removal job keeps the source on a target it still references', function () {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);
    $relation = (new SubItem)->getMongoRelation()['navigation'];

    (new SyncTargetJob(SubItem::class, (string) $subItem->id, 'navigation', $relation, [], [(string) $navigation->id]))->handle();

    $entries = collect(Navigation::find($navigation->id)->sub_items)->filter(fn ($mini) => SyncHelper::sameId($mini->ref_id, $subItem->id));
    expect($entries)->toHaveCount(1);
});
