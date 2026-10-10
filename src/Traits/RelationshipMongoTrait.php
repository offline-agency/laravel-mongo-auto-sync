<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use LogicException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Connection as MongoConnection;
use MongoDB\Laravel\Query\Builder as MongoQueryBuilder;
use OfflineAgency\MongoAutoSync\Context\SyncContext;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\MongoAutoSyncException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;
use stdClass;

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
            $type = SyncHelper::toString($relation['type']);
            $model = SyncHelper::toString($relation['model']);
            $hasTarget = SyncHelper::hasTarget($relation);
            if ($hasTarget) {
                $modelTarget = SyncHelper::toString($relation['modelTarget']);
                $methodOnTarget = SyncHelper::toString($relation['methodOnTarget']);
                $modelOnTarget = SyncHelper::toString($relation['modelOnTarget']);
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
                SyncHelper::assertSafeRefIds($key, $value, SyncHelper::idKeysFor($type));
                $objs = SyncHelper::payloadDocuments($key, $value);
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

                    // a relationship absent from the request is only propagated to its targets: keep the stored copy
                    if ($is_EM && $is_embeds_has_to_be_updated) {
                        $this->$method = $this->tempEM;
                    }
                } elseif ($is_embeds_has_to_be_updated) {
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
     * Upsert the mini model of a source on this target with atomic updates: concurrent jobs writing other
     * sources on the same target cannot overwrite each other, and the same source is never duplicated.
     * The updates run through the laravel-mongodb query builder, so they join the connection's transaction.
     *
     * @param  object  $mini_model
     * @param  bool  $is_EO_target
     * @param  bool  $is_EM_target
     * @return void
     */
    public function updateRelationWithSync($mini_model, string $method_on_target, $is_EO_target, $is_EM_target)
    {
        /** @var MDModel $mini_model */
        $mini_attributes = $mini_model->getAttributes();
        $touch = $this->touchedTimestamp();

        if ($is_EM_target) {
            $ref_ids = SyncHelper::idVariants($mini_attributes['ref_id'] ?? null);
            // a null field cannot be pushed to
            $this->targetQuery()->whereNull($method_on_target)->update(['$set' => [$method_on_target => []]]);
            if ($ref_ids !== []) {
                $this->targetQuery()->update(['$pull' => [$method_on_target => ['ref_id' => ['$in' => $ref_ids]]]]);
            }
            $update = ['$push' => [$method_on_target => $mini_attributes]];
            if ($touch !== []) {
                $update['$set'] = $touch;
            }
            // skip the push when a concurrent job for the same source already added it
            $this->targetQuery()->whereNotIn($method_on_target.'.ref_id', $ref_ids)->update($update);
        } else {
            $this->targetQuery()->update(['$set' => [$method_on_target => $mini_attributes] + $touch]);
        }

        $this->reloadEmbedded($method_on_target);
    }

    /**
     * The updated_at value to $set with an atomic target write (none without timestamps or updated_at column).
     *
     * @return array<string, mixed>
     */
    private function touchedTimestamp(): array
    {
        $column = $this->getUpdatedAtColumn();
        if (! $this->usesTimestamps() || $column === null) {
            return [];
        }

        return [$column => $this->fromDateTime($this->freshTimestamp())];
    }

    /**
     * laravel-mongodb query on this document only (inherits session, maxTimeMS and read preference).
     */
    private function targetQuery(): MongoQueryBuilder
    {
        $connection = $this->getConnection();
        $query = $connection instanceof MongoConnection ? $this->newQueryWithoutScopes()->getQuery() : null;
        if (! $query instanceof MongoQueryBuilder) {
            throw new LogicException('Target sync requires a MongoDB connection, got '.get_class($connection));
        }

        // raw filter: whereIn('_id') would convert a 24-hex string id to an ObjectId and miss documents stored with a string _id
        // @phpstan-ignore argument.type (laravel-mongodb's whereRaw() accepts a filter array; the phpdoc is inherited from Illuminate)
        return $query->whereRaw(['_id' => ['$in' => SyncHelper::idVariants(SyncHelper::toString($this->getKey()))]]);
    }

    /**
     * Refresh the in-memory value of $method after an atomic update.
     */
    private function reloadEmbedded(string $method): void
    {
        $fresh = $this->newQueryWithoutScopes()->find($this->getKey());
        if (! $fresh instanceof MDModel) {
            return;
        }

        $this->setRawAttributes(array_merge($this->getAttributes(), [$method => $fresh->getAttributes()[$method] ?? null]), true);
        $this->unsetRelation($method);
    }

    /**
     * Remove the mini model of the source $ref_id from this target (atomic update).
     *
     * @return void
     */
    public function removeRelationWithSync(string $method_on_target, mixed $ref_id, bool $is_EO_target, bool $is_EM_target)
    {
        $ref_ids = SyncHelper::idVariants($ref_id);
        if ($ref_ids === [] || (! $is_EM_target && ! $is_EO_target)) {
            return;
        }

        $touch = $this->touchedTimestamp();

        if ($is_EM_target) {
            $update = ['$pull' => [$method_on_target => ['ref_id' => ['$in' => $ref_ids]]]];
            if ($touch !== []) {
                $update['$set'] = $touch;
            }
        } else {
            // only clear the EmbedsOne when it still points at this source
            $update = ['$set' => [$method_on_target => null] + $touch];
        }

        // only documents that still reference the source are touched
        $this->targetQuery()->whereIn($method_on_target.'.ref_id', $ref_ids)->update($update);
        $this->reloadEmbedded($method_on_target);
    }

    /**
     * @param  object|null  $obj
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

        $targetIds = SyncHelper::refIds($this->$method);

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
        if (! $is_EM_target) {
            return; // the synchronous path only cleans EmbedsMany targets (unchanged since 3.x)
        }

        /** @var MDModel $modelInstance */
        $modelInstance = SyncContext::targetModel(static::class, SyncContext::connectionName($this), $modelTarget);
        $targets = $modelInstance->newQuery()->whereIn('id', $targetIds)->get();

        foreach ($targets as $target) {
            /** @var MDModel $target */
            $target->removeRelationWithSync($methodOnTarget, $this->getId(), $is_EO_target, $is_EM_target);
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
     * @param  array<int, object|null>  $objs
     * @param  string  $method
     * @param  array<string, mixed>  $relation
     * @param  bool  $is_HO
     * @return void
     */
    public function syncReferencedDelete($objs, $method, $relation, $is_HO)
    {
        $currentIds = [];
        foreach ($this->referencedModels($method, $is_HO) as $item) {
            $currentIds[] = SyncHelper::toString($item->getAttribute('id'));
        }

        $incomingIds = [];
        foreach ($objs as $obj) {
            if (isset($obj->id)) {
                $incomingIds[] = SyncHelper::toString($obj->id);
            } elseif (isset($obj->_id)) {
                $incomingIds[] = SyncHelper::toString($obj->_id);
            }
        }

        $toDelete = array_diff($currentIds, $incomingIds);
        $modelClass = $relation['model'];
        throw_unless(is_string($modelClass) && is_subclass_of($modelClass, Model::class), new InvalidConfigurationException("The model of relationship '{$method}' must be an Eloquent model class."));

        if (! empty($toDelete)) {
            // For Mongo, we might need to check how destroy works
            // It usually takes IDs.
            $modelClass::destroy($toDelete);
        }
    }

    /**
     * @param  array<int, object|null>  $objs
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

            /** @var MDModel $child */
            $child = new $modelClass;
            if ($id) {
                $found = $child->find($id);
                if ($found !== null) {
                    /** @var MDModel $child */
                    $child = $found;
                }
            }

            if ($child instanceof MDModel) {
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
        foreach ($this->referencedModels($method, $is_HO) as $item) {
            $item->delete();
        }
    }

    /**
     * The models currently held by a referenced relationship: the related model (HasOne) or the related
     * collection (HasMany); anything else holds none.
     *
     * @return list<Model>
     */
    private function referencedModels(string $method, bool $is_HO): array
    {
        $relationData = $this->$method;
        $items = $is_HO ? [$relationData] : (is_iterable($relationData) ? $relationData : []);

        $models = [];
        foreach ($items as $item) {
            if ($item instanceof Model) {
                $models[] = $item;
            }
        }

        return $models;
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
        /** @var MDModel $embedObj */
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
                        $embedObj->$EOkey = new UTCDateTime(new DateTime(SyncHelper::toString($obj->$EOkey)));
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
     * @param  object|null  $obj
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

        // nothing to sync for an empty EmbedsOne (null, or the all-null mini model of a missing relation)
        if (! $obj instanceof stdClass || (property_exists($obj, 'ref_id') && $obj->ref_id === null)) {
            return;
        }

        $modelToBeSync = $this->getModelTobeSync($modelTarget, $obj);
        if (! is_null($modelToBeSync)) {
            /** @var MDModel $modelToBeSync */
            /** @var MDModel $miniModel */
            $miniModel = $this->getEmbedModel($modelOnTarget);
            $modelToBeSync->updateRelationWithSync($miniModel, $methodOnTarget, $is_EO_target, $is_EM_target);
            // TODO: sync targets on level > 1 is not implemented (the previous call built the request key
            // "<method><method><method>-" and threw InvalidRequestException on every target sync).
        }
    }
}
