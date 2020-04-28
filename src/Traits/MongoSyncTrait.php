<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\UTCDateTime;

trait MongoSyncTrait
{
    protected $has_partial_request;
    protected $request;
    protected $target_additional_data;
    protected $partial_generated_request;
    protected $options;

    /**
     * @param Request $request
     * @param array $additionalData
     * @param array $options
     * @param array $target_additional_data
     * @return $this
     * @throws Exception
     */
    public function storeWithSync(Request $request, array $additionalData = [], array $options = [], array $target_additional_data = [])
    {
        $this->initDataForSync($request, $additionalData, $options, $target_additional_data);
        $this->storeEditAllItems($request, 'add', $options);
        $this->processAllRelationships($request, 'add', '', '', $options);

        //Dispatch the creation event
        $this->fireModelEvent('storeWithSync');

        return $this;
    }

    /**
     * @param Request $request
     * @param string $event
     * @param array $options
     * @throws Exception
     */
    public function storeEditAllItems(Request $request, string $event, array $options)
    {
        //Get the item name
        $items = $this->getItems();

        //Current Obj Create
        foreach ($items as $key => $item) {
            $is_ML = isML($item);
            $is_MD = isMD($item);

            $is_fillable = isFillable($item, $event);
            $is_skippable = $this->getIsSkippable($request->has($key));
            if ($is_skippable) {
                continue;
            } else {
                $this->checkRequestExistence(
                    $request,
                    $key
                );
            }

            if ($is_fillable) {
                if ($is_ML) {
                    if (is_null($this->$key)) {
                        $old_value = [];
                    } else {
                        $old_value = $this->$key;
                    }
                    $this->$key = ml($old_value, $request->input($key));
                } elseif ($is_MD) {
                    if ($request->input($key) == '' || $request->input($key) == null) {
                        $this->$key = null;
                    } else {
                        $this->$key = new UTCDateTime(new DateTime($request->input($key)));
                    }
                } else {
                    $this->$key = $request->input($key);
                }
            }
        }

        $this->save();
    }

    /**
     * @param Request $request
     * @param string $event
     * @param string $parent
     * @param string $counter
     * @param array $options
     * @throws Exception
     */
    public function processAllRelationships(Request $request, string $event, string $parent, string $counter, array $options)
    {
        $this->setMiniModels(); // For target Sync
        Log::channel('single')->info(json_encode($this->getMiniModels()));
        //Get the relation info
        $relations = $this->getMongoRelation();

        //Process all relationships
        foreach ($relations as $method => $relation) {
            //Get Relation Save Mode
            $type = $relation['type'];
            $model = $relation['model'];
            $hasTarget = hasTarget($relation);
            if ($hasTarget) {
                $modelTarget = $relation['modelTarget'];
                $methodOnTarget = $relation['methodOnTarget'];
                $modelOnTarget = $relation['modelOnTarget'];
            } else {
                $modelTarget = '';
                $methodOnTarget = '';
                $modelOnTarget = '';
            }

            $is_EO = is_EO($type);
            $is_EM = is_EM($type);

            $key = $parent.$method.$counter;
            $is_skippable = $this->getIsSkippable($request->has($key));
            $value = $this->getRelationshipRequest($key, $is_skippable);

            if (! is_null($value) && ! ($value == '') && ! ($value == '[]')) {
                $objs = json_decode($value);
            } else {
                $objs = getArrayWithEmptyObj($model, $is_EO, $is_EM);
            }

            if ($is_EO || $is_EM) {//EmbedsOne Create - EmbedsMany Create
                if ($event == 'update' && ! $is_skippable) {

                    //Delete EmbedsMany or EmbedsOne on Target - TODO: check if it is necessary to run deleteTargetObj method
                    if ($hasTarget) {
                        $this->deleteTargetObj($method, $modelTarget, $methodOnTarget, $is_EO);
                    }
                    //Delete EmbedsMany or EmbedsOne on current object
                    if ($is_EM) {
                        $this->$method()->delete();
                    }
                }

                if (! empty($objs)) {
                    $i = 0;
                    foreach ($objs as $obj) {
                        $this->processOneEmbededRelationship(
                            $request,
                            $obj,
                            $type,
                            $model,
                            $method,
                            $modelTarget,
                            $methodOnTarget,
                            $modelOnTarget, $event,
                            $hasTarget,
                            $is_EO,
                            $is_EM,
                            $i,
                            $is_skippable,
                            $options);
                        $i++;
                    }
                } else {
                    $this->$method = [];
                    $this->save();
                }
            }
        }
    }

    /**
     * @param $mini_model
     * @param string $method_on_target
     */
    public function updateRelationWithSync($mini_model, string $method_on_target)
    {
        $this->$method_on_target()->associate($mini_model);
        $this->save();
    }

    /**
     * @param Request $request
     * @param $obj
     * @param $type
     * @param $model
     * @param $method
     * @param $modelTarget
     * @param $methodOnTarget
     * @param $modelOnTarget
     * @param $event
     * @param $hasTarget
     * @param $is_EO
     * @param $is_EM
     * @param $i
     * @param bool $is_skippable
     * @param $options
     * @throws Exception
     */
    public function processOneEmbededRelationship(Request $request, $obj, $type, $model, $method, $modelTarget, $methodOnTarget, $modelOnTarget, $event, $hasTarget, $is_EO, $is_EM, $i, $is_skippable, $options)
    {
        if (! $is_skippable) {
            $this->processEmbedOnCurrentCollection($request, $obj, $type, $model, $method, $event, $is_EO, $is_EM, $i, $options);
        }

        if ($hasTarget) {
            $this->processEmbedOnTargetCollection($modelTarget, $obj, $methodOnTarget, $modelOnTarget);
        }
    }

    /**
     * @param Request $request
     * @param array $additionalData
     * @param array $options
     * @param array $target_additional_data
     * @return $this
     * @throws Exception
     */
    public function updateWithSync(Request $request, array $additionalData = [], array $options = [], array $target_additional_data = [])
    {
        $this->initDataForSync($request, $additionalData, $options, $target_additional_data);
        $this->storeEditAllItems($request, 'update', $options);
        $this->processAllRelationships($request, 'update', '', '', $options);

        //Dispatch the update event
        $this->fireModelEvent('updateWithSync');

        return $this;
    }

    /**
     * @param $method
     * @param $modelTarget
     * @param $methodOnTarget
     * @param $is_EO
     */
    public function deleteTargetObj($method, $modelTarget, $methodOnTarget, $is_EO)
    {
        if ($is_EO) {
            $embedObj = $this->$method;
            if (! is_null($embedObj)) {
                $target_id = $embedObj->ref_id;
                $this->handleSubTarget($target_id, $modelTarget, $methodOnTarget);
            }
        } else {
            foreach ($this->$method as $target) {
                $this->handleSubTarget($target->ref_id, $modelTarget, $methodOnTarget);
            }
        }
    }

    /**
     * @param $target_id
     * @param $modelTarget
     * @param $methodOnTarget
     */
    public function handleSubTarget($target_id, $modelTarget, $methodOnTarget)
    {
        $id = $this->getId();
        $target = new $modelTarget;
        $target = $target->all()->where('id', $target_id)->first();
        if (! is_null($target)) {
            $subTarget = $target->$methodOnTarget()->where('ref_id', $id)->first();
            $temps = $target->$methodOnTarget()->where('ref_id', '!=', $id);
            $target->$methodOnTarget()->delete($subTarget);
            foreach ($temps as $temp) {
                $target->$methodOnTarget()->associate($temp);
                $target->save();
            }
        }
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

                if ($is_EO || $is_EM) {//EmbedsOne Create - EmbedsMany Create
                    //Delete EmbedsMany or EmbedsOne on Target
                    $this->deleteTargetObj($method, $modelTarget, $methodOnTarget, $is_EO);
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
     * @param array $options
     * @param string $key
     * @return bool|mixed
     */
    private function getOptionValue(array $options, string $key)
    {
        return Arr::has($options, $key) ? $options[$key] : '';
    }

    /**
     * @param $obj
     * @param string $EOkey
     * @throws Exception
     */
    public function checkPropertyExistence($obj, string $EOkey)
    {
        if (! property_exists($obj, $EOkey)) {
            $msg = ('Error - '.$EOkey.' attribute not found on obj '.json_encode($obj));
            throw new Exception($msg);
        }
    }

    /**
     * @param $arr
     * @param string $key
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
     * @param Request $request
     * @param string $key
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
     * @param bool $request_has_key
     * @return bool
     */
    public function getIsSkippable($request_has_key)
    {
        return ! $request_has_key && $this->getHasPartialRequest();
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
     * @param Request $request
     * @param $obj
     * @param $type
     * @param $model
     * @param $method
     * @param $event
     * @param $is_EO
     * @param $is_EM
     * @param $i
     * @param $options
     * @throws Exception
     */
    private function processEmbedOnCurrentCollection(Request $request, $obj, $type, $model, $method, $event, $is_EO, $is_EM, $i, $options)
    {
        //Init the embedone model
        $embedObj = new $model;

        $EOitems = $embedObj->getItems();
        //Current Obj Create
        foreach ($EOitems as $EOkey => $item) {
            if (! is_null($obj)) {
                $is_ML = isML($item);
                $is_MD = isMD($item);

                if ($is_ML) {
                    $embedObj->$EOkey = ml([], $obj->$EOkey);
                } elseif ($EOkey == 'updated_at' || $EOkey == 'created_at') {
                    $embedObj->$EOkey = now();
                } elseif ($is_MD) {
                    if ($obj->$EOkey == '' || $obj->$EOkey == null) {
                        $embedObj->$EOkey = null;
                    } else {
                        $embedObj->$EOkey = new UTCDateTime(new DateTime($obj->$EOkey));
                    }
                } else {
                    $this->checkPropertyExistence($obj, $EOkey);
                    $embedObj->$EOkey = $obj->$EOkey;
                }
            }
        }

        //else if($is_EM){//To be implemented}
        //else if($is_HM){//To be implemented}
        //else if($is_HO){//To be implemented}

        //Get counter for embeds many with level > 1
        $counter = getCounterForRelationships($method, $is_EO, $is_EM, $i);
        //Check for another Level of Relationship
        $embedObj->processAllRelationships($request, $event, $method.'-', $counter, $options);

        if ($is_EO) {
            $this->$method = $embedObj->attributes;
        } else {
            $this->$method()->associate($embedObj);
        }
        $this->save();
    }

    /**
     * @param $modelTarget
     * @param $obj
     * @param $methodOnTarget
     * @param $modelOnTarget
     * @throws Exception
     */
    private function processEmbedOnTargetCollection($modelTarget, $obj, $methodOnTarget, $modelOnTarget)
    {
        $this->checkPropertyExistence($obj, 'ref_id');
        $target_id = $obj->ref_id;

        //Init the Target Model
        $modelToBeSync = new $modelTarget;
        $modelToBeSync = $modelToBeSync->find($target_id);
        if (! is_null($modelToBeSync)) {
            $miniModel = $this->getEmbedModel($modelOnTarget);
            //Log::channel('single')->info(json_encode($miniModel));
            //Log::channel('single')->info(json_encode($target_id));
            //Log::channel('single')->info(json_encode($methodOnTarget));
            $modelToBeSync->updateRelationWithSync($miniModel, $methodOnTarget);
            //TODO:Sync target on level > 1
            //$modelToBeSync->processAllRelationships($request, $event, $methodOnTarget, $methodOnTarget . "-");
        }
    }

    /**
     * @param string $key
     * @param bool $is_skippable
     * @return mixed
     */
    private function getRelationshipRequest(string $key, $is_skippable)
    {
        $request = $is_skippable ? $this->getPartialGeneratedRequest() : $this->getRequest();

        return $request->input($key);
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param Request $request
     * @param array $additionalData
     */
    public function setRequest(Request $request, array $additionalData): void
    {
        $request = $request->merge($additionalData);
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
     * @param array $arr
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
     * @param array $options
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
     * @param array $target_additional_data
     */
    public function setTargetAdditionalData($target_additional_data): void
    {
        $this->target_additional_data = $target_additional_data;
    }

    /**
     * @param Request $request
     * @param array $additionalData
     * @param array $options
     * @param array $target_additional_data
     */
    public function initDataForSync(Request $request, array $additionalData, array $options, array $target_additional_data)
    {
        $this->setRequest($request, $additionalData);
        $this->setTargetAdditionalData($target_additional_data);
        $this->setOptions($options);
        $this->setHasPartialRequest();
    }
}
