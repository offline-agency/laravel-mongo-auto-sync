<?php

namespace OfflineAgency\MongoAutoSync\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;
use ReflectionClass;
use Throwable;

/**
 * Checks that every embedded reference with a target (EmbedsOne/EmbedsMany) points to an existing target
 * document that embeds the source back.
 *
 * Exit code 0 only when every supported check finished without anomalies or errors; 1 otherwise, also with
 * --fix (dispatching a repair job is not a new, clean scan). The scan reads documents one by one: it is not
 * a consistent snapshot, it does not compare the embedded values, it does not look for orphan copies on the
 * target side, and HasOne/HasMany relations with a target are reported as unsupported.
 */
class AnalyseDatabase extends Command
{
    protected $signature = 'mongo-sync:analyse {--fix : Dispatch one SyncTargetJob per source document and relation with a missing back reference}';

    protected $description = 'Analyse database for relationship inconsistencies';

    private int $anomalies = 0;

    private int $errors = 0;

    private int $dispatched = 0;

    public function handle(): int
    {
        $this->anomalies = 0;
        $this->errors = 0;
        $this->dispatched = 0;

        foreach ($this->discoverModels() as $modelClass) {
            $this->analyseModel($modelClass);
        }

        $this->line(sprintf(
            'Analysis finished: %d anomalies, %d errors, %d repair jobs dispatched.',
            $this->anomalies,
            $this->errors,
            $this->dispatched,
        ));

        return $this->anomalies === 0 && $this->errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Concrete MDModel classes under model_path (sub-directories keep their sub-namespace) and other_models,
     * without duplicates.
     *
     * @return list<class-string<MDModel>>
     */
    private function discoverModels(): array
    {
        $path = config('laravel-mongo-auto-sync.model_path');
        $namespace = config('laravel-mongo-auto-sync.model_namespace');

        $candidates = [];
        if (! is_string($path) || ! is_string($namespace) || ! is_dir($path)) {
            $this->reportError(sprintf('Invalid configuration: model_path "%s" is not a readable directory.', is_string($path) ? $path : get_debug_type($path)));
        } else {
            $phpFiles = 0;
            $candidates = $this->classesInDirectory($path, rtrim($namespace, '\\'), $phpFiles);
            if ($phpFiles > 0 && $candidates === []) {
                $this->reportError(sprintf('Invalid configuration: no class of namespace %s was found in model_path "%s".', $namespace, $path));
            }
        }

        $otherModels = config('laravel-mongo-auto-sync.other_models', []);
        if (! is_array($otherModels)) {
            $this->reportError('Invalid configuration: other_models must be an array.');
            $otherModels = [];
        }
        foreach ($otherModels as $key => $values) {
            if (! is_array($values) || ! isset($values['model_namespace']) || ! is_string($values['model_namespace'])) {
                $this->reportError(sprintf('Invalid configuration: other_models.%s must be an array with a model_namespace.', $key));

                continue;
            }
            $class = rtrim($values['model_namespace'], '\\').'\\'.Str::ucfirst((string) $key);
            if (! $this->classExists($class)) {
                $this->line(sprintf('Skipped other_models.%s: class %s does not exist.', $key, $class));

                continue;
            }
            $candidates[] = $class;
        }

        $models = [];
        foreach ($candidates as $class) {
            // only concrete MDModel classes: abstract classes and foreign classes are skipped
            if (! is_subclass_of($class, MDModel::class) || ! ($reflection = new ReflectionClass($class))->isInstantiable()) {
                continue;
            }
            // the declared name (case and leading backslash normalised) identifies a class once
            $models[$reflection->getName()] ??= $class;
        }

        return array_values($models);
    }

    /**
     * @return list<string>
     */
    private function classesInDirectory(string $path, string $namespace, int &$phpFiles): array
    {
        $entries = @scandir($path);
        if ($entries === false) {
            $this->reportError(sprintf('Invalid configuration: model_path "%s" cannot be read.', $path));

            return [];
        }

        $classes = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $file = $path.DIRECTORY_SEPARATOR.$entry;
            if (is_dir($file)) {
                $classes = [...$classes, ...$this->classesInDirectory($file, $namespace.'\\'.$entry, $phpFiles)];
            } elseif (str_ends_with($entry, '.php')) {
                $phpFiles++;
                if ($this->classExists($class = $namespace.'\\'.substr($entry, 0, -4))) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    /**
     * class_exists() that reports a class whose file cannot be loaded (for example a missing parent class).
     */
    private function classExists(string $class): bool
    {
        try {
            return class_exists($class);
        } catch (Throwable $e) {
            $this->reportError(sprintf('Invalid configuration: %s cannot be loaded: %s', ltrim($class, '\\'), $e->getMessage()));

            return false;
        }
    }

    /**
     * @param  class-string<MDModel>  $modelClass
     */
    private function analyseModel(string $modelClass): void
    {
        $this->line("Analysing {$modelClass}...");

        try {
            $relations = (new $modelClass)->getMongoRelation();
        } catch (Throwable $e) {
            $this->reportError(sprintf('Cannot read the relations of %s: %s', $modelClass, $e->getMessage()));

            return;
        }

        foreach ($relations as $method => $relation) {
            if (! SyncHelper::hasTarget($relation)) {
                continue;
            }
            $method = (string) $method;
            $type = $relation['type'] ?? null;
            if (! SyncHelper::is_EO($type) && ! SyncHelper::is_EM($type)) {
                $this->reportError(sprintf(
                    'Unsupported target: %s::%s has type %s; only EmbedsOne and EmbedsMany relations with a target can be analysed.',
                    $modelClass,
                    $method,
                    is_string($type) ? $type : get_debug_type($type),
                ));

                continue;
            }
            $modelTarget = $relation['modelTarget'] ?? null;
            $methodOnTarget = $relation['methodOnTarget'] ?? null;
            if (! is_string($modelTarget) || ! is_string($methodOnTarget) || ! is_subclass_of($modelTarget, MDModel::class)) {
                $this->reportError(sprintf('Invalid configuration: %s::%s needs modelTarget (an MDModel class) and methodOnTarget.', $modelClass, $method));

                continue;
            }

            try {
                $this->analyseRelation($modelClass, $method, $relation, $modelTarget, $methodOnTarget);
            } catch (Throwable $e) {
                $this->reportError(sprintf('Analysis of %s::%s failed: %s', $modelClass, $method, $e->getMessage()));
            }
        }
    }

    /**
     * @param  class-string<MDModel>  $modelClass
     * @param  array<string, mixed>  $relation
     * @param  class-string<MDModel>  $modelTarget
     */
    private function analyseRelation(string $modelClass, string $method, array $relation, string $modelTarget, string $methodOnTarget): void
    {
        $typeOnTarget = Arr::get($relation, 'typeOnTarget', 'EmbedsMany');
        foreach ($modelClass::query()->cursor() as $source) {
            $sourceId = SyncHelper::toString($source->getKey());
            $repairTargets = [];

            foreach (SyncHelper::refIds(Arr::get($source->getAttributes(), $method)) as $refId) {
                // @phpstan-ignore argument.type (laravel-mongodb's whereRaw() accepts a filter array; the phpdoc is inherited from Illuminate)
                $target = $modelTarget::query()->whereRaw(['_id' => ['$in' => SyncHelper::idVariants($refId)]])->first();
                if (! $target instanceof MDModel) {
                    $this->reportAnomaly("Target not found: {$modelTarget} ID {$refId} referenced by {$modelClass} ID {$sourceId}");

                    continue;
                }

                $backReferences = SyncHelper::refIds(Arr::get($target->getAttributes(), $methodOnTarget));
                if (in_array($sourceId, $backReferences, true)) {
                    continue;
                }
                // an EmbedsOne inverse that holds another source: --fix would only move the conflict elsewhere
                if (SyncHelper::is_EO($typeOnTarget) && $backReferences !== []) {
                    $this->reportAnomaly("Conflict: {$modelClass} {$sourceId} links to {$modelTarget} {$refId}, which embeds {$modelClass} {$backReferences[0]} instead; not repaired.");

                    continue;
                }
                $this->reportAnomaly("Inconsistency: {$modelClass} {$sourceId} links to {$modelTarget} {$refId}, but target missing back reference.");
                $repairTargets[] = $refId;
            }

            if ($repairTargets !== [] && $this->option('fix')) {
                $this->dispatchRepair($source, $method, $relation, $repairTargets);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $relation
     * @param  list<string>  $repairTargets  existing targets missing the back reference: deleted targets are never recreated
     */
    private function dispatchRepair(MDModel $source, string $method, array $relation, array $repairTargets): void
    {
        try {
            SyncTargetJob::dispatchFor($source, $method, $relation, $repairTargets, []);
            $this->dispatched++;
            $this->line(sprintf('Repair job dispatched for %s %s (%s).', $source::class, SyncHelper::toString($source->getKey()), $method));
        } catch (Throwable $e) {
            $this->reportError(sprintf('Repair job for %s %s (%s) could not be dispatched: %s', $source::class, SyncHelper::toString($source->getKey()), $method, $e->getMessage()));
        }
    }

    private function reportAnomaly(string $message): void
    {
        $this->anomalies++;
        $this->warn($message);
    }

    private function reportError(string $message): void
    {
        $this->errors++;
        $this->error($message);
    }
}
