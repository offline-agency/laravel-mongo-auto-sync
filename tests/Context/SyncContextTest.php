<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use OfflineAgency\MongoAutoSync\Context\SyncContext;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\SyncContextException;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;
use Tests\Context\TestTenantResolver;
use Tests\Models\CtxSubItem;
use Tests\Models\SubItem;

const CTX_NAVIGATION_ID = '6a0000000000000000000001';
const CTX_SUB_ITEM_ID = '6a0000000000000000000002';

beforeEach(function () {
    $mongodb = config('database.connections.mongodb');
    foreach (['tenant_b' => '_b', 'ctx_targets' => '_targets', 'tenant' => '_none'] as $connection => $suffix) {
        config()->set("database.connections.{$connection}", array_merge($mongodb, ['database' => $mongodb['database'].$suffix]));
        DB::purge($connection);
    }
    config()->set('queue.default', 'mongodb');
    config()->set('laravel-mongo-auto-sync.context_resolver', null);
    TestTenantResolver::$active = null;
    DB::table('jobs')->truncate();
    DB::connection('mongodb2')->table('failed_jobs')->truncate();

    foreach (['mongodb', 'tenant_b', 'ctx_targets'] as $connection) {
        ctxSeed($connection, $connection);
    }
    foreach (TestTenantResolver::TENANTS as $tenant) {
        TestTenantResolver::activate($tenant);
        ctxSeed('tenant', 'tenant '.$tenant);
    }
    TestTenantResolver::activate(null);
});

/**
 * The same navigation and sub item ids in every database, with values naming the database.
 */
function ctxSeed(string $connection, string $label): void
{
    $db = DB::connection($connection);
    $db->getCollection('navigation')->deleteMany(['_id' => new ObjectId(CTX_NAVIGATION_ID)]);
    $db->getCollection('sub_items')->deleteMany(['_id' => new ObjectId(CTX_SUB_ITEM_ID)]);
    $db->getCollection('navigation')->insertOne([
        '_id' => new ObjectId(CTX_NAVIGATION_ID), 'text' => 'navigation of '.$label, 'code' => 'N', 'href' => 'h', 'title' => ['en' => 'nav'], 'sub_items' => [],
    ]);
    $db->getCollection('sub_items')->insertOne([
        '_id' => new ObjectId(CTX_SUB_ITEM_ID), 'text' => ['en' => 'sub item of '.$label], 'code' => 'S', 'href' => 'h',
        'navigation' => ['ref_id' => CTX_NAVIGATION_ID, 'text' => 'navigation of '.$label, 'code' => 'N', 'title' => ['en' => 'nav']],
    ]);
}

/**
 * @return array<string, mixed>
 */
function ctxRawNavigation(string $connection): array
{
    $document = DB::connection($connection)->getCollection('navigation')
        ->findOne(['_id' => new ObjectId(CTX_NAVIGATION_ID)], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);

    return is_array($document) ? $document : [];
}

/**
 * Texts of the sub item mini models on the navigation of $connection.
 *
 * @return list<string>
 */
function ctxTargetTexts(string $connection): array
{
    return array_map(fn (array $mini) => $mini['text']['en'] ?? '', ctxRawNavigation($connection)['sub_items'] ?? []);
}

/**
 * Update the sub item stored on $connection (setConnection(), as a tenant-aware application does).
 *
 * @param  class-string<SubItem>  $class
 */
function ctxUpdate(string $connection, string $text, string $class = SubItem::class): void
{
    $subItem = (new $class)->setConnection($connection)->newQuery()->find(CTX_SUB_ITEM_ID);
    $subItem->updateWithSync(new Request, [
        'text' => $text, 'code' => 'S', 'href' => 'h',
        'navigation' => json_encode([['ref_id' => CTX_NAVIGATION_ID, 'text' => 'nav', 'code' => 'N', 'title' => 'nav']]),
    ]);
}

function ctxWorker(array $options = []): void
{
    Artisan::call('queue:work', ['connection' => 'mongodb', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1] + $options);
}

it('syncs the targets in the database of the source, synchronously and through a serialized job', function (bool $background) {
    config()->set('laravel-mongo-auto-sync.use_background_sync', $background);
    $default = ctxRawNavigation('mongodb');
    $targets = ctxRawNavigation('ctx_targets');

    ctxUpdate('tenant_b', 'B updated');
    ctxWorker();

    expect(ctxTargetTexts('tenant_b'))->toBe(['B updated'])
        ->and(ctxRawNavigation('mongodb'))->toEqual($default)
        ->and(ctxRawNavigation('ctx_targets'))->toEqual($targets)
        ->and(DB::table('jobs')->count())->toBe(0);
})->with(['synchronous' => [false], 'background, mongodb queue worker' => [true]]);

it('keeps a target declared on its own connection there', function (bool $background) {
    config()->set('laravel-mongo-auto-sync.use_background_sync', $background);
    $b = ctxRawNavigation('tenant_b');
    $default = ctxRawNavigation('mongodb');

    ctxUpdate('tenant_b', 'to own connection', CtxSubItem::class);
    ctxWorker();

    expect(ctxTargetTexts('ctx_targets'))->toBe(['to own connection'])
        ->and(ctxRawNavigation('tenant_b'))->toEqual($b)
        ->and(ctxRawNavigation('mongodb'))->toEqual($default);
})->with(['synchronous' => [false], 'background, mongodb queue worker' => [true]]);

it('fails without writing when the connection points to another database, then succeeds on retry', function () {
    config()->set('laravel-mongo-auto-sync.use_background_sync', true);
    ctxUpdate('tenant_b', 'B retried');
    $queued = DB::table('jobs')->count();
    $snapshot = ['b' => ctxRawNavigation('tenant_b'), 'default' => ctxRawNavigation('mongodb')];

    // the worker's tenant_b connection now points to another database
    config()->set('database.connections.tenant_b.database', config('database.connections.mongodb.database').'_other');
    DB::purge('tenant_b');
    ctxWorker();

    expect($queued)->toBeGreaterThan(0)
        ->and(DB::connection('mongodb2')->table('failed_jobs')->count())->toBe($queued)
        ->and(DB::connection('tenant_b')->getCollection('navigation')->countDocuments())->toBe(0);
    config()->set('database.connections.tenant_b.database', config('database.connections.mongodb.database').'_b');
    DB::purge('tenant_b');
    expect(['b' => ctxRawNavigation('tenant_b'), 'default' => ctxRawNavigation('mongodb')])->toEqual($snapshot);

    Artisan::call('queue:retry', ['id' => ['all']]);
    ctxWorker();

    expect(ctxTargetTexts('tenant_b'))->toBe(['B retried'])
        ->and(ctxRawNavigation('mongodb'))->toEqual($snapshot['default']);
});

it('runs each tenant job inside its tenant and restores the worker context', function () {
    config()->set('laravel-mongo-auto-sync.use_background_sync', true);
    config()->set('laravel-mongo-auto-sync.context_resolver', TestTenantResolver::class);
    $default = ctxRawNavigation('mongodb');

    TestTenantResolver::activate('t1');
    ctxUpdate('tenant', 'tenant t1 value');
    $t1Jobs = DB::table('jobs')->count();
    TestTenantResolver::activate('t2');
    ctxUpdate('tenant', 'tenant t2 value');
    expect($t1Jobs)->toBeGreaterThan(0)
        ->and(DB::table('jobs')->count())->toBe(2 * $t1Jobs);

    // the worker starts with t2 active: the t1 job must not write t2, the t2 job runs after it
    ctxWorker();

    expect(TestTenantResolver::$active)->toBe('t2')
        ->and(config('database.connections.tenant.database'))->toBe(TestTenantResolver::database('t2'))
        ->and(ctxTargetTexts('tenant'))->toBe(['tenant t2 value']);
    TestTenantResolver::activate('t1');
    expect(ctxTargetTexts('tenant'))->toBe(['tenant t1 value'])
        ->and(ctxRawNavigation('mongodb'))->toEqual($default);
});

it('refuses a job whose context cannot be established, without writing', function (callable $job, string $message) {
    config()->set('laravel-mongo-auto-sync.context_resolver', TestTenantResolver::class);
    $snapshot = [ctxRawNavigation('mongodb'), ctxRawNavigation('tenant_b')];

    expect(fn () => $job()->handle())->toThrow(SyncContextException::class, $message)
        ->and(TestTenantResolver::$active)->toBeNull()
        ->and([ctxRawNavigation('mongodb'), ctxRawNavigation('tenant_b')])->toEqual($snapshot);
})->with([
    'unknown tenant' => [fn () => ctxJob(new SyncContext('tenant', TestTenantResolver::database('t9'), 't9')), 'Unknown tenant.'],
    'legacy payload while tenancy is configured' => [fn () => tap(ctxJob(null), fn (SyncTargetJob $job) => $job->payloadVersion = 1), 'drain the queue'],
    'malformed context' => [fn () => tap(ctxJob(null), fn (SyncTargetJob $job) => $job->context = ['connection' => '', 'database' => 'x']), 'Invalid synchronisation context in the job payload.'],
]);

it('refuses a tenant job when no resolver is configured', function () {
    expect(fn () => ctxJob(new SyncContext('tenant', TestTenantResolver::database('t1'), 't1'))->handle())
        ->toThrow(SyncContextException::class, 'The job belongs to a tenant but no context_resolver is configured.');
});

it('runs a legacy payload on the models\' own connections when no resolver is configured', function () {
    $job = ctxJob(null);
    $job->payloadVersion = 1;
    DB::connection('mongodb')->getCollection('sub_items')->updateOne(['_id' => new ObjectId(CTX_SUB_ITEM_ID)], ['$set' => ['text' => ['en' => 'legacy']]]);

    $job->handle();

    expect(ctxTargetTexts('mongodb'))->toBe(['legacy']);
});

it('rejects an invalid context_resolver and non MDModel classes', function () {
    config()->set('laravel-mongo-auto-sync.context_resolver', stdClass::class);
    expect(fn () => SyncContext::resolver())->toThrow(InvalidConfigurationException::class, 'The context_resolver must be the name of a class implementing');

    expect(fn () => SyncContext::targetModel(SubItem::class, 'mongodb', stdClass::class))
        ->toThrow(InvalidConfigurationException::class, 'Target sync requires models extending MDModel');
});

it('carries logical identifiers only in the job payload', function () {
    config()->set('database.connections.tenant_b.password', 'secret-password');
    $subItem = (new SubItem)->setConnection('tenant_b')->newQuery()->find(CTX_SUB_ITEM_ID);
    $job = SyncTargetJob::dispatchFor($subItem, 'navigation', $subItem->getMongoRelation()['navigation'], [CTX_NAVIGATION_ID], [])->getJob();

    expect($job->context)->toBe(['connection' => 'tenant_b', 'database' => config('database.connections.mongodb.database').'_b', 'tenant' => null])
        ->and(serialize($job))->not->toContain('secret-password');
});

function ctxJob(?SyncContext $context): SyncTargetJob
{
    $relation = (new SubItem)->getMongoRelation()['navigation'];

    return new SyncTargetJob(SubItem::class, CTX_SUB_ITEM_ID, 'navigation', $relation, [CTX_NAVIGATION_ID], [], [], [], $context);
}
