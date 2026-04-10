<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use MongoDB\BSON\UTCDateTime;
use OfflineAgency\MongoAutoSync\Exceptions\MongoAutoSyncException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;

trait RelationshipMongoTrait
{
    /**
     * @param  array<string, mixed>  $options
     * @return void
     *
     * @throws MongoAutoSyncException
     */
    public function processAllRelationships(Request $request, string $event, string $parent, string $counter, array $options)
    {
        $this->setMiniModels(); // For target Sync

        // Get the relation info
        $relations = $this->getMongoRelation();

        // Process all relationships
        foreach ($relations as $method => $relation) {
            // Get Relation Save Mode
            $type = $relation['type'];
            $model = $relation['model'];
            $hasTarget = SyncHelper::hasTarget($relation);
            if ($hasTarget) {
                $modelTarget = $relation['modelTarget'];
                $methodOnTarget = $relation['methodOnTarget'];
                $modelOnTarget = $relation['modelOnTarget'];
                $typeOnTarget = Arr::has($relation, 'typeOnTarget') ? Arr::get($relation, 'typeOnTarget') : 'EmbedsMany';
            } else {
                $modelTarget = '';
                $methodOnTarget = '';
                $modelOnTarget = '';
                $typeOnTarget = '';
            }

            $is_EO = SyncHelper::is_EO($type);
            $is_EM = SyncHelper::is_EM($type);
            $is_HO = SyncHelper::is_HO($type);
            $is_HM = SyncHelper::is_HM($type);

            $is_EM_target = SyncHelper::is_EM($typeOnTarget);
            $is_EO_target = SyncHelper::is_EO($typeOnTarget);

            $key = $parent.$method.$counter;
            $is_skippable = $this->getIsSkippable($request->has($key), $hasTarget);

            if ($is_skippable) {
                continue;
            }
            $partialRequest = $this->getPartialGeneratedRequest();
            $current_request = $request->has($key) ? $request : ($partialRequest ?? $request);

            $value = $this->getRelationshipRequest($key, $current_request);

            $is_embeds_has_to_be_updated = $request->has($key);

            if (! is_null($value) && ! ($value == '') && ! ($value == '[]')) {
                $objs = json_decode($value);
            } else {
                $objs = SyncHelper::getArrayWithEmptyObj($model, $is_EO, $is_EM);
            }

            if ($is_EO || $is_EM) {// EmbedsOne Create - EmbedsMany Create
                if ($event == 'update' && $is_embeds_has_to_be_updated) {

                    // Delete EmbedsMany or EmbedsOne on Target - TODO: check if it is necessary to run deleteTargetObj method
                    if ($hasTarget) {
                        $this->deleteTargetObj($method, $modelTarget, $methodOnTarget, $is_EO, $is_EO_target, $is_EM_target);
                    }
                    // Delete EmbedsMany or EmbedsOne on current object
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
            } elseif ($is_HM || $is_HO) {
                if ($event == 'update' && $is_embeds_has_to_be_updated) {
                    $this->syncReferencedDelete($objs, $method, $relation, $is_HO);
                }

                if (! empty($objs)) {
                    $this->processReferencedRelationship($request, $objs, $method, $relation, $event, $options);
                }
            }
        }
    }

    /**
     * @param  object  $mini_model
     * @param  bool  $is_EO_target
     * @param  bool  $is_EM_target
     * @return void
     */
    public function updateRelationWithSync($mini_model, string $method_on_target, $is_EO_target, $is_EM_target)
    {
        if ($is_EM_target) {
            $new_values = [];
            foreach ($this->$method_on_target as $temp) {
                /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $temp */
                $new_values[] = $temp->attributes;
            }
            /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $mini_model */
            $new_values[] = $mini_model->attributes;
        } else {
            /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $mini_model */
            $new_values = $mini_model->attributes;
        }

        $this->$method_on_target = $new_values;
        $this->save();
    }

    /**
     * @param  object  $obj
     * @param  string  $type
     * @param  string  $model
     * @param  string  $method
     * @param  string  $modelTarget
     * @param  string  $methodOnTarget
     * @param  string  $modelOnTarget
     * @param  string  $event
     * @param  bool  $hasTarget
     * @param  bool  $is_EO
     * @param  bool  $is_EM
     * @param  bool  $is_EO_target
     * @param  bool  $is_EM_target
     * @param  int  $i
     * @param  bool  $is_embeds_has_to_be_updated
     * @param  array<string, mixed>  $options
     * @return void
     *
     * @throws MongoAutoSyncException
     */
    public function processOneEmbeddedRelationship(Request $request, $obj, $type, $model, $method, $modelTarget, $methodOnTarget, $modelOnTarget, $event, $hasTarget, $is_EO, $is_EM, $is_EO_target, $is_EM_target, $i, $is_embeds_has_to_be_updated, array $options)
    {
        if ($is_embeds_has_to_be_updated) {
            $this->processEmbedOnCurrentCollection($request, $obj, $type, $model, $method, $event, $is_EO, $is_EM, $i, $options);
        }

        if ($hasTarget) {
            $this->processEmbedOnTargetCollection($modelTarget, $obj, $methodOnTarget, $modelOnTarget, $is_EO_target, $is_EM_target, $request, $event, $options);
        }
    }

    /**
     * @return void
     */
    public function deleteTargetObj(string $method, string $modelTarget, string $methodOnTarget, bool $is_EO, bool $is_EO_target, bool $is_EM_target)
    {
        if (config('laravel-mongo-auto-sync.use_background_sync')) {
            return;
        }

        $targetIds = [];

        if ($is_EO) {
            $embedObj = $this->$method;
            if (! is_null($embedObj)) {
                $targetIds[] = $embedObj->ref_id;
            }
        } else {
            if (is_iterable($this->$method)) {
                foreach ($this->$method as $target) {
                    $targetIds[] = $target->ref_id;
                }
            }
        }

        if (! empty($targetIds)) {
            $this->batchHandleSubTarget($targetIds, $modelTarget, $methodOnTarget, $is_EO_target, $is_EM_target);
        }
    }

    /**
     * @param  array<int|string, mixed>  $targetIds
     * @return void
     */
    public function batchHandleSubTarget(array $targetIds, string $modelTarget, string $methodOnTarget, bool $is_EO_target, bool $is_EM_target)
    {
        if ($is_EM_target) {
            /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $modelInstance */
            $modelInstance = new $modelTarget;
            // Use whereIn for batch retrieval
            $targets = $modelInstance->whereIn('id', $targetIds)->get();

            foreach ($targets as $target) {
                if (! is_null($target)) {
                    $new_values = [];
                    // Check if the relationship exists and is iterable
                    if (isset($target->$methodOnTarget) && is_iterable($target->$methodOnTarget)) {
                        foreach ($target->$methodOnTarget as $temp) {
                            // Check if property exists to avoid undefined property notice
                            if (property_exists($temp, 'ref_id') && $temp->ref_id !== $this->getId()) {
                                $new_values[] = $temp->attributes ?? $temp;
                            } elseif (isset($temp['ref_id']) && $temp['ref_id'] !== $this->getId()) {
                                // Handle array case just in case
                                $new_values[] = $temp;
                            }
                        }
                    }
                    $target->$methodOnTarget = $new_values;
                    $target->save();
                }
            }
        }
    }

    /**
     * @param  string|int  $target_id
     * @param  string  $modelTarget
     * @param  string  $methodOnTarget
     * @param  bool  $is_EO_target
     * @param  bool  $is_EM_target
     * @return void
     *
     * @deprecated Use batchHandleSubTarget instead
     */
    public function handleSubTarget($target_id, $modelTarget, $methodOnTarget, $is_EO_target, $is_EM_target)
    {
        // Wrapper for backward compatibility
        $this->batchHandleSubTarget([$target_id], $modelTarget, $methodOnTarget, $is_EO_target, $is_EM_target);
    }

    /**
     * @param  array<int, object>  $objs
     * @param  string  $method
     * @param  array<string, mixed>  $relation
     * @param  bool  $is_HO
     * @return void
     */
    public function syncReferencedDelete($objs, $method, $relation, $is_HO)
    {
        $current = $this->$method;
        $currentIds = [];

        if ($is_HO) {
            if ($current) {
                $currentIds[] = $current->id;
            }
        } else {
            if ($current) {
                foreach ($current as $item) {
                    $currentIds[] = $item->id;
                }
            }
        }

        $incomingIds = [];
        if (! empty($objs)) {
            foreach ($objs as $obj) {
                if (isset($obj->id)) {
                    $incomingIds[] = $obj->id;
                } elseif (isset($obj->_id)) {
                    $incomingIds[] = $obj->_id;
                }
            }
        }

        $toDelete = array_diff($currentIds, $incomingIds);
        $modelClass = $relation['model'];

        if (! empty($toDelete)) {
            // For Mongo, we might need to check how destroy works
            // It usually takes IDs.
            $modelClass::destroy($toDelete);
        }
    }

    /**
     * @param  array<int, object>  $objs
     * @param  string  $method
     * @param  array<string, mixed>  $relation
     * @param  string  $event
     * @param  array<string, mixed>  $options
     * @return void
     */
    public function processReferencedRelationship(Request $request, $objs, $method, $relation, $event, array $options)
    {
        $modelClass = $relation['model'];
        $foreignKey = $relation['foreignKey'] ?? null;
        $localKey = $relation['localKey'] ?? 'id';

        foreach ($objs as $obj) {
            $attributes = (array) $obj;
            $id = $attributes['id'] ?? ($attributes['_id'] ?? null);

            /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $child */
            $child = new $modelClass;
            if ($id) {
                $found = $child->find($id);
                if ($found !== null) {
                    /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $child */
                    $child = $found;
                }
            }

            if ($child instanceof \OfflineAgency\MongoAutoSync\Http\Models\MDModel) {
                foreach ($attributes as $k => $v) {
                    if ($k !== 'id' && $k !== '_id') {
                        $child->$k = $v;
                    }
                }

                if ($foreignKey) {
                    // Assuming HasMany/HasOne where FK is on child
                    $child->$foreignKey = $this->$localKey;
                }

                $childRequest = new Request;
                $childRequest->merge($attributes);

                if ($id) {
                    $child->updateWithSync($childRequest);
                } else {
                    $child->storeWithSync($childRequest);
                }
            }
        }
    }

    /**
     * @param  string  $method
     * @param  bool  $is_HO
     * @return void
     */
    public function deleteReferencedObj($method, $is_HO)
    {
        $relationData = $this->$method;
        if ($is_HO) {
            if ($relationData) {
                $relationData->delete();
            }
        } else {
            if ($relationData) {
                foreach ($relationData as $item) {
                    $item->delete();
                }
            }
        }
    }

    /**
     * @param  object|null  $obj
     * @param  string  $type
     * @param  string  $model
     * @param  string  $method
     * @param  string  $event
     * @param  bool  $is_EO
     * @param  bool  $is_EM
     * @param  int  $i
     * @param  array<string, mixed>  $options
     * @return void
     *
     * @throws MongoAutoSyncException
     */
    private function processEmbedOnCurrentCollection(Request $request, $obj, $type, $model, $method, $event, $is_EO, $is_EM, $i, array $options)
    {
        // Init the embed one model
        /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $embedObj */
        $embedObj = new $model;

        $EOitems = $embedObj->getItems();
        // Current Obj Create
        foreach ($EOitems as $EOkey => $item) {
            if (! is_null($obj)) {
                $is_ML = SyncHelper::isML($item);
                $is_MD = SyncHelper::isMD($item);
                $this->checkPropertyExistence($obj, $EOkey, $method, $model);

                if ($is_ML) {
                    $embedObj->$EOkey = SyncHelper::ml([], $obj->$EOkey);
                } elseif ($EOkey == 'updated_at' || $EOkey == 'created_at') {
                    // These are dynamic properties managed by Laravel
                    $embedObj->setAttribute($EOkey, now());
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

        // else if($is_EM){//To be implemented}
        // else if($is_HM){//To be implemented}
        // else if($is_HO){//To be implemented}

        // Get counter for embeds many with level > 1
        $counter = SyncHelper::getCounterForRelationships($method, $is_EO, $is_EM, $i);
        // Check for another Level of Relationship
        $embedObj->processAllRelationships($request, $event, $method.'-', $counter, $options);

        if ($is_EO) {
            $this->$method = $embedObj->attributes;
        } else {
            $tempEM = $this->tempEM;
            if ($tempEM === null) {
                $tempEM = [];
            }
            $tempEM[] = $embedObj->attributes;
            $this->tempEM = $tempEM;
        }
    }

    /**
     * @param  string  $modelTarget
     * @param  object  $obj
     * @param  string  $methodOnTarget
     * @param  string  $modelOnTarget
     * @param  bool  $is_EO_target
     * @param  bool  $is_EM_target
     * @param  Request|null  $request
     * @param  string|null  $event
     * @param  array<string, mixed>  $options
     * @return void
     *
     * @throws MongoAutoSyncException
     */
    private function processEmbedOnTargetCollection($modelTarget, $obj, $methodOnTarget, $modelOnTarget, $is_EO_target, $is_EM_target, $request = null, $event = null, array $options = [])
    {
        if (config('laravel-mongo-auto-sync.use_background_sync')) {
            return;
        }

        /** @var \stdClass $obj */
        $modelToBeSync = $this->getModelTobeSync($modelTarget, $obj);
        if (! is_null($modelToBeSync)) {
            /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $modelToBeSync */
            /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $miniModel */
            $miniModel = $this->getEmbedModel($modelOnTarget);
            $modelToBeSync->updateRelationWithSync($miniModel, $methodOnTarget, $is_EO_target, $is_EM_target);
            // Sync target on level > 1
            if ($request && $event) {
                $modelToBeSync->processAllRelationships($request, $event, $methodOnTarget, $methodOnTarget.'-', $options);
            }
        }
    }
}
