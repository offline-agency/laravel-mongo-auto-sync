<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use DateTime;
use Exception;
use Illuminate\Support\Arr;
use MongoDB\BSON\UTCDateTime;
use OfflineAgency\MongoAutoSync\Extensions\MongoCollection;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;
use stdClass;

trait ModelAdditionalMethod
{
    protected $mini_models;

    public function newCollection(array $models = [])
    {
        return new MongoCollection($models);
    }

    /**
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return array
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
     * @return array
     */
    public function getPageMetaTag()
    {
        $collection_name = $this->collection;
        $meta_content = [];
        $meta_value = [];
        $meta_key = [];
        $title = '';
        $description = '';
        $meta_description = '';
        $fb_id = env('FB_ID');
        $img_url = '';
        $meta = [];

        switch ($collection_name) {
            case $collection_name == 'article':
                $meta_content = [
                    'article',
                    $this->author,
                    $this->updated_at,
                    $this->updated_at,
                    '666',
                    '920',
                    'secure_image.png',
                ];
                $meta_value = [
                    'og:type',
                    'article:author',
                    'article:modified_time',
                    'article:published_time',
                    'og:image:height',
                    'og:image:width',
                    'og:image:secure_url',
                ];
                $meta_key = [
                    'property',
                    'property',
                    'property',
                    'property',
                    'property',
                    'property',
                    'property',
                ];
                $title = getTranslatedContent($this->title).' | ';
                $description = getTranslatedContent($this->excerption);
                $meta_description = '';
                $img_url = getFullUrlImgByKey($this->img_evidence_text);
                break;

            case $collection_name == 'course':
                $meta_content = ['article'];
                $meta_value = ['og:type'];
                $meta_key = ['property'];
                $title = getTranslatedContent($this->title).' | ';
                $description = getTranslatedContent($this->shortDescription);
                $meta_description = getTranslatedContent($this->shortDescription);
                $img_url = getFullUrlImgByKey($this->img_evidence_text);

                break;

            case $collection_name == 'event':
                $meta_content = ['product'];
                $meta_value = [];
                $meta_key = [];
                $title = getTranslatedContent($this->title).' | ';
                $meta_description = getTranslatedContent($this->shortDescription);
                $img_url = getFullUrlImgByKey($this->img_evidence_text);

                break;

            case $collection_name == 'page':
                $meta_content = [];
                $meta_value = [];
                $meta_key = [];
                $title = getTranslatedContent($this->title).' | ';
                $meta_description = getTranslatedContent($this->description);
                $img_url = '';
                break;

        }

        //common meta
        $obj_content = [
            $meta_description,
            env('APP_LOCALE'),
            $title.getSiteGeneralValueByKey('company_name'),
            $description,
            url()->current(),
            $img_url,
            $img_url,
            getSiteGeneralValueByKey('company_name'),
            $fb_id,
            '@informaz',
            '@informaz',
            $title.getSiteGeneralValueByKey('company_name'),
            $description,
            $img_url,
            'summary',
        ];
        $obj_value = [
            'description',
            'og:locale',
            'og:title',
            'og:description',
            'og:url',
            'og:image',
            'og:image:secure_url',
            'og:site_name',
            'fb:app_id',
            'twitter:creator',
            'twitter:site',
            'twitter:title',
            'twitter:description',
            'twitter:image',
            'twitter:card',
        ];
        $obj_key = [
            'name',
            'property',
            'property',
            'property',
            'property',
            'property',
            'property',
            'property',
            'property',
            'name',
            'name',
            'name',
            'name',
            'name',
            'name',
        ];

        $obj_key = array_merge($obj_key, $meta_key);
        $obj_value = array_merge($obj_value, $meta_value);
        $obj_content = array_merge($obj_content, $meta_content);

        for ($i = 0; $i < count($obj_key); $i++) {
            $obj = [
                'key'     => $obj_key[$i],
                'value'   => $obj_value[$i],
                'content' => $obj_content[$i],
            ];
            //generate new sitegeneral to match obj_key number
            $meta[] = $obj;
        }

        return $meta;
    }

    /**
     * @param int $numberOfRandomRow
     *
     * @return mixed
     * @throws Exception
     */
    public function getRandomRow(int $numberOfRandomRow = 0)
    {
        $totalRow = $this->count();
        if ($numberOfRandomRow == 0 || $numberOfRandomRow < 0) {
            throw new Exception('Invalid # of random record requested');
        } elseif ($numberOfRandomRow > $totalRow) {
            throw new Exception('You have requested a number of record bigger than the count collection record ( '.$totalRow.')');
        } elseif ($numberOfRandomRow == 1) {
            return $this->skip(rand(0, $totalRow - 1))->take($numberOfRandomRow)->first();
        } else {
            return $this->skip(rand(0, $totalRow - 1))->take($numberOfRandomRow);
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    public function setMiniModels()
    {
        $miniModelList = $this->getUniqueMiniModelList();
        $this->mini_models = $this->populateMiniModels($miniModelList);
    }

    /**
     * @return array
     */
    public function getMiniModels()
    {
        return $this->mini_models;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getUniqueMiniModelList()
    {
        $relationships = $this->getMongoRelation();

        $models = [];
        $embedded_object = [];

        foreach ($relationships as $method => $relationship) {
            $hasTarget = hasTarget($relationship);
            if ($hasTarget){
                $relationshipsContainsTarget = Arr::has($relationship, 'modelOnTarget');
                if ($relationshipsContainsTarget) {
                    $models[] = Arr::get($relationship, 'modelOnTarget');
                    $embedded_object[$method] = $this->getObjWithRefId($method, $relationship);
                }else{
                    throw new Exception('modelOnTarget not found on relationship ' . $method . ' array. Check your Model configuration ' . get_class($this));
                }
            }
        }
        $this->setPartialGeneratedRequest($embedded_object);

        return collect($models)->unique()->toArray();
    }

    /**
     * @param array $miniModelList
     * @return mixed
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
     * @param string $mini_model_path
     * @return MDModel
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
     * @param string $key
     * @param $item
     * @param string $mini_model_path
     * @return array|mixed|UTCDateTime|null
     * @throws Exception
     */
    public function castValueToBeSaved(string $key, $item, string $mini_model_path)
    {
        $is_ML = isML($item);
        $is_MD = isMD($item);
        $is_array = $this->isArray($item);
        $is_carbon_date = $this->isCarbonDate($item);

        $value = $this->getObjValueToBeSaved($key, $mini_model_path);
        if ($is_ML) {
            return is_array($value) ? $value : ml([], $value);
        } elseif ($is_MD) {
            if ($value == '' || is_null($value)) {
                return;
            } else {
                return new UTCDateTime(new DateTime($value));
            }
        } elseif ($is_carbon_date) {
            return new UTCDateTime($value);
        } elseif ($is_array) {
            return is_array($value) ? $value : $value->getAttributes();
        } else {
            return $value;
        }
    }

    /**
     * @param string $mini_model_path
     * @return MDModel
     */
    public function getModelInstanceFromPath(string $mini_model_path)
    {
        return new $mini_model_path;
    }

    /**
     * @param string $key
     * @param string $mini_model_path
     * @param bool $rewrite_ref_id_key
     * @return mixed
     */
    public function getObjValueToBeSaved(string $key, string $mini_model_path, $rewrite_ref_id_key = true)
    {
        $key = $key === 'ref_id' && $rewrite_ref_id_key ? '_id' : $key;
        $target_additional_data = $this->getTargetAdditionalData();
        $request = $this->getRequest();

        $db_value = $this->getDbValue($key);

        return Arr::has($target_additional_data, $mini_model_path.'.'.$key) ? Arr::get($target_additional_data, $mini_model_path.'.'.$key) : // Search on target_additional_data [] 4th parameter of updateWithSync() / storeWithSync()
            ($request->has($key) ? $request->input($key) : $db_value); // Search on Main Request 1st parameter of updateWithSync() / storeWithSync() or directly on database
        //TODO: Add default value from Item Model
    }

    /**
     * @param string $key
     * @return mixed
     */
    private function getDbValue( string $key)
    {
        return $this->$key;
    }

    /**
     * @param string $key
     * @return array
     * @throws Exception
     */
    public function getEmbedModel(string $key)
    {
        $embedModels = $this->getMiniModels();

        if (Arr::has($embedModels, $key)) {
            return Arr::get($embedModels, $key);
        } else {
            throw new Exception('I cannot find an embedded model with key: '.$key.'. Check on your model configuration');
        }
    }

    /**
     * @param string $method
     * @param array $relationship
     * @return false|string
     * @throws Exception
     */
    public function getObjWithRefId(string $method, array $relationship)
    {
        $objs = [];
        $type = $relationship['type'];

        $is_EO = is_EO($type);
        $is_EM = is_EM($type);

        if ($is_EO) {
            $obj = new stdClass;
            $obj->ref_id = $this->getObjValueToBeSaved($method, '', false);
            $objs[] = $obj;
        } elseif ($is_EM) {

            if (!is_null($this->$method) > 0){
                foreach ($this->$method as $value) {
                    $obj = new stdClass;
                    $obj->ref_id = $value->ref_id;
                    $objs[] = $obj;
                }
            }
        } else {
            throw new Exception('Relationship '.$method.' type '.$type.' is not valid! Possible values are: EmbedsMany and EmbedsOne');
        }

        return json_encode($objs);
    }
}
