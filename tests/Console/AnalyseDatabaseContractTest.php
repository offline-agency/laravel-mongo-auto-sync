<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use MongoDB\BSON\ObjectId;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;
use Tests\ConsoleFixtures\UnreadableDirectoryStream;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

beforeEach(function () {
    foreach (['navigation', 'sub_items', 'jobs', 'failed_jobs'] as $collection) {
        DB::table($collection)->truncate();
    }
    useFixtures('Healthy');
    config()->set('laravel-mongo-auto-sync.other_models', []);
});

function useFixtures(string $directory): void
{
    config()->set('laravel-mongo-auto-sync.model_path', __DIR__.'/../AnalyseFixtures/'.$directory);
    config()->set('laravel-mongo-auto-sync.model_namespace', 'Tests\AnalyseFixtures\\'.$directory);
}

/** @return array{0: int, 1: string} */
function analyse(array $options = []): array
{
    $code = Artisan::call('mongo-sync:analyse', $options);

    return [$code, Artisan::output()];
}

/** A navigation embedding $subItems whose SubItem documents do not embed it back (plain save, no sync). */
function navigationWithoutBackReferences(SubItem ...$subItems): Navigation
{
    $navigation = new Navigation;
    $navigation->text = 'one-sided';
    $navigation->sub_items = array_map(fn (SubItem $s) => ['ref_id' => (string) $s->id, 'code' => 'c'], $subItems);
    $navigation->save();

    return $navigation;
}

function storedDocuments(): string
{
    return json_encode([DB::table('navigation')->get()->all(), DB::table('sub_items')->get()->all()]);
}

test('an empty database is consistent', function () {
    [$code, $output] = analyse();

    expect($code)->toBe(0);
    expect($output)->toContain('Analysis finished: 0 anomalies, 0 errors, 0 repair jobs dispatched.');
});

test('discovery keeps sub-namespaces, skips abstract and foreign classes and deduplicates other_models', function () {
    config()->set('laravel-mongo-auto-sync.other_models', [
        'navigation' => ['model_path' => __DIR__.'/../AnalyseFixtures/Healthy', 'model_namespace' => 'Tests\AnalyseFixtures\Healthy'],
        'user' => ['model_path' => '/nowhere', 'model_namespace' => 'App'],
    ]);

    [$code, $output] = analyse();

    expect($code)->toBe(0);
    expect(substr_count($output, 'Analysing Tests\AnalyseFixtures\Healthy\Navigation...'))->toBe(1);
    expect($output)->toContain('Analysing Tests\AnalyseFixtures\Healthy\Items\SubItem...')
        ->toContain('Skipped other_models.user: class App\User does not exist.')
        ->not->toContain('AbstractBase')
        ->not->toContain('NotAModel');
});

test('a consistent database written through the sync is reported clean', function () {
    $navigation = $this->createNavigation();
    $this->createSubItems(['navigation' => $this->getMiniNavigation($navigation->id)]);

    expect(analyse()[0])->toBe(0);
});

test('ObjectId and string references are compared by value', function () {
    $subItem = $this->createSubItems();
    $navigation = navigationWithoutBackReferences($subItem);
    DB::connection('mongodb')->getCollection('navigation')->updateOne(['_id' => new ObjectId((string) $navigation->id)], ['$set' => ['sub_items.0.ref_id' => new ObjectId((string) $subItem->id)]]);
    DB::connection('mongodb')->getCollection('sub_items')->updateOne(['_id' => new ObjectId((string) $subItem->id)], ['$set' => ['navigation' => ['ref_id' => (string) $navigation->id]]]);

    expect(analyse()[0])->toBe(0);
});

test('a missing back reference fails the analysis without writing or dispatching anything', function () {
    $subItem = $this->createSubItems();
    $navigation = navigationWithoutBackReferences($subItem);
    $before = storedDocuments();
    Queue::fake();

    [$code, $output] = analyse();

    expect($code)->toBe(1);
    expect($output)->toContain("Inconsistency: Tests\\AnalyseFixtures\\Healthy\\Navigation {$navigation->id} links to Tests\\Models\\SubItem {$subItem->id}, but target missing back reference.");
    expect(storedDocuments())->toBe($before);
    Queue::assertNothingPushed();
});

test('--fix still fails and dispatches at most one job per source and relation, on the configured queue', function () {
    config()->set('laravel-mongo-auto-sync.queue_connection', 'mongodb');
    config()->set('laravel-mongo-auto-sync.queue_name', 'analyse_repairs');
    $first = $this->createSubItems();
    $second = $this->createSubItems();
    $navigation = navigationWithoutBackReferences($first, $second);
    Queue::fake();

    [$code, $output] = analyse(['--fix' => true]);

    expect($code)->toBe(1);
    expect($output)->toContain('1 repair jobs dispatched');
    Queue::assertPushed(SyncTargetJob::class, 1);
    Queue::assertPushedOn('analyse_repairs', SyncTargetJob::class, fn (SyncTargetJob $job) => $job->connection === 'mongodb'
        && $job->sourceId === (string) $navigation->id
        && $job->currentTargetIds === [(string) $first->id, (string) $second->id]);
});

test('a missing target fails the analysis and is never repaired', function () {
    $deleted = new SubItem;
    $deleted->id = '650000000000000000000099';
    $navigation = navigationWithoutBackReferences($deleted);
    Queue::fake();

    [$code, $output] = analyse(['--fix' => true]);

    expect($code)->toBe(1);
    expect($output)->toContain("Target not found: Tests\\Models\\SubItem ID 650000000000000000000099 referenced by Tests\\AnalyseFixtures\\Healthy\\Navigation ID {$navigation->id}");
    Queue::assertNothingPushed();
});

test('an invalid model directory is an error and the command can be reused afterwards', function () {
    config()->set('laravel-mongo-auto-sync.model_path', __DIR__.'/../AnalyseFixtures/does-not-exist');
    [$failed, $output] = analyse();

    useFixtures('Healthy');
    [$recovered] = analyse();

    expect($failed)->toBe(1);
    expect($output)->toContain('Invalid configuration: model_path');
    expect($recovered)->toBe(0);
});

test('relations with an unsupported target type and invalid target configuration are errors', function (string $directory, string $message) {
    useFixtures($directory);

    [$code, $output] = analyse();

    expect($code)->toBe(1);
    expect($output)->toContain($message);
})->with([
    'HasMany with target' => ['Unsupported', 'Unsupported target: Tests\AnalyseFixtures\Unsupported\ReferencedWithTarget::books has type HasMany'],
    'unknown target model' => ['BrokenConfig', 'Invalid configuration: Tests\AnalyseFixtures\BrokenConfig\MissingTargetModel::links needs modelTarget'],
]);

test('a repair job that cannot be dispatched is reported as an error', function () {
    config()->set('laravel-mongo-auto-sync.queue_connection', 'missing-connection');
    $subItem = $this->createSubItems();
    navigationWithoutBackReferences($subItem);

    [$code, $output] = analyse(['--fix' => true]);

    expect($code)->toBe(1);
    expect($output)->toContain('could not be dispatched')->toContain('0 repair jobs dispatched');
});

test('end to end: --fix fails, the worker repairs the database and a new scan is clean', function () {
    config()->set('queue.default', 'mongodb');
    config()->set('laravel-mongo-auto-sync.queue_name', 'analyse_e2e');
    $subItem = $this->createSubItems();
    $navigation = navigationWithoutBackReferences($subItem);

    expect(analyse(['--fix' => true])[0])->toBe(1);
    expect(DB::table('jobs')->where('queue', 'analyse_e2e')->count())->toBe(1);

    Artisan::call('queue:work', ['connection' => 'mongodb', '--queue' => 'analyse_e2e', '--stop-when-empty' => true, '--tries' => 1]);

    expect((string) SubItem::find($subItem->id)->navigation->ref_id)->toBe((string) $navigation->id);
    expect(DB::table('jobs')->count())->toBe(0);
    expect(DB::table('failed_jobs')->count())->toBe(0);
    expect(analyse()[0])->toBe(0);
});

test('an invalid other_models entry is an error, unlike an optional model that does not exist', function () {
    config()->set('laravel-mongo-auto-sync.other_models', ['broken' => 'not-an-array', 'user' => ['model_namespace' => 'App']]);

    [$code, $output] = analyse();

    expect($code)->toBe(1);
    expect($output)->toContain('Invalid configuration: other_models.broken must be an array with a model_namespace.')
        ->toContain('Skipped other_models.user: class App\User does not exist.')
        ->toContain('0 anomalies, 1 errors');
});

test('an operational error while reading documents is reported and fails the analysis', function () {
    useFixtures('Operational');

    [$code, $output] = analyse();

    expect($code)->toBe(1);
    expect($output)->toContain('Analysis of Tests\AnalyseFixtures\Operational\BrokenConnection::links failed: Database connection [not-configured] not configured.');
});

test('a model directory that cannot be read is an error', function () {
    UnreadableDirectoryStream::register();
    config()->set('laravel-mongo-auto-sync.model_path', UnreadableDirectoryStream::PROTOCOL.'://models');

    try {
        [$code, $output] = analyse();
    } finally {
        UnreadableDirectoryStream::unregister();
    }

    expect($code)->toBe(1);
    expect($output)->toContain('Invalid configuration: model_path "'.UnreadableDirectoryStream::PROTOCOL.'://models" cannot be read.');
});
