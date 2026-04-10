<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Exceptions\MongoAutoSyncException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Observers\MongoAutoSyncObserver;
use stdClass;

trait MainMongoTrait
{
    /**
     * @return void
     */
    public static function bootMainMongoTrait()
    {
        static::observe(MongoAutoSyncObserver::class);
    }

    /**
     * @var bool
     */
    protected $has_partial_request;

    /**
     * @var Request|null
     */
    protected $request;

    /**
     * @var array<string, mixed>|null
     */
    protected $target_additional_data;

    /**
     * @var Request|null
     */
    protected $partial_generated_request;

    /**
     * @var array<string, mixed>|null
     */
    protected $options;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected $tempEM;

    /**
     * @param  array<string, mixed>  $additionalData
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $target_additional_data
     * @return static
     *
     * @throws MongoAutoSyncException
     */
    public function storeWithSync(Request $request, array $additionalData = [], array $options = [], array $target_additional_data = [])
    {
        Log::info('storeWithSync started for model: '.get_class($this));

        $this->initDataForSync($request, $additionalData, $options, $target_additional_data);
        $this->storeEditAllItems($request, 'add', $options);
        $this->processAllRelationships($request, 'add', '', '', $options);

        // Dispatch the creation event
        $this->fireModelEvent('storeWithSync');

        $fresh = $this->fresh();
        if ($fresh === null) {
            return $this;
        }

        return $fresh;
    }

    /**
     * @param  array<string, mixed>  $additionalData
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $target_additional_data
     * @return static
     *
     * @throws MongoAutoSyncException
     */
    public function updateWithSync(Request $request, array $additionalData = [], array $options = [], array $target_additional_data = [])
    {
        Log::info('updateWithSync started for model: '.get_class($this).' with ID: '.$this->id);

        $this->initDataForSync($request, $additionalData, $options, $target_additional_data);
        $this->storeEditAllItems($request, 'update', $options);
        $this->processAllRelationships($request, 'update', '', '', $options);

        // Dispatch the update event
        $this->fireModelEvent('updateWithSync');

        $fresh = $this->fresh();
        if ($fresh === null) {
            return $this;
        }

        return $fresh;
    }

    /**
     * @param  bool  $syncTargets
     * @return $this
     */
    public function destroyWithSync($syncTargets = true)
    {
        // Get the relation info
        $relations = $this->getMongoRelation();

        // Process all relationships
        foreach ($relations as $method => $relation) {
            // Get Relation Save Mode
            $type = $relation['type'];
            $hasTarget = SyncHelper::hasTarget($relation);
            if ($hasTarget && $syncTargets) {
                $modelTarget = $relation['modelTarget'];
                $methodOnTarget = $relation['methodOnTarget'];
                $modelOnTarget = $relation['modelOnTarget'];
                $typeOnTarget = Arr::has($relation, 'typeOnTarget') ? Arr::get($relation, 'typeOnTarget') : 'EmbedsMany';

                $is_EO = SyncHelper::is_EO($type);
                $is_EM = SyncHelper::is_EM($type);
                $is_HO = SyncHelper::is_HO($type);
                $is_HM = SyncHelper::is_HM($type);

                $is_EO_target = SyncHelper::is_EO($typeOnTarget);
                $is_EM_target = SyncHelper::is_EM($typeOnTarget);

                if ($is_EO || $is_EM) {// EmbedsOne Create - EmbedsMany Create
                    // Delete EmbedsMany or EmbedsOne on Target
                    $this->deleteTargetObj($method, $modelTarget, $methodOnTarget, $is_EO, $is_EO_target, $is_EM_target);
                } elseif ($is_HM || $is_HO) {
                    $this->deleteReferencedObj($method, $is_HO);
                }
            }
        }
        // Delete current object
        $this->delete();

        // Dispatch the destroy event
        $this->fireModelEvent('destroyWithSync');

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return bool|mixed
     */
    private function getOptionValue(array $options, string $key)
    {
        return Arr::has($options, $key) ? $options[$key] : '';
    }

    /**
     * @param  object  $obj
     * @param  string  $method
     * @param  string  $model
     * @return void
     *
     * @throws InvalidConfigurationException
     */
    public function checkPropertyExistence($obj, string $EOkey, $method = '', $model = '')
    {
        if (! property_exists($obj, $EOkey)) {
            $msg = 'Error - '.$EOkey.' attribute not found on obj '.json_encode($obj).' during save of model: '.$model.' and attribute: '.$method;
            throw new InvalidConfigurationException($msg);
        }
    }

    /**
     * @param  array<string, mixed>  $arr
     * @return void
     *
     * @throws InvalidConfigurationException
     */
    public function checkArrayExistence($arr, string $key)
    {
        if (! Arr::has($arr, $key)) {
            $msg = ('Error - '.$key.' attribute not found on obj '.json_encode($arr));
            throw new InvalidConfigurationException($msg);
        }
    }

    /**
     * @return void
     *
     * @throws InvalidRequestException
     */
    private function checkRequestExistence(Request $request, string $key)
    {
        if (! $request->has($key)) {
            $msg = ('Error - '.$key.' attribute not found in Request '.json_encode($request->all()));
            throw new InvalidRequestException($msg);
        }
    }

    /**
     * @param  bool  $request_has_key
     * @param  bool  $hasTarget
     * @return bool
     */
    public function getIsSkippable($request_has_key, $hasTarget = false)
    {
        return ! $request_has_key && $this->getHasPartialRequest() && ! $hasTarget;
    }

    /**
     * @return bool
     */
    public function getHasPartialRequest()
    {
        return $this->has_partial_request;
    }

    public function setHasPartialRequest(): void
    {
        $this->has_partial_request = $this->getOptionValue(
            $this->getOptions(),
            'request_type'
        ) == 'partial';
    }

    /**
     * @return \OfflineAgency\MongoAutoSync\Http\Models\MDModel|null
     *
     * @throws InvalidConfigurationException
     */
    private function getModelTobeSync(string $modelTarget, stdClass $obj)
    {
        $this->checkPropertyExistence($obj, 'ref_id');
        $target_id = $obj->ref_id;

        // Init the Target Model
        /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $modelToBeSync */
        $modelToBeSync = new $modelTarget;

        $found = $modelToBeSync->find($target_id);
        if ($found instanceof \OfflineAgency\MongoAutoSync\Http\Models\MDModel) {
            return $found;
        }

        return null;
    }

    /**
     * @return mixed
     *
     * @throws InvalidRequestException
     */
    private function getRelationshipRequest(string $key, Request $request)
    {
        $this->checkRequestExistence(
            $request,
            $key
        );

        return $request->input($key);
    }

    /**
     * @return Request|null
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param  array<string, mixed>  $additionalData
     */
    public function setRequest(Request $request, array $additionalData): void
    {
        $request = $request->merge($additionalData);
        $this->request = $request;
    }

    /**
     * @return Request|null
     */
    public function getPartialGeneratedRequest()
    {
        return $this->partial_generated_request;
    }

    /**
     * @param  array<string, mixed>  $arr
     */
    public function setPartialGeneratedRequest(array $arr): void
    {
        $partial_generated_request = new Request;
        $partial_generated_request->merge($arr);

        $this->partial_generated_request = $partial_generated_request;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions()
    {
        if ($this->options === null) {
            return [];
        }

        return $this->options;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTargetAdditionalData()
    {
        if ($this->target_additional_data === null) {
            return [];
        }

        return $this->target_additional_data;
    }

    /**
     * @param  array<string, mixed>  $target_additional_data
     */
    public function setTargetAdditionalData($target_additional_data): void
    {
        $this->target_additional_data = $target_additional_data;
    }

    /**
     * @param  array<string, mixed>  $additionalData
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $target_additional_data
     * @return void
     */
    public function initDataForSync(Request $request, array $additionalData, array $options, array $target_additional_data)
    {
        $this->setRequest($request, $additionalData);
        $this->setTargetAdditionalData($target_additional_data);
        $this->setOptions($options);
        $this->setHasPartialRequest();
    }
}
