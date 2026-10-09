<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;
use Tests\Models\Navigation;

test('dispatches one job per document and relation with a target', function () {
    Navigation::truncate();
    $this->createNavigation();
    $this->createNavigation();

    Queue::fake();
    expect(Artisan::call('mongo-sync:sync-collection', ['model' => Navigation::class]))->toBe(0);

    // Navigation has a single relation with a target (sub_items)
    Queue::assertPushed(SyncTargetJob::class, 2);
    Queue::assertPushed(SyncTargetJob::class, fn (SyncTargetJob $job) => $job->sourceClass === Navigation::class && $job->method === 'sub_items');
});

test('fails on an unknown model', function () {
    Queue::fake();

    expect(Artisan::call('mongo-sync:sync-collection', ['model' => 'Tests\Models\DoesNotExist']))->not->toBe(0);
    expect(Artisan::output())->toContain('Class Tests\Models\DoesNotExist does not exist.');
    Queue::assertNothingPushed();
});
