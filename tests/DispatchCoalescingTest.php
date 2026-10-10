<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

beforeEach(function () {
    config()->set('laravel-mongo-auto-sync.use_background_sync', true);
    config()->set('queue.default', 'mongodb');
    DB::table('jobs')->truncate();
});

/**
 * @return list<SyncTargetJob>
 */
function coalescedJobs(): array
{
    return DB::table('jobs')->get()
        ->map(fn ($row) => unserialize(json_decode($row->payload, true)['data']['command']))
        ->values()->all();
}

function coalescedMini(string $navigationId): string
{
    $navigation = Navigation::find($navigationId);

    // the multilingual title is sent as the value of the current locale, as clients do
    return json_encode([['ref_id' => $navigation->id, 'text' => $navigation->text, 'code' => $navigation->code, 'title' => getTranslatedContent($navigation->title)]]);
}

/**
 * @return list<string>
 */
function coalescedEntries(string $navigationId, string $subItemId): array
{
    return collect(Navigation::find($navigationId)?->sub_items ?? [])
        ->filter(fn ($mini) => SyncHelper::sameId($mini->ref_id, $subItemId))
        ->map(fn ($mini) => getTranslatedContent($mini->text))
        ->values()->all();
}

function coalescedWorker(): void
{
    Artisan::call('queue:work', ['connection' => 'mongodb', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1]);
}

function coalescedSubItem(string $navigationJson, string $text = 'coalesced'): SubItem
{
    return (new SubItem)->storeWithSync(new Request, ['text' => $text, 'code' => 'C', 'href' => 'h', 'navigation' => $navigationJson]);
}

it('dispatches one job per relation for each storeWithSync and updateWithSync operation', function () {
    $a = $this->createNavigation();
    $b = $this->createNavigation();
    DB::table('jobs')->truncate();

    $subItem = coalescedSubItem(coalescedMini($a->id));
    expect(DB::table('jobs')->count())->toBe(1);

    DB::table('jobs')->truncate();
    $subItem = $subItem->updateWithSync(new Request, ['text' => 'changed', 'code' => 'C', 'href' => 'h', 'navigation' => coalescedMini($a->id)]);
    expect(DB::table('jobs')->count())->toBe(1);

    DB::table('jobs')->truncate();
    $subItem->updateWithSync(new Request, ['text' => 'moved', 'code' => 'C', 'href' => 'h', 'navigation' => coalescedMini($b->id)]);
    $jobs = coalescedJobs();
    expect($jobs)->toHaveCount(1)
        ->and($jobs[0]->currentTargetIds)->toBe([(string) $b->id])
        ->and($jobs[0]->removedTargetIds)->toBe([(string) $a->id]);

    coalescedWorker();
    expect(coalescedEntries($a->id, $subItem->id))->toBe([])
        ->and(coalescedEntries($b->id, $subItem->id))->toBe(['moved']);
});

it('dispatches nothing for an operation that changes nothing', function () {
    $a = $this->createNavigation();
    $subItem = coalescedSubItem(coalescedMini($a->id));
    coalescedWorker();
    $subItem = SubItem::find($subItem->id);

    $subItem->updateWithSync(new Request, ['text' => 'coalesced', 'code' => 'C', 'href' => 'h', 'navigation' => coalescedMini($a->id)]);

    expect(DB::table('jobs')->count())->toBe(0);
});

it('keeps every destination to clean when a scope moves A -> B -> C, and the worker converges', function () {
    [$a, $b, $c] = [$this->createNavigation(), $this->createNavigation(), $this->createNavigation()];
    $subItem = coalescedSubItem(coalescedMini($a->id));
    coalescedWorker();
    $subItem = SubItem::find($subItem->id);

    $subItem->withCoalescedTargetSync(function (SubItem $model) use ($b, $c) {
        $model->navigation = json_decode(coalescedMini($b->id), true)[0];
        $model->save();
        // a nested scope does not flush on its own
        $model->withCoalescedTargetSync(function (SubItem $model) use ($c) {
            $model->navigation = json_decode(coalescedMini($c->id), true)[0];
            $model->save();
        });
        expect(DB::table('jobs')->count())->toBe(0);
    });

    $jobs = coalescedJobs();
    expect($jobs)->toHaveCount(1)
        ->and($jobs[0]->currentTargetIds)->toBe([(string) $c->id])
        ->and($jobs[0]->removedTargetIds)->toEqualCanonicalizing([(string) $a->id, (string) $b->id]);

    coalescedWorker();
    expect(coalescedEntries($a->id, $subItem->id))->toBe([])
        ->and(coalescedEntries($b->id, $subItem->id))->toBe([])
        ->and(coalescedEntries($c->id, $subItem->id))->toBe(['coalesced']);
});

it('flushes the work already persisted when the operation throws, then rethrows', function () {
    [$a, $b] = [$this->createNavigation(), $this->createNavigation()];
    $subItem = SubItem::find(coalescedSubItem(coalescedMini($a->id))->id);
    coalescedWorker();

    expect(fn () => $subItem->withCoalescedTargetSync(function (SubItem $model) use ($b) {
        $model->navigation = json_decode(coalescedMini($b->id), true)[0];
        $model->save();
        throw new RuntimeException('after a partial write');
    }))->toThrow(RuntimeException::class, 'after a partial write');

    expect(DB::table('jobs')->count())->toBe(1);
    coalescedWorker();
    expect(coalescedEntries($a->id, $subItem->id))->toBe([])
        ->and(coalescedEntries($b->id, $subItem->id))->toBe(['coalesced']);
});

it('keeps sources apart and leaves opted-out saves out of the coalesced dispatch', function () {
    $a = $this->createNavigation();
    $first = SubItem::find(coalescedSubItem(coalescedMini($a->id), 'first')->id);
    $second = SubItem::find(coalescedSubItem(coalescedMini($a->id), 'second')->id);
    coalescedWorker();

    $first->withCoalescedTargetSync(function (SubItem $model) use ($second) {
        $model->text = ['en' => 'first changed'];
        $model->save();
        $second->text = ['en' => 'second changed'];
        $second->save();
    });
    // one job per source
    expect(collect(coalescedJobs())->map->sourceId->sort()->values()->all())->toBe(collect([(string) $first->id, (string) $second->id])->sort()->values()->all());

    DB::table('jobs')->truncate();
    $first->withCoalescedTargetSync(fn (SubItem $model) => $model->withoutTargetSync(function (SubItem $model) {
        $model->text = ['en' => 'opted out'];
        $model->save();
    }));
    expect(DB::table('jobs')->count())->toBe(0);
});
