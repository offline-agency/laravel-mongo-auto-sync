<?php

namespace OfflineAgency\MongoAutoSync\Observers;

use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Jobs\SyncTargetJob;

class MongoAutoSyncObserver
{
    /**
     * @param  mixed  $model
     * @return void
     */
    public function saved($model)
    {
        if (config('laravel-mongo-auto-sync.use_background_sync')) {
            $this->processSync($model, 'save');
        }
    }

    /**
     * @param  mixed  $model
     * @return void
     */
    public function deleted($model)
    {
        if (config('laravel-mongo-auto-sync.use_background_sync')) {
            if (method_exists($model, 'getMongoRelation')) {
                /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $model */
                $relations = $model->getMongoRelation();
                foreach ($relations as $method => $relation) {
                    if (SyncHelper::hasTarget($relation)) {
                        $items = $model->$method;

                        dispatch(new SyncTargetJob(
                            $model,
                            $relation,
                            $method,
                            'delete',
                            $items,
                            [],
                            [],
                            $model->id ?? $model->_id ?? null
                        ));
                    }
                }
            }
        }
    }

    /**
     * @param  mixed  $model
     * @param  string  $event
     * @return void
     */
    private function processSync($model, $event)
    {
        if (! method_exists($model, 'getMongoRelation')) {
            return;
        }

        /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $model */
        $relations = $model->getMongoRelation();
        $requestData = method_exists($model, 'getRequest') && $model->getRequest() ? $model->getRequest()->all() : [];
        $targetAdditionalData = method_exists($model, 'getTargetAdditionalData') ? $model->getTargetAdditionalData() : [];

        foreach ($relations as $method => $relation) {
            if (SyncHelper::hasTarget($relation)) {
                $original = method_exists($model, 'getOriginal') ? $model->getOriginal($method) : null;
                $current = $model->$method;

                if (! empty($original)) {
                    dispatch(new SyncTargetJob(
                        $model,
                        $relation,
                        $method,
                        'delete',
                        $original,
                        $requestData,
                        $targetAdditionalData,
                        $model->id ?? $model->_id ?? null
                    ));
                }

                if (! empty($current)) {
                    dispatch(new SyncTargetJob(
                        $model,
                        $relation,
                        $method,
                        'save',
                        $current,
                        $requestData,
                        $targetAdditionalData,
                        $model->id ?? $model->_id ?? null
                    ));
                }
            }
        }
    }
}
