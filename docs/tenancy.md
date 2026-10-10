# Database and tenant context

Target sync resolves the source and its targets in the database the source lives in, on the synchronous path and in queued `SyncTargetJob`s, whatever the worker's default connection is.

## Connections

- A source stored on another connection (`$model->setConnection('tenant_b')`, `Model::on('tenant_b')`) syncs its targets on that connection.
- A target model declared on the **same** connection as the source model follows the source; a target model declared on **its own** connection (`protected $connection = 'catalog'`) is always written there.
- Referenced (`HasOne` / `HasMany`) children follow the same rule: they are looked up and created on the parent's connection unless the child model declares its own.
- The queue connection (`queue_connection`) and the data connection are independent: jobs can be stored in any queue.

When a job is dispatched it records a `SyncContext` with logical identifiers only:

| Field | Meaning |
| --- | --- |
| `connection` | the connection name of the source (a key of `config('database.connections')`) |
| `database` | the database that connection pointed to at dispatch time |
| `tenant` | the tenant identifier returned by the context resolver, or `null` |

No host, DSN, user name, password or client object is serialised. Before any lookup or write the worker checks that `connection` still points to `database`; if it does not, the job throws `SyncContextException` and writes nothing (the queue retries it and it ends in `failed_jobs` as usual). There is no fallback to another database.

## Tenant databases selected at runtime

When the database of a connection depends on the current tenant (the connection name stays `tenant` while its `database` changes), configure a resolver:

```php
// config/laravel-mongo-auto-sync.php
'context_resolver' => App\Sync\TenantContextResolver::class,
```

```php
use OfflineAgency\MongoAutoSync\Context\SyncContextResolver;
use OfflineAgency\MongoAutoSync\Exceptions\SyncContextException;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

final class TenantContextResolver implements SyncContextResolver
{
    public function current(MDModel $source): ?string
    {
        // the logical tenant id of the source, null for models that are not tenant scoped
        return $source->getConnectionName() === 'tenant' ? Tenancy::currentId() : null;
    }

    public function run(string $tenant, callable $callback): mixed
    {
        $tenantModel = Tenant::find($tenant) ?? throw new SyncContextException('Unknown tenant.');
        $previous = Tenancy::current();
        Tenancy::switchTo($tenantModel);

        try {
            return $callback();
        } finally {
            Tenancy::switchTo($previous);   // also when the job throws: the next job starts clean
        }
    }
}
```

The resolver is the only authority that maps a tenant id to a database: a job cannot name an arbitrary database, and an unknown tenant must throw. `tests/Context/TestTenantResolver.php` is a complete, tested example.

## Payload versions and upgrading

`SyncTargetJob` payload version 2 carries the context. Jobs queued by the 4.0 pre-releases (version 1, no context):
- with a `context_resolver` configured: fail with `SyncContextException` (the job cannot tell which tenant it belongs to);
- without a resolver: run on the models' own connections, as before.

Drain the queue with the previous release before deploying, in particular before enabling a resolver.

## Limits

- Correct-context resolution is not a distributed transaction: a source and a target on two different connections are written independently.
- Deduplication, locking and ordering keys are not implemented by this package version; when they are (stale-write protection, dispatch coalescence), they include the context.
- The analyser (`mongo-sync:analyse`) scans the models' declared connections.
