<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Jenssegers\Mongodb\Eloquent\Model;

if (! function_exists('getTranslatedContent')) {

    /**
     * @param array $mlCollection
     *
     * @return string
     */
    function getTranslatedContent($mlCollection)
    {
        //Get current Lang
        $cl = Config::get('app.locale');

        if (is_array($mlCollection) && (array_key_exists('en_EN', $mlCollection) || array_key_exists('it_IT', $mlCollection) || ! is_null($mlCollection || ! isset($destination)))) {
            return $mlCollection[$cl];
        } else {
            return '';
        }
    }
}

if (! function_exists('cl')) {

    /**
     * @return string current Lang
     */
    function cl()
    {
        //Get current Lang
        return Config::get('app.locale');
    }
}

if (! function_exists('ml')) {
    //save a localized field
    /**
     * @param array $destination
     * @param string $input
     *
     * @return array ready to be saved
     */
    function ml($destination, $input)
    {
        if (is_null($destination)) {
            $destination = [];
        }

        return array_merge($destination, [cl() => $input]);
    }
}

if (! function_exists('isML')) {
    function isML($value)
    {
        try {
            if (gettype($value) !== 'array') {
                throw new Exception($value.' is not a valid array!');
            }
        } catch (Exception $exception) {
            echo $exception->getMessage();
        }

        if (array_key_exists('is-ml', $value)) {
            return $value['is-ml'];
        } else {
            return false;
        }
    }
}

if (! function_exists('isMD')) {
    function isMD($value)
    {
        if (array_key_exists('is-md', $value)) {
            return $value['is-md'];
        } else {
            return false;
        }
    }
}
if (! function_exists('is_EM')) {
    function is_EM($value)
    {
        if ($value === 'EmbedsMany') {
            return true;
        } else {
            return false;
        }
    }
}

if (! function_exists('is_EO')) {
    function is_EO($value)
    {
        if ($value === 'EmbedsOne') {
            return true;
        } else {
            return false;
        }
    }
}

if (! function_exists('is_HM')) {
    function is_HM($value)
    {
        if ($value === 'HasMany') {
            return true;
        } else {
            return false;
        }
    }
}

if (! function_exists('is_HO')) {
    function is_HO($value)
    {
        if ($value === 'HasOne') {
            return true;
        } else {
            return false;
        }
    }
}

if (! function_exists('isEditable')) {
    function isEditable($value)
    {
        if (array_key_exists('is-editable', $value)) {
            return $value['is-editable'];
        } else {
            return true;
        }
    }
}

if (! function_exists('hasTarget')) {
    function hasTarget($value)
    {
        if (array_key_exists('has-target', $value)) {
            return $value['has-target'];
        } else {
            return true;
        }
    }
}
if (! function_exists('isFillable')) {
    function isFillable($value, $event)
    {
        if ($event === 'add') {
            return true;
        } else {
            return isEditable($value);
        }
    }
}

if (! function_exists('getRequestToBeSync')) {
    /**
     * @param $ref_id
     * @param $modelOnTarget
     * @param Request $request
     *
     * @return Request
     */
    function getRequestToBeSync($ref_id, $modelOnTarget, Request $request, $methodOnTarget)
    {
        $new_req_embeded = new stdClass();
        $model = new $modelOnTarget;
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
}

if (! function_exists('getPrimaryRequest')) {
    /**
     * @param string $request
     *
     * @return string
     */
    function getPrimaryRequest($request)
    {
        if (! is_null($request)) {
            $arr = [];
            $categorylistdataJson = $request;
            $categorylistdataArr = json_decode($categorylistdataJson);
            if (empty($categorylistdataArr)) {
                return;
            } else {
                //extract first array  and create the primary category
                $primarycategory = ($categorylistdataArr[0]);
                $arr[] = $primarycategory;

                return json_encode($arr);
            }
        } else {
            return '';
        }
    }
}

if (! function_exists('getAID')) {
    /**
     * @param Model $model
     * @return string
     */
    function getAID(Model $model)
    {
        //Get Last Obj
        $obj = $model->orderBy('created_at', 'desc')->first();

        return is_null($obj) ? 1 : $obj->autoincrement_id + 1;
    }
}

if (! function_exists('processList')) {
    /**
     * @param array $array
     *
     * @return string
     */
    function processList($array)
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

if (! function_exists('isRequestReadyToBeProcessed')) {
    function isRequestReadyToBeProcessed(Request $request)
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
}

if (! function_exists('removeSubCollectionInput')) {
    function removeSubCollectionInput(Request $request)
    {
        return $request;
    }
}

if (! function_exists('prepareRequest')) {

    /**
     * @param Request $request
     * @param array $additionalData
     *
     * @return Request
     */
    function prepareRequest(Request $request, array $additionalData)
    {
        $request = $request->merge($additionalData);
        $additionalData = removeSubCollectionInput($request);
        $request = new Request;

        if (isRequestReadyToBeProcessed($request)) {
            return $request->merge($additionalData);
        } else {
            return prepareRequest($request, $additionalData);
        }
    }
}

if (! function_exists('getArrayWithEmptyObj')) {

    /**
     * @param $model
     *
     * @return array
     */
    function getArrayWithEmptyObj($model, $is_EO, $is_EM)
    {
        $arr = [];
        if ($is_EO) {
            $obj = new stdClass();
            $embedObj = new $model;
            $EOitems = $embedObj->getItems();

            //Current Obj Create
            foreach ($EOitems as $EOkey => $item) {
                $obj->$EOkey = null;
            }

            $arr[] = $obj;
        }// $is_EM == empty array

        return $arr;
    }
}

if (! function_exists('getCounterForRelationships')) {

    /**
     * @param $method
     * @param $is_EO
     * @param $is_EM
     * @param $i
     *
     * @return string
     */
    function getCounterForRelationships($method, $is_EO, $is_EM, $i)
    {
        if ($method === '' || ($method !== '' && $is_EO) || ($method === '' && $is_EM)) {
            return '';
        } else {
            return '-'.$i;
        }
    }
}
