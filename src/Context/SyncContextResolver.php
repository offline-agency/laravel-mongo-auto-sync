<?php

namespace OfflineAgency\MongoAutoSync\Context;

use OfflineAgency\MongoAutoSync\Exceptions\SyncContextException;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Application adapter for dynamically selected (tenant) databases, configured as
 * 'laravel-mongo-auto-sync.context_resolver'. It maps a logical tenant identifier to the application's own
 * tenant switch: the identifier is the only tenant information stored in a job, never credentials or DSNs.
 */
interface SyncContextResolver
{
    /**
     * The logical identifier of the tenant whose database holds $source, captured when a job is dispatched;
     * null when $source does not belong to a tenant.
     */
    public function current(MDModel $source): ?string;

    /**
     * Run $callback with the tenant $tenant active and restore the previous state afterwards, also when
     * $callback throws. An unknown or unavailable tenant must throw, never fall back to another one.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     *
     * @throws SyncContextException
     */
    public function run(string $tenant, callable $callback): mixed;
}
