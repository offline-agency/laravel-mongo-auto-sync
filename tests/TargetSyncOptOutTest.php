<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

beforeEach(function () {
    config()->set('queue.default', 'mongodb');
    DB::table('jobs')->truncate();
});

/**
 * The raw navigation document (values and timestamps), to prove a target was not written.
 *
 * @return array<string, mixed>
 */
function optOutRawNavigation(string $id): array
{
    $document = DB::connection('mongodb')->getCollection('navigation')
        ->findOne(['_id' => new ObjectId($id)], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);

    return is_array($document) ? $document : [];
}

/**
 * @param  array<string, mixed>  $options
 */
function optOutStoreSubItem(string $navigationJson, array $options = [], string $text = 'opt-out'): SubItem
{
    return (new SubItem)->storeWithSync(new Request, ['text' => $text, 'code' => 'C1', 'href' => 'https://example.com', 'navigation' => $navigationJson], $options);
}

/**
 * @param  array<string, mixed>  $options
 */
function optOutUpdateSubItem(SubItem $subItem, string $navigationJson, string $text, array $options = []): SubItem
{
    return $subItem->updateWithSync(new Request, ['text' => $text, 'code' => 'C1', 'href' => 'https://example.com', 'navigation' => $navigationJson], $options);
}

function optOutRunWorker(): void
{
    Artisan::call('queue:work', ['connection' => 'mongodb', '--stop-when-empty' => true, '--sleep' => 0]);
}

/**
 * @return list<string>
 */
function optOutEntryTexts(string $navigationId, string $subItemId): array
{
    return collect(Navigation::find($navigationId)?->sub_items ?? [])
        ->filter(fn ($mini) => SyncHelper::sameId($mini->ref_id, $subItemId))
        ->map(fn ($mini) => getTranslatedContent($mini->text))
        ->values()->all();
}

it('stores and updates the source without touching or scheduling the targets', function (bool $background) {
    config()->set('laravel-mongo-auto-sync.use_background_sync', $background);
    $navigation = $this->createNavigation();
    $before = optOutRawNavigation($navigation->id);

    $subItem = optOutStoreSubItem($this->getMiniNavigation($navigation->id), ['sync_targets' => false]);
    $subItem = optOutUpdateSubItem($subItem, $this->getMiniNavigation($navigation->id), 'changed', ['sync_targets' => false]);

    expect(DB::table('jobs')->count())->toBe(0);
    optOutRunWorker();

    // the source and its own embedded relationship are written
    $stored = SubItem::find($subItem->id);
    expect(getTranslatedContent($stored->text))->toBe('changed')
        ->and(SyncHelper::sameId($stored->navigation->ref_id, $navigation->id))->toBeTrue()
        // the target document is unchanged, timestamps included
        ->and(optOutRawNavigation($navigation->id))->toEqual($before);
})->with(['synchronous' => [false], 'background, real mongodb queue' => [true]]);

it('keeps the #55 validation when target sync is disabled', function () {
    $navigation = $this->createNavigation();

    expect(fn () => (new SubItem)->storeWithSync(new Request, ['text' => 't', 'code' => 'c', 'href' => 'h'], ['sync_targets' => false]))
        ->toThrow(InvalidRequestException::class, "Missing relationship 'navigation' in the request.");
});

it('rejects a sync_targets option that is not a boolean', function () {
    expect(fn () => optOutStoreSubItem(json_encode([]), ['sync_targets' => 'no']))
        ->toThrow(InvalidConfigurationException::class, 'The sync_targets option must be a boolean.');
});

it('still fires the Eloquent and package events of the source', function () {
    $events = [];
    SubItem::saved(function () use (&$events) {
        $events[] = 'saved';
    });
    SubItem::registerModelEvent('storeWithSync', function () use (&$events) {
        $events[] = 'storeWithSync';
    });

    optOutStoreSubItem($this->getMiniNavigation($this->createNavigation()->id), ['sync_targets' => false]);

    expect($events)->toContain('saved', 'storeWithSync');
});

it('scopes a plain save to the instance, restores it after an exception and lets the next save sync', function () {
    config()->set('laravel-mongo-auto-sync.use_background_sync', true);
    $navigation = $this->createNavigation();
    $subItem = optOutStoreSubItem($this->getMiniNavigation($navigation->id), [], 'synced');
    optOutRunWorker();
    $before = optOutRawNavigation($navigation->id);
    $other = optOutStoreSubItem($this->getMiniNavigation($navigation->id), [], 'other');
    DB::table('jobs')->truncate();

    $subItem->withoutTargetSync(function (SubItem $model) use ($other) {
        $model->text = ['en' => 'not synced'];
        $model->save();
        // nested scope, and another model saved inside the scope keeps syncing
        $model->withoutTargetSync(fn () => $model->save());
        $other->text = ['en' => 'other changed'];
        $other->save();
    });
    expect(DB::table('jobs')->count())->toBe(1);
    optOutRunWorker();
    expect(optOutEntryTexts($navigation->id, $subItem->id))->toBe(['synced'])
        ->and(optOutEntryTexts($navigation->id, $other->id))->toBe(['other changed']);

    expect(fn () => $subItem->withoutTargetSync(fn () => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class, 'boom')
        ->and($subItem->targetSyncEnabled())->toBeTrue();

    $subItem->text = ['en' => 'synced again'];
    $subItem->save();
    optOutRunWorker();
    expect(optOutEntryTexts($navigation->id, $subItem->id))->toBe(['synced again']);
});

it('does not cancel a job queued by an earlier operation', function () {
    config()->set('laravel-mongo-auto-sync.use_background_sync', true);
    $navigation = $this->createNavigation();
    $subItem = optOutStoreSubItem($this->getMiniNavigation($navigation->id), [], 'queued earlier');
    expect(DB::table('jobs')->count())->toBe(1);

    optOutUpdateSubItem($subItem, $this->getMiniNavigation($navigation->id), 'opted out', ['sync_targets' => false]);
    expect(DB::table('jobs')->count())->toBe(1);
    optOutRunWorker();

    // documented boundary: the earlier job rebuilds the mini model from the stored source
    expect(optOutEntryTexts($navigation->id, $subItem->id))->toBe(['opted out']);
});

it('deletes with destroyWithSync(false) without touching the targets, in both modes', function (bool $background) {
    config()->set('laravel-mongo-auto-sync.use_background_sync', $background);
    $navigation = $this->createNavigation();
    $unrelated = $this->createNavigation();
    $subItem = optOutStoreSubItem($this->getMiniNavigation($navigation->id), [], 'kept on target');
    optOutRunWorker();
    $before = optOutRawNavigation($navigation->id);
    $unrelatedBefore = optOutRawNavigation($unrelated->id);
    $events = [];
    SubItem::deleted(function () use (&$events) {
        $events[] = 'deleted';
    });
    SubItem::registerModelEvent('destroyWithSync', function () use (&$events) {
        $events[] = 'destroyWithSync';
    });

    $subItem->destroyWithSync(false);

    expect(DB::table('jobs')->count())->toBe(0);
    optOutRunWorker();
    expect(SubItem::find($subItem->id))->toBeNull()
        ->and(optOutRawNavigation($navigation->id))->toEqual($before)
        ->and(optOutRawNavigation($unrelated->id))->toEqual($unrelatedBefore)
        ->and($events)->toBe(['deleted', 'destroyWithSync'])
        ->and($subItem->targetSyncEnabled())->toBeTrue();

    // a later enabled delete still cleans its target
    $next = optOutStoreSubItem($this->getMiniNavigation($navigation->id), [], 'removed');
    optOutRunWorker();
    $next->destroyWithSync();
    optOutRunWorker();
    expect(optOutEntryTexts($navigation->id, $next->id))->toBe([]);
})->with(['synchronous' => [false], 'background, real mongodb queue' => [true]]);
