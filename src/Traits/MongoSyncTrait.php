<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use DateTime;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;

trait MongoSyncTrait
{


    public function storeWithSync(Request $request, Array $additionalData = [])
    {
        $request = $request->merge($additionalData);
        //$request = prepareRequest($request,$additionalData);

        $this->storeEditAllItems($request, "add");
        $this->processAllRelationships($request, "add", "", "");

        return $this;
    }

    public function storeEditAllItems($request, $event)
    {
        //Get the item name
        $items = $this->getItems();
        //Current Obj Create
        foreach ($items as $key => $item) {
            $is_ML = isML($item);
            $is_MD = isMD($item);
            $is_fillable = isFillable($item, $event);
            if ($is_fillable) {
                if ($is_ML) {
                    if (is_null($this->$key)) {
                        $old_value = array();
                    } else {
                        $old_value = $this->$key;
                    }
                    $this->$key = ml($old_value, $request->input($key));
                } else if ($is_MD) {
                    //  dd( new UTCDateTime(new DateTime($request->input($key))));
                    if ($request->input($key) == "" || $request->input($key) == null) {
                        //dd('if');
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

    public function processAllRelationships(Request $request, $event, $parent, $counter)
    {
        //Get the relation info
        $relations = $this->getMongoRelation();

        //Process all relationships
        foreach ($relations as $method => $relation) {
            //Get Relation Save Mode
            $type = $relation['type'];
            $mode = $relation['mode'];
            $model = $relation['model'];
            $hasTarget = hasTarget($relation);
            if ($hasTarget) {
                $modelTarget = $relation['modelTarget'];
                $methodOnTarget = $relation['methodOnTarget'];
                $modelOnTarget = $relation['modelOnTarget'];
            } else {
                $modelTarget = "";
                $methodOnTarget = "";
                $modelOnTarget = "";
            }

            $is_EO = is_EO($type);
            $is_EM = is_EM($type);
            $is_HO = is_HO($type);
            $is_HM = is_HM($type);

            $key = $parent . $method . $counter;
            $value = $request->input($key);

            if (!is_null($value) && !($value == "") && !($value == "[]")) {
                $objs = json_decode($value);
            } else {
                $objs = getArrayWithEmptyObj($model, $is_EO, $is_EM);
            }

            if ($is_EO || $is_EM) {//EmbedsOne Create - EmbedsMany Create
                if ($event == "update") {

                    //Delete EmbedsMany or EmbedsOne on Target
                    if ($hasTarget) {
                        $this->deleteTargetObj($method, $modelTarget, $methodOnTarget, $is_EO);

                    }
                    //Delete EmbedsMany or EmbedsOne on current object
                    if ($is_EM) {
                        $this->$method()->delete();
                    }

                }

                if (!empty($objs)) {
                    $i = 0;
                    foreach ($objs as $obj) {
                        $this->processOneEmbededRelationship($request, $obj, $type, $model, $method, $modelTarget, $methodOnTarget, $modelOnTarget, $event, $hasTarget, $is_EO, $is_EM, $i);
                        $i++;
                    }
                } else {
                    $this->$method = [];
                    $this->save();
                }
            }
        }
    }

    public function updateRelationWithSync(Request $request, $methodOnTarget, $modelOnTarget)
    {

        $embededModel = new $modelOnTarget;
        //Get the item name
        $items = $embededModel->getItems();
        $embededObj = $request->input($methodOnTarget);
        $embededObj = json_decode($embededObj);

        //Current Obj Create
        foreach ($items as $key => $item) {
            $is_ML = isML($item);
            $is_MD = isMD($item);

            if ($is_ML) {
                $embededModel->$key = ml(array(), $embededObj->$key);
            } else if ($is_MD) {

                if ($embededObj->$key == "" || $embededObj->$key == null) {
                    //dd('if');
                    $embededModel->$key = null;
                } else {
                    $embededModel->$key = new UTCDateTime(new DateTime($embededObj->$key));
                }

            } else {
                $embededModel->$key = $embededObj->$key;
            }
        }
        $this->$methodOnTarget()->associate($embededModel);
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
     */
    public function processOneEmbededRelationship(Request $request, $obj, $type, $model, $method, $modelTarget, $methodOnTarget, $modelOnTarget, $event, $hasTarget, $is_EO, $is_EM, $i)
    {
        //Init the embedone model
        $embedObj = new $model;

        $EOitems = $embedObj->getItems();
        //Current Obj Create
        foreach ($EOitems as $EOkey => $item) {
            if (!is_null($obj)) {
                $is_ML = isML($item);
                $is_MD = isMD($item);

                if ($is_ML) {
                    $embedObj->$EOkey = ml(array(), $obj->$EOkey);
                } else if ($EOkey == "updated_at" || $EOkey == "created_at") {
                    $embedObj->$EOkey = now();
                } else if ($is_MD) {

                    if ( $obj->$EOkey == "" ||  $obj->$EOkey == null) {
                        //dd('if');
                        $embedObj->$EOkey = null;
                    } else {
                        $embedObj->$EOkey = new UTCDateTime(new DateTime($obj->$EOkey));
                    }


//                    $embedObj->$EOkey = new UTCDateTime(new DateTime($obj->$EOkey));
                } else {
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
        $embedObj->processAllRelationships($request, $event, $method . "-", $counter);

        if ($is_EO) {
            $this->$method = $embedObj->attributes;
        } else {
            $this->$method()->associate($embedObj);
        }
        $this->save();

        //dd($embedObj, $this);

        if ($hasTarget) {
            //sync Permission to Permissiongroup
            //Init the Target Model
            $target_model = new $modelTarget;
            $target_id = $obj->ref_id;
            $ref_id = $this->getId();

            $requestToBeSync = getRequestToBeSync($ref_id, $modelOnTarget, $request, $methodOnTarget);

            $modelToBeSync = new $modelTarget;
            $modelToBeSync = $modelToBeSync->find($target_id);
            if (!is_null($modelToBeSync)) {
                $modelToBeSync->updateRelationWithSync($requestToBeSync, $methodOnTarget, $modelOnTarget);
                //TODO:Sync target on level > 1
                //$modelToBeSync->processAllRelationships($request, $event, $methodOnTarget, $methodOnTarget . "-");
            }
        }
    }

    /**
     * @param $id
     * @param Request $request
     */
    public function updateWithSync(Request $request, Array $additionalData = [])
    {
        $request = $request->merge($additionalData);
        $this->storeEditAllItems($request, "update");
        $this->processAllRelationships($request, "update", "", "");
    }

    /**
     * @param $method
     * @param $typeTarget
     * @param $modelTarget
     * @param $methodOnTarget
     * @param $id
     */
    public function deleteTargetObj($method, $modelTarget, $methodOnTarget, $is_EO)
    {
        if ($is_EO) {
            $embedObj = $this->$method;
            if (!is_null($embedObj)) {
                $target_id = $embedObj->ref_id;
                $this->handleSubTarget($target_id, $modelTarget, $methodOnTarget);
            }
        } else {
            foreach ($this->$method as $target) {
                $this->handleSubTarget($target->ref_id, $modelTarget, $methodOnTarget);
            }
        }
    }

    public function handleSubTarget($target_id, $modelTarget, $methodOnTarget)
    {
        $id = $this->getId();
        $target = new $modelTarget;
        $target = $target->all()->where('id', $target_id)->first();
        if (!is_null($target)) {
            $subTarget = $target->$methodOnTarget()->where('ref_id', $id)->first();
            $temps = $target->$methodOnTarget()->where('ref_id', '!=', $id);
            $target->$methodOnTarget()->delete($subTarget);
            foreach ($temps as $temp) {
                $target->$methodOnTarget()->associate($temp);
                $target->save();
            }
        }
    }


    public function destroyWithSync()
    {

        //Get the relation info
        $relations = $this->getMongoRelation();

        //Process all relationships
        foreach ($relations as $method => $relation) {
            //Get Relation Save Mode
            $type = $relation['type'];
            $mode = $relation['mode'];
            $model = $relation['model'];
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

                } else if ($is_HM) {//HasMany

                } else if ($is_HO) {//HasOne Create

                }
            }
        }
        //Delete current object
        $this->delete();
    }

}
