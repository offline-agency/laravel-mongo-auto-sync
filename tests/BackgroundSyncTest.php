<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use MongoDB\BSON\ObjectId;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

beforeEach(function () {
    config()->set('laravel-mongo-auto-sync.use_background_sync', true);
    DB::table('jobs')->truncate();
});

/**
 * Entries of $navigation->sub_items pointing to $subItemId.
 *
 * @return list<object>
 */
function subItemEntries(string $navigationId, string $subItemId): array
{
    $navigation = Navigation::find($navigationId);

    return collect($navigation->sub_items ?? [])
        ->filter(fn ($mini) => SyncHelper::sameId($mini->ref_id, $subItemId))
        ->values()
        ->all();
}

function updateSubItem(SubItem $subItem, array $data): SubItem
{
    return $subItem->updateWithSync(new Request, array_merge([
        'text' => getTranslatedContent($subItem->text),
        'code' => $subItem->code,
        'href' => $subItem->href,
    ], $data));
}

test('dispatches exactly one job per relation with plain ids', function () {
    $navigation = $this->createNavigation();
    $mini = $this->getMiniNavigation($navigation->id);

    Queue::fake();
    $this->createSubItems(['navigation' => $mini]);

    Queue::assertPushed(SyncTargetJob::class, 1);
    Queue::assertPushed(SyncTargetJob::class, function (SyncTargetJob $job) use ($navigation) {
        return $job->sourceClass === SubItem::class
            && $job->currentTargetIds === [(string) $navigation->id]
            && $job->removedTargetIds === [];
    });
});

test('job payload contains no model instances', function () {
    $navigation = $this->createNavigation();
    $mini = $this->getMiniNavigation($navigation->id);

    Queue::fake();
    $subItem = $this->createSubItems(['text' => 'serialised', 'navigation' => $mini]);

    $job = Queue::pushed(SyncTargetJob::class)->first();
    expect($job)->not->toBeNull();
    expect(serialize($job))->not->toContain('ModelIdentifier');

    unserialize(serialize($job))->handle();

    $entries = subItemEntries($navigation->id, $subItem->id);
    expect($entries)->toHaveCount(1);
    expect(getTranslatedContent($entries[0]->text))->toEqual('serialised');
});

test('store syncs the target through the sync driver', function () {
    $navigation = $this->createNavigation();
    $mini = $this->getMiniNavigation($navigation->id);

    $subItem = $this->createSubItems([
        'text' => 'example sub item test',
        'code' => 'HFGRT12345',
        'href' => 'https://google.com',
        'navigation' => $mini,
    ]);

    $navigation = Navigation::find($navigation->id);
    $subItemMini = $navigation->sub_items[0];

    expect($navigation->sub_items)->not->toBeEmpty();
    expect($subItemMini->ref_id)->toEqual($subItem->id);
    expect(getTranslatedContent($subItemMini->text))->toEqual(getTranslatedContent($subItem->text));
    expect($subItemMini->code)->toEqual($subItem->code);
    expect($subItemMini->href)->toEqual($subItem->href);
});

test('update replaces the mini model instead of duplicating it', function () {
    $navigation = $this->createNavigation();
    $mini = $this->getMiniNavigation($navigation->id);
    $subItem = $this->createSubItems(['text' => 'before', 'navigation' => $mini]);

    updateSubItem($subItem, ['text' => 'after', 'navigation' => $mini]);

    $entries = subItemEntries($navigation->id, $subItem->id);
    expect($entries)->toHaveCount(1);
    expect(getTranslatedContent($entries[0]->text))->toEqual('after');
});

test('moving the relation cleans the old target and fills the new one', function () {
    $oldNavigation = $this->createNavigation();
    $newNavigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($oldNavigation->id)]);

    updateSubItem($subItem, ['navigation' => $this->getMiniNavigation($newNavigation->id)]);

    expect(subItemEntries($oldNavigation->id, $subItem->id))->toHaveCount(0);
    expect(subItemEntries($newNavigation->id, $subItem->id))->toHaveCount(1);
});

test('destroyWithSync removes the mini model from the target', function () {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);
    expect(subItemEntries($navigation->id, $subItem->id))->toHaveCount(1);

    $subItem->destroyWithSync();

    expect(subItemEntries($navigation->id, $subItem->id))->toHaveCount(0);
});

test('saving an unchanged model dispatches nothing', function () {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);

    Queue::fake();
    SubItem::find($subItem->id)->save();

    Queue::assertNothingPushed();
});

test('works end to end on the mongodb queue driver', function () {
    config()->set('queue.default', 'mongodb');

    $oldNavigation = $this->createNavigation();
    $newNavigation = $this->createNavigation();
    $subItem = $this->createSubItems(['text' => 'queued', 'navigation' => $this->getMiniNavigation($oldNavigation->id)]);
    $subItem = updateSubItem($subItem, ['text' => 'queued and updated', 'navigation' => $this->getMiniNavigation($oldNavigation->id)]);
    updateSubItem($subItem, ['navigation' => $this->getMiniNavigation($newNavigation->id)]);

    expect(DB::table('jobs')->count())->toBeGreaterThan(0);

    Artisan::call('queue:work', ['connection' => 'mongodb', '--stop-when-empty' => true]);

    expect(subItemEntries($oldNavigation->id, $subItem->id))->toHaveCount(0);
    $entries = subItemEntries($newNavigation->id, $subItem->id);
    expect($entries)->toHaveCount(1);
    expect(getTranslatedContent($entries[0]->text))->toEqual('queued and updated');
    expect(DB::table('jobs')->count())->toEqual(0);
});

test('honours queue_connection and queue_name', function () {
    config()->set('laravel-mongo-auto-sync.queue_connection', 'mongodb');
    config()->set('laravel-mongo-auto-sync.queue_name', 'mongo_auto_sync');
    $navigation = $this->createNavigation();

    Queue::fake();
    $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);

    Queue::assertPushedOn('mongo_auto_sync', SyncTargetJob::class);
    Queue::assertPushed(SyncTargetJob::class, fn (SyncTargetJob $job) => $job->connection === 'mongodb');
});

test('target writes made by the job do not dispatch new jobs', function () {
    $navigation = $this->createNavigation();
    $mini = $this->getMiniNavigation($navigation->id);

    Queue::fake();
    $this->createSubItems(['navigation' => $mini]);
    $job = Queue::pushed(SyncTargetJob::class)->first();

    Queue::fake();
    $job->handle();

    expect(Navigation::find($navigation->id)->sub_items)->toHaveCount(1);
    Queue::assertNothingPushed();
});

test('a stale job does not overwrite newer values on the target', function () {
    $navigation = $this->createNavigation();
    $mini = $this->getMiniNavigation($navigation->id);
    $subItem = $this->createSubItems(['text' => 'v1', 'navigation' => $mini]);

    Queue::fake();
    $subItem = updateSubItem($subItem, ['text' => 'v2', 'navigation' => $mini]);
    updateSubItem($subItem, ['text' => 'v3', 'navigation' => $mini]);

    // workers run the jobs in reverse order
    foreach (Queue::pushed(SyncTargetJob::class)->values()->reverse() as $job) {
        unserialize(serialize($job))->handle();
    }

    $entries = subItemEntries($navigation->id, $subItem->id);
    expect($entries)->toHaveCount(1);
    expect(getTranslatedContent($entries[0]->text))->toEqual('v3');
});

test('the job keeps the locale of the stored source', function () {
    $navigation = $this->createNavigation();
    $mini = $this->getMiniNavigation($navigation->id);

    app()->setLocale('it');
    Queue::fake();
    $subItem = $this->createSubItems(['text' => 'ciao', 'navigation' => $mini]);
    $job = Queue::pushed(SyncTargetJob::class)->first();

    app()->setLocale('en');
    unserialize(serialize($job))->handle();

    expect(subItemEntries($navigation->id, $subItem->id)[0]->text)->toEqual(['it' => 'ciao']);
});

test('the job payload does not carry the request input', function () {
    $navigation = $this->createNavigation();

    Queue::fake();
    (new SubItem)->storeWithSync(new Request(['password' => 'hunter2']), [
        'text' => 'text',
        'code' => 'code',
        'href' => 'href',
        'navigation' => $this->getMiniNavigation($navigation->id),
    ]);

    $job = Queue::pushed(SyncTargetJob::class)->first();
    expect($job->requestData)->toBe([]);
    expect(serialize($job))->not->toContain('hunter2');
});

test('destroyWithSync(false) does not touch the targets', function () {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);

    Queue::fake();
    $subItem->destroyWithSync(false);

    Queue::assertNothingPushed();
});

test('ref_id comparison ignores ObjectId vs string', function () {
    $id = (string) new ObjectId;

    expect(SyncHelper::sameId(new ObjectId($id), $id))->toBeTrue();
    expect(SyncHelper::sameId($id, new ObjectId($id)))->toBeTrue();
    expect(SyncHelper::sameId(new ObjectId, $id))->toBeFalse();
    expect(SyncHelper::sameId(null, $id))->toBeFalse();
});
