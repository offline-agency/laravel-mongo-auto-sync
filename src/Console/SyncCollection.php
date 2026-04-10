<?php

namespace OfflineAgency\MongoAutoSync\Console;

use Illuminate\Console\Command;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;

class SyncCollection extends Command
{
    protected $signature = 'mongo-sync:sync-collection {model}';

    protected $description = 'Sync relationships for a collection';

    /**
     * @return void
     */
    public function handle()
    {
        $modelClass = $this->argument('model');
        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            $className = is_string($modelClass) ? $modelClass : (is_null($modelClass) ? 'null' : gettype($modelClass));
            $this->error("Class $className does not exist.");

            return;
        }

        /** @var \OfflineAgency\MongoAutoSync\Eloquent\Model $model */
        $model = new $modelClass;
        $count = $model->count();
        $bar = $this->output->createProgressBar($count);

        $model::chunk(100, function ($items) use ($bar) {
            foreach ($items as $item) {
                if (method_exists($item, 'getMongoRelation')) {
                    /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $item */
                    $relations = $item->getMongoRelation();
                    foreach ($relations as $method => $relation) {
                        if (SyncHelper::hasTarget($relation)) {
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
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Sync dispatching complete.');
    }
}
