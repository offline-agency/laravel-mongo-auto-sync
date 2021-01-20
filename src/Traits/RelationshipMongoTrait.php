<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;

trait RelationshipMongoTrait
{
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
                $typeOnTarget = getTypeOnTarget($relation);
            } else {
                $modelTarget = '';
                $methodOnTarget = '';
                $modelOnTarget = '';
                $typeOnTarget = '';
            }

            $is_EO = is_EO($type);
            $is_EM = is_EM($type);

            $is_EM_target = is_EM($typeOnTarget);
            $is_EO_target = is_EO($typeOnTarget);

            $key = $parent.$method.$counter;
            $is_skippable = $this->getIsSkippable($request->has($key), $hasTarget);

            if ($is_skippable) {
                continue;
            }
            $current_request = $request->has($key) ? $request : $this->getPartialGeneratedRequest();

            $value = $this->getRelationshipRequest($key, $current_request);

            $is_embeds_has_to_be_updated = $request->has($key);

            if (! is_null($value) && ! ($value == '') && ! ($value == '[]')) {
                $objs = json_decode($value);
            } else {
                $objs = getArrayWithEmptyObj($model, $is_EO, $is_EM);
            }

            if ($is_EO || $is_EM) {//EmbedsOne Create - EmbedsMany Create
                if ($event == 'update' && $is_embeds_has_to_be_updated) {

                    //Delete EmbedsMany or EmbedsOne on Target - TODO: check if it is necessary to run deleteTargetObj method
                    if ($hasTarget) {
                        $this->deleteTargetObj($method, $modelTarget, $methodOnTarget, $is_EO, $is_EO_target, $is_EM_target);
                    }
                    //Delete EmbedsMany or EmbedsOne on current object
                    if ($is_EM) {
                        $this->$method = [];
                        $this->save();
                    }
                }

                if (! empty($objs)) {
                    if ($is_EM) {
                        $this->tempEM = [];
                    }

                    $i = 0;
                    foreach ($objs as $obj) {
                        $this->processOneEmbeddedRelationship(
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
                            $is_EO_target,
                            $is_EM_target,
                            $i,
                            $is_embeds_has_to_be_updated,
                            $options);
                        $i++;
                    }

                    if ($is_EM) {
                        $this->$method = $this->tempEM;
                    }
                } else {
                    $this->$method = [];
                }
                $this->save();
            }
        }
    }

    /**
     * @param $mini_model
     * @param string $method_on_target
     * @param bool $is_EO_target
     * @param bool $is_EM_target
     */
    public function updateRelationWithSync($mini_model, string $method_on_target, $is_EO_target, $is_EM_target)
    {
        if ($is_EM_target) {
            $new_values = [];
            foreach ($this->$method_on_target as $temp) {
                $new_values[] = $temp->attributes;
            }
            $new_values[] = $mini_model->attributes;
        } else {
            $new_values = $mini_model->attributes;
        }

        $this->$method_on_target = $new_values;
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
     * @param bool $is_EO
     * @param bool $is_EM
     * @param bool $is_EO_target
     * @param bool $is_EM_target
     * @param $i
     * @param bool $is_embeds_has_to_be_updated
     * @param $options
     * @throws Exception
     */
    public function processOneEmbeddedRelationship(Request $request, $obj, $type, $model, $method, $modelTarget, $methodOnTarget, $modelOnTarget, $event, $hasTarget, $is_EO, $is_EM, $is_EO_target, $is_EM_target, $i, $is_embeds_has_to_be_updated, $options)
    {
        if ($is_embeds_has_to_be_updated) {
            $this->processEmbedOnCurrentCollection($request, $obj, $type, $model, $method, $event, $is_EO, $is_EM, $i, $options);
        }

        if ($hasTarget) {
            $this->processEmbedOnTargetCollection($modelTarget, $obj, $methodOnTarget, $modelOnTarget, $is_EO_target, $is_EM_target);
        }
    }

    /**
     * @param $method
     * @param $modelTarget
     * @param $methodOnTarget
     * @param bool $is_EO
     * @param bool $is_EO_target
     * @param bool $is_EM_target
     */
    public function deleteTargetObj($method, $modelTarget, $methodOnTarget, $is_EO, $is_EO_target, $is_EM_target)
    {
        if ($is_EO) {
            $embedObj = $this->$method;
            if (! is_null($embedObj)) {
                $target_id = $embedObj->ref_id;
                $this->handleSubTarget($target_id, $modelTarget, $methodOnTarget, $is_EO_target, $is_EM_target);
            }
        } else {
            foreach ($this->$method as $target) {
                $this->handleSubTarget($target->ref_id, $modelTarget, $methodOnTarget, $is_EO_target, $is_EM_target);
            }
        }
    }

    /**
     * @param $target_id
     * @param $modelTarget
     * @param $methodOnTarget
     * @param bool $is_EO_target
     * @param bool $is_EM_target
     */
    public function handleSubTarget($target_id, $modelTarget, $methodOnTarget, $is_EO_target, $is_EM_target)
    {
        if ($is_EM_target) {
            $target = new $modelTarget;
            $target = $target->all()->where('id', $target_id)->first();
            if (! is_null($target)) {
                $new_values = [];
                foreach ($target->$methodOnTarget as $temp) {
                    if ($temp->ref_id !== $this->getId()) {
                        $new_values[] = $temp->attributes;
                    }
                }
                $target->$methodOnTarget = $new_values;
                $target->save();
            }
        }
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
        //Init the embed one model
        $embedObj = new $model;

        $EOitems = $embedObj->getItems();
        //Current Obj Create
        foreach ($EOitems as $EOkey => $item) {
            if (! is_null($obj)) {
                $is_ML = isML($item);
                $is_MD = isMD($item);
                $this->checkPropertyExistence($obj, $EOkey, $method, $model);

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
            $this->tempEM[] = $embedObj->attributes;
        }
    }

    /**
     * @param $modelTarget
     * @param $obj
     * @param $methodOnTarget
     * @param $modelOnTarget
     * @param bool $is_EO_target
     * @param bool $is_EM_target
     * @throws Exception
     */
    private function processEmbedOnTargetCollection($modelTarget, $obj, $methodOnTarget, $modelOnTarget, $is_EO_target, $is_EM_target)
    {
        $modelToBeSync = $this->getModelTobeSync($modelTarget, $obj);
        if (! is_null($modelToBeSync)) {
            $miniModel = $this->getEmbedModel($modelOnTarget);
            $modelToBeSync->updateRelationWithSync($miniModel, $methodOnTarget, $is_EO_target, $is_EM_target);
            //TODO:Sync target on level > 1
            //$modelToBeSync->processAllRelationships($request, $event, $methodOnTarget, $methodOnTarget . "-");
        }
    }
}
