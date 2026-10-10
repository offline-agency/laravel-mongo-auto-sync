<?php

namespace Tests\Context;

use Illuminate\Support\Facades\DB;
use OfflineAgency\MongoAutoSync\Context\SyncContextResolver;
use OfflineAgency\MongoAutoSync\Exceptions\SyncContextException;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * A tenant switch of the kind applications use: the 'tenant' connection points to the database of the active
 * tenant. Only the logical tenant id ever reaches a job.
 */
final class TestTenantResolver implements SyncContextResolver
{
    public const TENANTS = ['t1', 't2'];

    public static ?string $active = null;

    public static function activate(?string $tenant): void
    {
        self::$active = $tenant;
        config()->set('database.connections.tenant.database', $tenant === null ? self::database('none') : self::database($tenant));
        DB::purge('tenant');
    }

    public static function database(string $tenant): string
    {
        return config('database.connections.mongodb.database').'_'.$tenant;
    }

    public function current(MDModel $source): ?string
    {
        return $source->getConnectionName() === 'tenant' ? self::$active : null;
    }

    public function run(string $tenant, callable $callback): mixed
    {
        if (! in_array($tenant, self::TENANTS, true)) {
            throw new SyncContextException('Unknown tenant.');
        }

        $previous = self::$active;
        self::activate($tenant);

        try {
            return $callback();
        } finally {
            self::activate($previous);
        }
    }
}
