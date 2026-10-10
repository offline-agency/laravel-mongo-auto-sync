<?php

namespace OfflineAgency\MongoAutoSync\Traits {

    use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;

    trait ModelAdditionalMethodExtracted
    {
        /**
         * @return array
         *
         * @deprecated This method contains hardcoded business logic and will be removed in future versions.
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
            $fb_id = config('laravel-mongo-auto-sync.fb_id');
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
                    $title = SyncHelper::getTranslatedContent($this->title).' | ';
                    $description = SyncHelper::getTranslatedContent($this->excerption);
                    $meta_description = '';
                    $img_url = getFullUrlImgByKey($this->img_evidence_text);
                    break;

                case $collection_name == 'course':
                    $meta_content = ['article'];
                    $meta_value = ['og:type'];
                    $meta_key = ['property'];
                    $title = SyncHelper::getTranslatedContent($this->title).' | ';
                    $description = SyncHelper::getTranslatedContent($this->shortDescription);
                    $meta_description = SyncHelper::getTranslatedContent($this->shortDescription);
                    $img_url = getFullUrlImgByKey($this->img_evidence_text);

                    break;

                case $collection_name == 'event':
                    $meta_content = ['product'];
                    $meta_value = [];
                    $meta_key = [];
                    $title = SyncHelper::getTranslatedContent($this->title).' | ';
                    $meta_description = SyncHelper::getTranslatedContent($this->shortDescription);
                    $img_url = getFullUrlImgByKey($this->img_evidence_text);

                    break;

                case $collection_name == 'page':
                    $meta_content = [];
                    $meta_value = [];
                    $meta_key = [];
                    $title = SyncHelper::getTranslatedContent($this->title).' | ';
                    $meta_description = SyncHelper::getTranslatedContent($this->description);
                    $img_url = '';
                    break;

            }

            // common meta
            $obj_content = [
                $meta_description,
                config('app.locale'),
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
                    'key' => $obj_key[$i],
                    'value' => $obj_value[$i],
                    'content' => $obj_content[$i],
                ];
                // generate new sitegeneral to match obj_key number
                $meta[] = $obj;
            }

            return $meta;
        }
    }
}

namespace OfflineAgency\MongoAutoSync\Extensions {

    use Illuminate\Database\Eloquent\ModelNotFoundException;
    use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;

    trait MongoCollectionExtracted
    {
        // Method to retrieve a collection by slug, very useful for frontend
        public function getBySlugAndStatus($category = null, $myslug = null)
        {
            $cl = SyncHelper::cl();

            $out = $this->filter(function ($col) use ($category, $myslug, $cl) {
                if ($col->slug[$cl] == $myslug && $col->status == 'published' && $col->primarycategory->slug[$cl] == $category) {
                    return true;
                } else {
                    return false;
                }
            })->first();
            if (! $out) {// Handler 404 Object Not Found
                $first = $this->first();
                $obj_name = $first ? get_class($first) : 'Unknown Model';
                throw (new ModelNotFoundException)->setModel($obj_name);
            } else {
                return $out;
            }
        }

        /**
         * @param  null  $myslug
         * @return mixed
         */
        public function getBySlug($myslug = null)
        {
            $cl = SyncHelper::cl();
            $out = $this->filter(function ($col) use ($myslug, $cl) {
                if ($col->slug[$cl] == $myslug) {
                    return true;
                }
            })->first();
            if (! $out) {// Handler 404 Object Not Found
                $first = $this->first();
                $obj_name = $first ? get_class($first) : 'Unknown Model';
                throw (new ModelNotFoundException)->setModel($obj_name);
            } else {
                return $out;
            }
        }

        // Method to retrieve only not deleted item of a collection - Check on is_deleted custom property added on MDMODEL ovverriding init, delete

        /**
         * @return MongoCollection
         */
        public function getNotDeleted()
        {
            return $this->filter(function ($col) {
                return ! $col->is_deleted;
            });
        }

        // Method to retrieve only published item of a collection - Check on status entry

        /**
         * @return MongoCollection
         */
        public function getPublished()
        {
            return $this->filter(function ($col) {
                if ($col->status == 'published') {
                    return true;
                } else {
                    return false;
                }
            });
        }

        // Method to retrieve only public item of a collection - Check on status entry

        /**
         * @return MongoCollection
         */
        public function getPublic()
        {
            return $this->filter(function ($col) {
                if ($col->visibility === 'public') {
                    return true;
                } else {
                    return false;
                }
            });
        }

        /**
         * @return MongoCollection
         */
        public function getActive()
        {
            return $this->filter(function ($col) {
                return $col->is_active;
            });
        }

        /**
         * @return mixed
         */
        public function findByAID(string $aid)
        {
            return $this->filter(function ($col) use ($aid) {
                return $col->autoincrement_id == $aid;
            })->first();
        }

        /**
         * @return bool
         */
        public function hasPermission($id)
        {
            if (is_null($id)) {
                return false;
            }

            $out = $this->filter(function ($col) use ($id) {
                if ($col->ref_id == $id) {
                    return true;
                }
            });

            if ($out->count() > 0) {
                return true;
            } else {
                return false;
            }
        }

        /**
         * @return bool
         */
        public function hasRole($name)
        {
            if (is_null($name)) {
                return false;
            }

            $out = $this->filter(function ($col) use ($name) {
                if ($col->name == $name) {
                    return true;
                }
            });

            if ($out->count() > 0) {
                return true;
            } else {
                return false;
            }
        }

        /**
         * @return bool
         */
        public function checkPermission($name)
        {
            if (is_null($name)) {
                return false;
            }

            $out = $this->filter(function ($col) use ($name) {
                if ($col->name == $name) {
                    return true;
                }
            });

            if ($out->count() > 0) {
                return true;
            } else {
                return false;
            }
        }
    }
}

namespace OfflineAgency\MongoAutoSync\Helpers {

    use MongoDB\Laravel\Eloquent\Model;

    class SyncHelperExtracted
    {
        /**
         * @param  string|null  $request
         * @return string
         */
        public static function getPrimaryRequest($request)
        {
            if (! is_null($request)) {
                $arr = [];
                $categorylistdataJson = $request;
                $categorylistdataArr = json_decode($categorylistdataJson);
                if (empty($categorylistdataArr)) {
                    return '';
                } else {
                    // extract first array  and create the primary category
                    $primarycategory = ($categorylistdataArr[0]);
                    $arr[] = $primarycategory;

                    return json_encode($arr);
                }
            } else {
                return '';
            }
        }

        /**
         * @return int
         */
        public static function getAID(Model $model)
        {
            // Get Last Obj
            /** @var mixed $obj */
            $obj = $model->orderBy('created_at', 'desc')->first();

            return is_null($obj) ? 1 : $obj->autoincrement_id + 1;
        }

        /**
         * @param  array  $array
         * @return string
         */
        public static function processList($array)
        {
            $final = [];
            $n = count($array);
            for ($i = 0; $i < $n; $i++) {
                $obj = [];
                if ($array[$i] !== null) {
                    $obj = ['label' => $array[$i], 'key' => $i];
                    $final[] = $obj;
                } else {
                    $final[] = $obj;
                    array_pop($final);
                }
            }

            return json_encode($final);
        }
    }
}

namespace {

    use MongoDB\Laravel\Eloquent\Model;
    use OfflineAgency\MongoAutoSync\Helpers\SyncHelperExtracted;

    if (! function_exists('getPrimaryRequest')) {
        /**
         * @param  string  $request
         * @return string
         */
        function getPrimaryRequest($request)
        {
            return SyncHelperExtracted::getPrimaryRequest($request);
        }
    }

    if (! function_exists('getAID')) {
        /**
         * @return string
         */
        function getAID(Model $model)
        {
            return SyncHelperExtracted::getAID($model);
        }
    }

    if (! function_exists('processList')) {
        /**
         * @param  array  $array
         * @return string
         */
        function processList($array)
        {
            return SyncHelperExtracted::processList($array);
        }
    }
}
