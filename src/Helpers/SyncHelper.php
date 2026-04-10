<?php

namespace OfflineAgency\MongoAutoSync\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use stdClass;

class SyncHelper
{
    /**
     * @param  array<mixed>|null  $mlCollection
     * @return string
     */
    public static function getTranslatedContent($mlCollection)
    {
        $cl = Config::get('app.locale');

        if (is_array($mlCollection) && (array_key_exists('en_EN', $mlCollection) || array_key_exists('it_IT', $mlCollection))) {
            return $mlCollection[$cl] ?? '';
        } else {
            return '';
        }
    }

    /**
     * @return string current Lang
     */
    public static function cl()
    {
        return (string) Config::get('app.locale');
    }

    /**
     * @param  array<mixed>|null  $destination
     * @param  string  $input
     * @return array<mixed> ready to be saved
     */
    public static function ml($destination, $input)
    {
        if (is_null($destination)) {
            $destination = [];
        }

        return array_merge($destination, [self::cl() => $input]);
    }

    /**
     * @param  mixed  $value
     * @return bool
     */
    public static function isML($value)
    {
        if (! is_array($value)) {
            return false;
        }

        if (array_key_exists('is-ml', $value)) {
            return (bool) $value['is-ml'];
        } else {
            return false;
        }
    }

    /**
     * @param  mixed  $value
     * @return bool
     */
    public static function isMD($value)
    {
        if (is_array($value) && array_key_exists('is-md', $value)) {
            return (bool) $value['is-md'];
        } else {
            return false;
        }
    }

    /**
     * @param  mixed  $value
     * @return bool
     */
    public static function is_EM($value)
    {
        return $value === 'EmbedsMany';
    }

    /**
     * @param  mixed  $value
     * @return bool
     */
    public static function is_EO($value)
    {
        return $value === 'EmbedsOne';
    }

    /**
     * @param  mixed  $value
     * @return bool
     */
    public static function is_HM($value)
    {
        return $value === 'HasMany';
    }

    /**
     * @param  mixed  $value
     * @return bool
     */
    public static function is_HO($value)
    {
        return $value === 'HasOne';
    }

    /**
     * @param  mixed  $value
     * @return bool
     */
    public static function isEditable($value)
    {
        if (is_array($value) && array_key_exists('is-editable', $value)) {
            return (bool) $value['is-editable'];
        } else {
            return true;
        }
    }

    /**
     * @param  mixed  $value
     * @return bool
     */
    public static function hasTarget($value)
    {
        if (is_array($value) && array_key_exists('has-target', $value)) {
            return (bool) $value['has-target'];
        } else {
            return true;
        }
    }

    /**
     * @param  mixed  $value
     * @param  string  $event
     * @return bool
     */
    public static function isFillable($value, $event)
    {
        if ($event === 'add') {
            return true;
        } else {
            return self::isEditable($value);
        }
    }

    /**
     * @param  string|mixed  $ref_id
     * @param  string  $modelOnTarget
     * @param  string  $methodOnTarget
     * @return Request
     */
    public static function getRequestToBeSync($ref_id, $modelOnTarget, Request $request, $methodOnTarget)
    {
        $new_req_embeded = new stdClass;
        /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $model */
        $model = new $modelOnTarget;
        /** @var array<string, mixed> $items */
        $items = $model->getItems();

        foreach ($items as $key => $item) {
            if ($key == 'ref_id') {
                $new_req_embeded->$key = $ref_id;
            } else {
                $new_req_embeded->$key = $request->input($key);
            }
        }

        $new_req = [];
        $new_req[$methodOnTarget] = json_encode($new_req_embeded);
        $request = new Request;
        $request->merge($new_req);

        return $request;
    }

    /**
     * @return bool
     */
    public static function isRequestReadyToBeProcessed(Request $request)
    {
        $requests = $request->all();
        unset($requests['_token']);

        foreach ($requests as $key => $val) {
            $pos = strpos($key, '-');
            if ($pos === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Request
     */
    public static function removeSubCollectionInput(Request $request)
    {
        return $request;
    }

    /**
     * @param  array<mixed>  $additionalData
     * @return Request
     */
    public static function prepareRequest(Request $request, array $additionalData)
    {
        return $request->merge($additionalData);
    }

    /**
     * @param  string  $model
     * @param  bool  $is_EO
     * @param  bool  $is_EM
     * @return array<mixed>
     */
    public static function getArrayWithEmptyObj($model, $is_EO, $is_EM)
    {
        $arr = [];
        if ($is_EO) {
            $obj = new stdClass;
            /** @var \OfflineAgency\MongoAutoSync\Http\Models\MDModel $embedObj */
            $embedObj = new $model;
            /** @var array<string, mixed> $EOitems */
            $EOitems = $embedObj->getItems();

            // Current Obj Create
            foreach ($EOitems as $EOkey => $item) {
                $obj->$EOkey = null;
            }

            $arr[] = $obj;
        }// $is_EM == empty array

        return $arr;
    }

    /**
     * @param  string  $method
     * @param  bool  $is_EO
     * @param  bool  $is_EM
     * @param  int|string  $i
     * @return string
     */
    public static function getCounterForRelationships($method, $is_EO, $is_EM, $i)
    {
        if ($method === '') {
            return '';
        }
        if ($is_EO) {
            return '';
        }
        if ($is_EM) {
            return '';
        }

        return '-'.$i;
    }
}
