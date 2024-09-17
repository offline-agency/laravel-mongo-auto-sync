<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Jenssegers\Mongodb\Eloquent\Model;

if (! function_exists('getTranslatedContent')) {
    /**
     * @param  array  $mlCollection
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
     * @param  array  $destination
     * @param  string  $input
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

if (! function_exists('getAID')) {
    /**
     * @param  Model  $model
     * @return string
     */
    function getAID(Model $model)
    {
        //Get Last Obj
        $obj = $model->orderBy('created_at', 'desc')->first();

        return is_null($obj) ? 1 : $obj->autoincrement_id + 1;
    }
}

if (! function_exists('getArrayWithEmptyObj')) {
    /**
     * @param  $model
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
     * @param  $method
     * @param  $is_EO
     * @param  $is_EM
     * @param  $i
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

    if (! function_exists('getTypeOnTarget')) {
        function getTypeOnTarget($relation)
        {
            return Arr::has($relation, 'typeOnTarget') ? Arr::get($relation, 'typeOnTarget') : 'EmbedsMany';
        }
    }
}
