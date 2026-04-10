<?php

namespace OfflineAgency\MongoAutoSync\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;

use function Termwind\render;

class AnalyseDatabase extends Command
{
    protected $signature = 'mongo-sync:analyse {--fix : Fix inconsistencies}';

    protected $description = 'Analyse database for relationship inconsistencies';

    /**
     * @return void
     */
    public function handle()
    {
        $models = $this->getModels();

        foreach ($models as $modelClass) {
            render("<div class='px-1 bg-blue-500 text-white mt-1'>Analysing {$modelClass}...</div>");

            $model = new $modelClass;
            try {
                if (method_exists($model, 'getMongoRelation')) {
                    /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $model */
                    $relations = $model->getMongoRelation();
                } else {
                    continue;
                }
            } catch (\Exception $e) {
                continue;
            }

            foreach ($relations as $method => $relation) {
                if (SyncHelper::hasTarget($relation)) {
                    $this->analyseRelation($model, $method, $relation, $modelClass);
                }
            }
        }
    }

    /**
     * @param  object  $modelInstance
     * @param  string  $method
     * @param  array<mixed>  $relation
     * @param  string  $modelClass
     * @return void
     */
    private function analyseRelation($modelInstance, $method, $relation, $modelClass)
    {
        $type = $relation['type'];
        $modelTarget = $relation['modelTarget'];
        $methodOnTarget = $relation['methodOnTarget'];

        $is_EO = SyncHelper::is_EO($type);

        // Check if modelInstance is a Model to call count and chunk
        if (! $modelInstance instanceof \MongoDB\Laravel\Eloquent\Model) {
            return;
        }

        $count = $modelInstance->count();
        $bar = $this->output->createProgressBar($count);

        $modelInstance::chunk(100, function ($items) use ($bar, $method, $modelTarget, $methodOnTarget, $is_EO, $modelClass, $relation) {
            foreach ($items as $item) {
                $relationData = $item->$method;

                $targets = $is_EO ? [$relationData] : $relationData;
                if (! is_array($targets) && ! is_iterable($targets)) {
                    $targets = [$targets];
                }

                foreach ($targets as $targetData) {
                    $ref_id = is_array($targetData) ? ($targetData['ref_id'] ?? null) : ($targetData->ref_id ?? null);

                    if ($ref_id) {
                        /** @var \OfflineAgency\MongoAutoSync\Eloquent\Model $targetModel */
                        $targetModel = new $modelTarget;
                        $target = $targetModel->find($ref_id);

                        if (! $target) {
                            render("<div class='text-red-500'>Target not found: {$modelTarget} ID {$ref_id} referenced by {$modelClass} ID {$item->id}</div>");

                            continue;
                        }

                        // Check if target has reference back to source
                        $found = false;
                        $targetRelations = $target->$methodOnTarget;

                        if (is_iterable($targetRelations)) {
                            foreach ($targetRelations as $tr) {
                                $trId = $tr->ref_id ?? ($tr['ref_id'] ?? null);
                                if ($trId == $item->id) {
                                    $found = true;
                                    break;
                                }
                            }
                        } else {
                            $trId = $targetRelations->ref_id ?? ($targetRelations['ref_id'] ?? null);
                            if ($trId == $item->id) {
                                $found = true;
                            }
                        }

                        if (! $found) {
                            render("<div class='text-yellow-500'>Inconsistency: {$modelClass} {$item->id} links to {$modelTarget} {$ref_id}, but target missing back reference.</div>");

                            if ($this->option('fix')) {
                                dispatch(new SyncTargetJob(
                                    $item,
                                    $relation,
                                    $method,
                                    'save',
                                    $item->$method,
                                    [],
                                    [],
                                    $item->id
                                ));
                            }
                        }
                    }
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    /**
     * @return array<string>
     */
    private function getModels()
    {
        $path = config('laravel-mongo-auto-sync.model_path');
        $namespace = config('laravel-mongo-auto-sync.model_namespace');
        $models = [];
        $files = File::allFiles($path);

        foreach ($files as $file) {
            $class = $namespace.'\\'.$file->getFilenameWithoutExtension();
            if (class_exists($class)) {
                $models[] = $class;
            }
        }

        // Also other_models from config
        $otherModels = config('laravel-mongo-auto-sync.other_models', []);
        foreach ($otherModels as $om) {
            // Logic to construct class name
        }

        return $models;
    }
}
