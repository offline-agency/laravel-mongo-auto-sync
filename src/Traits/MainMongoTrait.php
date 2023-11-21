<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use stdClass;

trait MainMongoTrait
{
    protected $has_partial_request;
    protected $request;
    protected $target_additional_data;
    protected $partial_generated_request;
    protected $options;
    protected $tempEM;

    /**
     * @param  Request  $request
     * @param  array  $additionalData
     * @param  array  $options
     * @param  array  $target_additional_data
     * @return $this
     *
     * @throws Exception
     */
    public function storeWithSync(Request $request, array $additionalData = [], array $options = [], array $target_additional_data = [])
    {
        $this->initDataForSync($request, $additionalData, $options, $target_additional_data);
        $this->storeEditAllItems($request, 'add', $options);
        $this->processAllRelationships($request, 'add', '', '', $options);

        //Dispatch the creation event
        $this->fireModelEvent('storeWithSync');

        return $this->fresh();
    }

    /**
     * @param  Request  $request
     * @param  array  $additionalData
     * @param  array  $options
     * @param  array  $target_additional_data
     * @return $this
     *
     * @throws Exception
     */
    public function updateWithSync(Request $request, array $additionalData = [], array $options = [], array $target_additional_data = [])
    {
        $this->initDataForSync($request, $additionalData, $options, $target_additional_data);
        $this->storeEditAllItems($request, 'update', $options);
        $this->processAllRelationships($request, 'update', '', '', $options);

        //Dispatch the update event
        $this->fireModelEvent('updateWithSync');

        return $this->fresh();
    }

    /**
     * @return $this
     */
    public function destroyWithSync()
    {
        //Get the relation info
        $relations = $this->getMongoRelation();
        //Process all relationships
        foreach ($relations as $method => $relation) {
            //Get Relation Save Mode
            $type = $relation['type'];
            $hasTarget = hasTarget($relation);
            if ($hasTarget) {
                $modelTarget = $relation['modelTarget'];
                $methodOnTarget = $relation['methodOnTarget'];
                $modelOnTarget = $relation['modelOnTarget'];
                $is_EO = is_EO($type);
                $is_EM = is_EM($type);
                $is_HO = is_HO($type);
                $is_HM = is_HM($type);
                $typeOnTarget = getTypeOnTarget($relation);
                $is_EM_target = is_EM($typeOnTarget);
                $is_EO_target = is_EO($typeOnTarget);
                if ($is_EO || $is_EM) {//EmbedsOne Create - EmbedsMany Create
                    //Delete EmbedsMany or EmbedsOne on Target
                    $this->deleteTargetObj($method, $modelTarget, $methodOnTarget, $is_EO, $is_EM, $is_EO_target, $is_EM_target);
                }
                //TODO: Need to be implemented
                /* elseif ($is_HM) {//HasMany
                 } elseif ($is_HO) {//HasOne Create
                 }*/
            }
        }
        //Delete current object
        $this->delete();
        //Dispatch the destroy event
        $this->fireModelEvent('destroyWithSync');

        return $this;
    }

    /**
     * @param  array  $options
     * @param  string  $key
     * @return bool|mixed
     */
    private function getOptionValue(array $options, string $key)
    {
        return Arr::has($options, $key) ? $options[$key] : '';
    }

    /**
     * @param $obj
     * @param  string  $EOkey
     * @param  string  $method
     * @param  string  $model
     *
     * @throws Exception
     */
    public function checkPropertyExistence($obj, string $EOkey, $method = '', $model = '')
    {
        if (! property_exists($obj, $EOkey)) {
            $msg = 'Error - '.$EOkey.' attribute not found on obj '.json_encode($obj).' during save of model: '.$model.' and attribute: '.$method;
            throw new Exception($msg);
        }
    }

    /**
     * @param $arr
     * @param  string  $key
     *
     * @throws Exception
     */
    public function checkArrayExistence($arr, string $key)
    {
        if (! Arr::has($arr, $key)) {
            $msg = ('Error - '.$key.' attribute not found on obj '.json_encode($arr));
            throw new Exception($msg);
        }
    }

    /**
     * @param  Request  $request
     * @param  string  $key
     *
     * @throws Exception
     */
    private function checkRequestExistence(Request $request, string $key)
    {
        if (! $request->has($key)) {
            $msg = ('Error - '.$key.' attribute not found in Request '.json_encode($request->all()));
            throw new Exception($msg);
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
     * @param  string  $modelTarget
     * @param  stdClass  $obj
     * @return MDModel|null
     *
     * @throws Exception
     */
    private function getModelTobeSync(string $modelTarget, stdClass $obj)
    {
        $this->checkPropertyExistence($obj, 'ref_id');
        $target_id = $obj->ref_id;

        //Init the Target Model
        $modelToBeSync = new $modelTarget;

        return $modelToBeSync->find($target_id);
    }

    /**
     * @param  string  $key
     * @param  Request  $request
     * @return mixed
     *
     * @throws Exception
     */
    private function getRelationshipRequest(string $key, Request $request)
    {
        $this->checkRequestExistence(
            $request,
            $key
        );

        return Arr::get($request, $key);
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param  Request  $request
     * @param  array  $additionalData
     */
    public function setRequest(Request $request, array $additionalData): void
    {
        $request = array_merge($request->toArray(), $additionalData);
        $this->request = $request;
    }

    /**
     * @return Request
     */
    public function getPartialGeneratedRequest()
    {
        return $this->partial_generated_request;
    }

    /**
     * @param  array  $arr
     */
    public function setPartialGeneratedRequest(array $arr): void
    {
        $partial_generated_request = new Request;
        $partial_generated_request->merge($arr);

        $this->partial_generated_request = $partial_generated_request;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param  array  $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getTargetAdditionalData()
    {
        return $this->target_additional_data;
    }

    /**
     * @param  array  $target_additional_data
     */
    public function setTargetAdditionalData($target_additional_data): void
    {
        $this->target_additional_data = $target_additional_data;
    }

    /**
     * @param  Request  $request
     * @param  array  $additionalData
     * @param  array  $options
     * @param  array  $target_additional_data
     */
    public function initDataForSync(Request $request, array $additionalData, array $options, array $target_additional_data)
    {
        $this->setRequest($request, $additionalData);
        $this->setTargetAdditionalData($target_additional_data);
        $this->setOptions($options);
        $this->setHasPartialRequest();
    }
}
