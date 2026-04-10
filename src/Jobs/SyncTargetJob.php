<?php

namespace OfflineAgency\MongoAutoSync\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;

class SyncTargetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var mixed */
    protected $model;

    /** @var array<mixed> */
    protected $relation;

    /** @var string */
    protected $method;

    /** @var string */
    protected $event;

    /** @var mixed */
    protected $items;

    /** @var array<mixed> */
    protected $requestData;

    /** @var array<mixed> */
    protected $targetAdditionalData;

    /** @var mixed */
    protected $sourceId;

    /**
     * @param  mixed  $model
     * @param  array<mixed>  $relation
     * @param  string  $method
     * @param  string  $event
     * @param  mixed  $items
     * @param  array<mixed>  $requestData
     * @param  array<mixed>  $targetAdditionalData
     * @param  mixed  $sourceId
     */
    public function __construct($model, $relation, $method, $event, $items, $requestData = [], $targetAdditionalData = [], $sourceId = null)
    {
        $this->relation = $relation;
        $this->method = $method;
        $this->event = $event;
        $this->requestData = $requestData;
        $this->targetAdditionalData = $targetAdditionalData;
        $this->items = $items;
        $this->sourceId = $sourceId;

        if ($event === 'delete') {
            $this->model = null;
        } else {
            $this->model = $model;
        }
    }

    /**
     * @return void
     */
    public function handle()
    {
        $relation = $this->relation;
        $method = $this->method;
        $event = $this->event;

        if ($this->model && is_object($this->model)) {
            $request = new Request;
            $request->merge($this->requestData);
            if (method_exists($this->model, 'setRequest')) {
                $this->model->setRequest($request, []);
            }
            if (method_exists($this->model, 'setTargetAdditionalData')) {
                $this->model->setTargetAdditionalData($this->targetAdditionalData);
            }
            if (method_exists($this->model, 'setMiniModels')) {
                $this->model->setMiniModels();
            }
        }

        $items = $this->items;

        if (empty($items)) {
            return;
        }

        $type = $relation['type'];
        $modelTarget = $relation['modelTarget'];
        $methodOnTarget = $relation['methodOnTarget'];
        $modelOnTarget = $relation['modelOnTarget'];
        $typeOnTarget = Arr::get($relation, 'typeOnTarget', 'EmbedsMany');

        $is_EO = SyncHelper::is_EO($type);
        $is_EO_target = SyncHelper::is_EO($typeOnTarget);
        $is_EM_target = SyncHelper::is_EM($typeOnTarget);

        if ($event === 'delete') {
            $this->processDelete($items, $is_EO, $modelTarget, $methodOnTarget, $is_EO_target, $is_EM_target);
        } else {
            $this->processUpdate($items, $is_EO, $modelTarget, $methodOnTarget, $modelOnTarget, $is_EO_target, $is_EM_target);
        }
    }

    /**
     * @param  mixed  $items
     * @param  bool  $is_EO
     * @param  string  $modelTarget
     * @param  string  $methodOnTarget
     * @param  string  $modelOnTarget
     * @param  bool  $is_EO_target
     * @param  bool  $is_EM_target
     * @return void
     */
    private function processUpdate($items, $is_EO, $modelTarget, $methodOnTarget, $modelOnTarget, $is_EO_target, $is_EM_target)
    {
        if (is_object($this->model) && method_exists($this->model, 'getEmbedModel')) {
            $miniModel = $this->model->getEmbedModel($modelOnTarget);
        } else {
            return;
        }

        $itemList = $is_EO ? [$items] : $items;
        if (! is_array($itemList) && ! is_iterable($itemList)) {
            $itemList = [$itemList];
        }

        $ids = [];
        foreach ($itemList as $item) {
            $ref_id = is_array($item) ? ($item['ref_id'] ?? null) : ($item->ref_id ?? null);
            if ($ref_id) {
                $ids[] = $ref_id;
            }
        }

        if (empty($ids)) {
            return;
        }

        /** @var \MongoDB\Laravel\Eloquent\Model $targetModel */
        $targetModel = new $modelTarget;
        // Batch load
        // MongoDB IDs can be strings or ObjectId.
        // Assuming they match what is in ref_id.
        $targets = $targetModel::whereIn('_id', $ids)->get();
        // keyBy might fail if _id is object, but usually it casts to string

        foreach ($targets as $target) {
            if (method_exists($target, 'updateRelationWithSync')) {
                $target->updateRelationWithSync($miniModel, $methodOnTarget, $is_EO_target, $is_EM_target);
            }

            // TODO: Recursion will go here
            // $this->processRecursiveSync($target, $modelOnTarget);
        }
    }

    /**
     * @param  mixed  $items
     * @param  bool  $is_EO
     * @param  string  $modelTarget
     * @param  string  $methodOnTarget
     * @param  bool  $is_EO_target
     * @param  bool  $is_EM_target
     * @return void
     */
    private function processDelete($items, $is_EO, $modelTarget, $methodOnTarget, $is_EO_target, $is_EM_target)
    {
        $itemList = $is_EO ? [$items] : $items;
        if (! is_array($itemList) && ! is_iterable($itemList)) {
            $itemList = [$itemList];
        }

        $ids = [];
        foreach ($itemList as $item) {
            $ref_id = is_array($item) ? ($item['ref_id'] ?? null) : ($item->ref_id ?? null);
            if ($ref_id) {
                $ids[] = $ref_id;
            }
        }

        if (empty($ids)) {
            return;
        }

        /** @var \MongoDB\Laravel\Eloquent\Model $targetModel */
        $targetModel = new $modelTarget;
        $targets = $targetModel::whereIn('_id', $ids)->get();

        foreach ($targets as $target) {
            if ($is_EM_target) {
                $new_values = [];
                if (is_iterable($target->$methodOnTarget)) {
                    foreach ($target->$methodOnTarget as $temp) {
                        if ($temp->ref_id !== $this->sourceId) {
                            $new_values[] = $temp->attributes;
                        }
                    }
                }
                $target->$methodOnTarget = $new_values;
                $target->save();
            }
        }
    }
}
