<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use DateTime;
use Exception;
use Illuminate\Support\Arr;
use MongoDB\BSON\UTCDateTime;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRelationshipException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Extensions\MongoCollection;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;
use stdClass;

trait ModelAdditionalMethod
{
    /**
     * @var array<string, MDModel>|null
     */
    protected $mini_models;

    /**
     * @var array<string, array<int, string>>
     */
    protected static $mini_model_list_cache = [];

    /**
     * @param  array<int, \Illuminate\Database\Eloquent\Model>  $models
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public function newCollection(array $models = [])
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, static> $collection */
        $collection = new MongoCollection($models);

        return $collection;
    }

    /**
     * @return array<string, mixed>
     */
    public function getItems(): array
    {
        return $this->items ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getMongoRelation(): array
    {
        if (! empty($this->mongoRelation)) {
            return $this->mongoRelation;
        } else {
            return [];
        }
    }

    /**
     * @return mixed
     *
     * @throws InvalidRequestException
     */
    public function getRandomRow(int $numberOfRandomRow = 0)
    {
        $totalRow = $this->count();
        if ($numberOfRandomRow <= 0) {
            throw new InvalidRequestException('Invalid # of random record requested');
        } elseif ($numberOfRandomRow > $totalRow) {
            throw new InvalidRequestException('You have requested a number of record bigger than the count collection record ( '.$totalRow.')');
        }

        $raw = $this->raw(function ($collection) use ($numberOfRandomRow) {
            return $collection->aggregate([
                ['$sample' => ['size' => $numberOfRandomRow]],
            ]);
        });

        /** @var \Traversable<int|string, mixed> $raw */
        $results = $this->hydrate(iterator_to_array($raw));

        if ($numberOfRandomRow == 1) {
            return $results->first();
        } else {
            return $results;
        }
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function setMiniModels()
    {
        $miniModelList = $this->getUniqueMiniModelList();
        $this->mini_models = $this->populateMiniModels($miniModelList);
    }

    /**
     * @return array<string, MDModel>
     */
    public function getMiniModels()
    {
        return $this->mini_models ?? [];
    }

    /**
     * @return array<int, string>
     *
     * @throws InvalidConfigurationException
     */
    public function getUniqueMiniModelList()
    {
        $cacheKey = get_class($this);

        if (isset(self::$mini_model_list_cache[$cacheKey])) {
            $result = self::$mini_model_list_cache[$cacheKey];
            // Restore partial generated request from cache or re-generate it?
            // The method sets partial generated request as a side effect.
            // We need to be careful. The side effect depends on the instance data ($this->getObjWithRefId which depends on attributes).
            // Actually getUniqueMiniModelList calls getObjWithRefId.
            // Wait, getObjWithRefId depends on $this attributes (ref_id).
            // So we CANNOT cache the result across instances blindly if the result depends on instance data.

            // Let's check getUniqueMiniModelList implementation again.
            // It calls getObjWithRefId.
            // getObjWithRefId calls getObjValueToBeSaved -> getDbValue -> $this->$key.
            // So it DOES depend on instance data.
            // So static caching is NOT safe if it depends on instance state.

            // However, the LIST of mini models (classes) should be constant for the Model Class.
            // But the method ALSO sets $this->setPartialGeneratedRequest($embedded_object);
            // $embedded_object depends on instance data.

            // So I can cache the 'models' list but I still need to iterate to build $embedded_object.
            // The previous implementation was:
            /*
            foreach ($relationships as $method => $relationship) {
                $hasTarget = SyncHelper::hasTarget($relationship);
                if ($hasTarget) {
                    $relationshipsContainsTarget = Arr::has($relationship, 'modelOnTarget');
                    if ($relationshipsContainsTarget) {
                        $models[] = Arr::get($relationship, 'modelOnTarget');
                        $embedded_object[$method] = $this->getObjWithRefId($method, $relationship);
                    } else {
                        throw new Exception...
                    }
                }
            }
            $this->setPartialGeneratedRequest($embedded_object);
            return collect($models)->unique()->toArray();
            */

            // I can optimize by caching which relationships have targets and what their modelOnTarget is.
            // But the 'embedded_object' part needs to run for every instance.
        }

        $relationships = $this->getMongoRelation();

        $models = [];
        $embedded_object = [];

        foreach ($relationships as $method => $relationship) {
            $hasTarget = SyncHelper::hasTarget($relationship);
            if ($hasTarget) {
                $relationshipsContainsTarget = Arr::has($relationship, 'modelOnTarget');
                if ($relationshipsContainsTarget) {
                    $models[] = Arr::get($relationship, 'modelOnTarget');
                    $embedded_object[$method] = $this->getObjWithRefId($method, $relationship);
                } else {
                    throw new InvalidConfigurationException('modelOnTarget not found on relationship '.$method.' array. Check your Model configuration '.get_class($this));
                }
            }
        }
        $this->setPartialGeneratedRequest($embedded_object);

        return collect($models)->unique()->toArray();
    }

    /**
     * @param  array<int, string>  $miniModelList
     * @return array<string, MDModel>
     *
     * @throws Exception
     */
    public function populateMiniModels(array $miniModelList)
    {
        $miniModels = [];
        foreach ($miniModelList as $miniModel) {
            $miniModels[$miniModel] = $this->getFreshMiniModel($miniModel);
        }

        return $miniModels;
    }

    /**
     * @return MDModel
     *
     * @throws Exception
     */
    public function getFreshMiniModel(string $mini_model_path)
    {
        $embededModel = $this->getModelInstanceFromPath($mini_model_path);
        $items = $embededModel->getItems();
        foreach ($items as $key => $item) {
            $embededModel->$key = $this->castValueToBeSaved($key, $item, $mini_model_path);
        }

        return $embededModel;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|UTCDateTime|mixed|null
     *
     * @throws Exception
     */
    public function castValueToBeSaved(string $key, $item, string $mini_model_path)
    {
        $is_ML = SyncHelper::isML($item);
        $is_MD = SyncHelper::isMD($item);
        $is_array = $this->isArray($item);
        $is_carbon_date = $this->isCarbonDate($item);

        $value = $this->getObjValueToBeSaved($key, $mini_model_path);
        if ($is_ML) {
            return is_array($value) ? $value : SyncHelper::ml([], (string) $value);
        } elseif ($is_MD) {
            if (empty($value)) {
                return null;
            } else {
                if ($value instanceof UTCDateTime) {
                    return $value;
                }
                if ($value instanceof \DateTimeInterface) {
                    return new UTCDateTime($value);
                }

                return new UTCDateTime(new DateTime($value));
            }
        } elseif ($is_carbon_date) {
            if ($value instanceof UTCDateTime) {
                return $value;
            }
            if ($value instanceof \DateTimeInterface) {
                return new UTCDateTime($value);
            }
            if (is_string($value) && ! is_numeric($value)) {
                return new UTCDateTime(new DateTime($value));
            }

            return new UTCDateTime($value);
        } elseif ($is_array) {
            return is_null($value) ? [] : (is_array($value) ? $value : $value->getAttributes());
        } else {
            return $value;
        }
    }

    /**
     * @return MDModel
     */
    public function getModelInstanceFromPath(string $mini_model_path)
    {
        /** @var MDModel $instance */
        $instance = new $mini_model_path;

        return $instance;
    }

    /**
     * @param  bool  $rewrite_ref_id_key
     * @return mixed
     */
    public function getObjValueToBeSaved(string $key, string $mini_model_path, $rewrite_ref_id_key = true)
    {
        $key = $key === 'ref_id' && $rewrite_ref_id_key ? '_id' : $key;
        $target_additional_data = $this->getTargetAdditionalData();
        $request = $this->getRequest();

        $db_value = $this->getDbValue($key);

        if ($request === null) {
            return $db_value;
        }

        return Arr::has($target_additional_data, $mini_model_path.'.'.$key) ? Arr::get($target_additional_data, $mini_model_path.'.'.$key) : // Search on target_additional_data [] 4th parameter of updateWithSync() / storeWithSync()
            ($request->has($key) ? $request->input($key) : $db_value); // Search on Main Request 1st parameter of updateWithSync() / storeWithSync() or directly on database
        // TODO: Add default value from Item Model
    }

    /**
     * @return mixed
     */
    private function getDbValue(string $key)
    {
        return $this->$key;
    }

    /**
     * @return MDModel
     *
     * @throws InvalidConfigurationException
     */
    public function getEmbedModel(string $key)
    {
        $embedModels = $this->getMiniModels();

        if (Arr::has($embedModels, $key)) {
            /** @var MDModel $model */
            $model = Arr::get($embedModels, $key);

            return $model;
        } else {
            throw new InvalidConfigurationException('I cannot find an embedded model with key: '.$key.'. Check on your model configuration');
        }
    }

    /**
     * @param  array<string, mixed>  $relationship
     * @return string
     *
     * @throws InvalidRelationshipException
     */
    public function getObjWithRefId(string $method, array $relationship)
    {
        $objs = [];
        $type = $relationship['type'];

        $is_EO = SyncHelper::is_EO($type);
        $is_EM = SyncHelper::is_EM($type);

        if ($is_EO) {
            $obj = new stdClass;
            $obj->ref_id = $this->getObjValueToBeSaved($method, '', false);
            $objs[] = $obj;
        } elseif ($is_EM) {
            $methodValue = $this->$method;
            if (! is_null($methodValue) && is_iterable($methodValue)) {
                /** @var iterable<int, object> $methodValue */
                foreach ($methodValue as $value) {
                    $obj = new stdClass;
                    /** @var object $value */
                    $obj->ref_id = $value->ref_id ?? null;
                    $objs[] = $obj;
                }
            }
        } else {
            throw new InvalidRelationshipException('Relationship '.$method.' type '.$type.' is not valid! Possible values are: EmbedsMany and EmbedsOne');
        }

        $result = json_encode($objs);
        if ($result === false) {
            return '';
        }

        return $result;
    }
}
