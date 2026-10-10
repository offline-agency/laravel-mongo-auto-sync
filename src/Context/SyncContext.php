<?php

namespace OfflineAgency\MongoAutoSync\Context;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\SyncContextException;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Where a source document lives, as logical identifiers only: the connection name, the database name of that
 * connection when the job was dispatched and, with a SyncContextResolver, the tenant identifier. A queued job
 * carries it to resolve the source and its targets in the same database, whatever the worker's defaults are.
 */
final class SyncContext
{
    public function __construct(
        public readonly string $connection,
        public readonly string $database,
        public readonly ?string $tenant = null,
    ) {}

    /**
     * @throws InvalidConfigurationException
     */
    public static function capture(MDModel $source): self
    {
        return new self(self::connectionName($source), $source->getConnection()->getDatabaseName(), self::resolver()?->current($source));
    }

    /**
     * @return array{connection: string, database: string, tenant: string|null}
     */
    public function toArray(): array
    {
        return ['connection' => $this->connection, 'database' => $this->database, 'tenant' => $this->tenant];
    }

    /**
     * @param  array<mixed>  $context
     *
     * @throws SyncContextException
     */
    public static function fromArray(array $context): self
    {
        $connection = $context['connection'] ?? null;
        $database = $context['database'] ?? null;
        $tenant = $context['tenant'] ?? null;
        if (! is_string($connection) || $connection === '' || ! is_string($database) || $database === '' || ($tenant !== null && ! is_string($tenant))) {
            throw new SyncContextException('Invalid synchronisation context in the job payload.');
        }

        return new self($connection, $database, $tenant);
    }

    /**
     * Run $callback in this context: inside the tenant (with the configured resolver) after checking that the
     * connection still points to the database recorded at dispatch time.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     *
     * @throws SyncContextException
     * @throws InvalidConfigurationException
     */
    public function run(callable $callback): mixed
    {
        $checked = function () use ($callback) {
            $database = DB::connection($this->connection)->getDatabaseName();
            if ($database !== $this->database) {
                throw new SyncContextException("Connection '{$this->connection}' points to database '{$database}', the synchronisation was requested for '{$this->database}'.");
            }

            return $callback();
        };

        if ($this->tenant === null) {
            return $checked();
        }

        $resolver = self::resolver() ?? throw new SyncContextException('The job belongs to a tenant but no context_resolver is configured.');

        return $resolver->run($this->tenant, $checked);
    }

    /**
     * The name of the connection $model uses (null in the model means the default connection).
     */
    public static function connectionName(Model $model): string
    {
        return $model->getConnectionName() ?? DB::getDefaultConnection();
    }

    /**
     * A new $targetClass model on the connection holding the targets of a $sourceClass document stored on
     * $sourceConnection: a target model declared on the same connection as the source model follows the source
     * (setConnection(), tenant connections); a target declared on its own connection stays there.
     *
     * @throws InvalidConfigurationException
     */
    public static function targetModel(string $sourceClass, string $sourceConnection, string $targetClass): MDModel
    {
        if (! is_subclass_of($sourceClass, MDModel::class) || ! is_subclass_of($targetClass, MDModel::class)) {
            throw new InvalidConfigurationException("Target sync requires models extending MDModel, got {$sourceClass} and {$targetClass}.");
        }

        $target = new $targetClass;
        $declared = self::connectionName($target);

        return $target->setConnection($declared === self::connectionName(new $sourceClass) ? $sourceConnection : $declared);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function resolver(): ?SyncContextResolver
    {
        $class = config('laravel-mongo-auto-sync.context_resolver');
        if ($class === null || $class === '') {
            return null;
        }

        $resolver = is_string($class) && class_exists($class) ? app()->make($class) : null;
        if (! $resolver instanceof SyncContextResolver) {
            throw new InvalidConfigurationException('The context_resolver must be the name of a class implementing '.SyncContextResolver::class.'.');
        }

        return $resolver;
    }
}
