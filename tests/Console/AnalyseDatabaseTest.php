<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

use function Termwind\renderUsing;

beforeEach(function () {
    // the command renders through Termwind: capture it instead of writing to stdout
    $this->rendered = new BufferedOutput;
    renderUsing($this->rendered);
});

afterEach(function () {
    renderUsing(null);
});

/**
 * A Navigation that embeds a SubItem which does not embed the Navigation back.
 *
 * @return array{0: Navigation, 1: SubItem}
 */
function inconsistentNavigation(): array
{
    $subItem = test()->createSubItems();
    // plain save(): no sync, so the SubItem never receives the Navigation
    $navigation = new Navigation;
    $navigation->text = 'inconsistent';
    $navigation->sub_items = [['ref_id' => $subItem->id, 'text' => ['en' => 'x'], 'code' => 'c', 'href' => 'h']];
    $navigation->save();

    return [$navigation, $subItem];
}

test('reports a target without back reference', function () {
    [$navigation, $subItem] = inconsistentNavigation();

    expect(Artisan::call('mongo-sync:analyse'))->toBe(0);
    expect($this->rendered->fetch())->toContain(
        "Inconsistency: Tests\\Models\\Navigation {$navigation->id} links to Tests\\Models\\SubItem {$subItem->id}, but target missing back reference."
    );
});

test('the fix option dispatches a sync job for the inconsistent source', function () {
    [$navigation, $subItem] = inconsistentNavigation();

    Queue::fake();
    Artisan::call('mongo-sync:analyse', ['--fix' => true]);

    Queue::assertPushed(SyncTargetJob::class, fn (SyncTargetJob $job) => $job->sourceClass === Navigation::class
        && $job->sourceId === (string) $navigation->id
        && in_array((string) $subItem->id, $job->currentTargetIds, true));
});

test('references written through the sync are found on both sides', function () {
    $navigation = $this->createNavigation();
    $subItem = $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);

    Artisan::call('mongo-sync:analyse');

    expect($this->rendered->fetch())
        ->toContain('Analysing Tests\Models\Navigation...')
        ->toContain('Analysing Tests\Models\SubItem...')
        ->not->toContain((string) $subItem->id)
        ->not->toContain((string) $navigation->id);
});
