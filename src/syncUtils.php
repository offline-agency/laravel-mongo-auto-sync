<?php

use Illuminate\Http\Request;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;

if (! function_exists('getTranslatedContent')) {

    /**
     * @param  array<string, mixed>|null  $mlCollection
     * @return string
     */
    function getTranslatedContent($mlCollection)
    {
        return SyncHelper::getTranslatedContent($mlCollection);
    }
}

if (! function_exists('cl')) {

    /**
     * @return string current Lang
     */
    function cl()
    {
        return SyncHelper::cl();
    }
}

if (! function_exists('ml')) {
    // save a localized field
    /**
     * @param  array<string, mixed>|null  $destination
     * @param  string  $input
     * @return array<string, mixed>
     */
    function ml($destination, $input)
    {
        return SyncHelper::ml($destination, $input);
    }
}

if (! function_exists('isML')) {
    /**
     * @param  mixed  $value
     * @return bool
     */
    function isML($value)
    {
        return SyncHelper::isML($value);
    }
}

if (! function_exists('isMD')) {
    /**
     * @param  mixed  $value
     * @return bool
     */
    function isMD($value)
    {
        return SyncHelper::isMD($value);
    }
}
if (! function_exists('is_EM')) {
    /**
     * @param  mixed  $value
     * @return bool
     */
    function is_EM($value)
    {
        return SyncHelper::is_EM($value);
    }
}

if (! function_exists('is_EO')) {
    /**
     * @param  mixed  $value
     * @return bool
     */
    function is_EO($value)
    {
        return SyncHelper::is_EO($value);
    }
}

if (! function_exists('is_HM')) {
    /**
     * @param  mixed  $value
     * @return bool
     */
    function is_HM($value)
    {
        return SyncHelper::is_HM($value);
    }
}

if (! function_exists('is_HO')) {
    /**
     * @param  mixed  $value
     * @return bool
     */
    function is_HO($value)
    {
        return SyncHelper::is_HO($value);
    }
}

if (! function_exists('isEditable')) {
    /**
     * @param  mixed  $value
     * @return bool
     */
    function isEditable($value)
    {
        return SyncHelper::isEditable($value);
    }
}

if (! function_exists('hasTarget')) {
    /**
     * @param  mixed  $value
     * @return bool
     */
    function hasTarget($value)
    {
        return SyncHelper::hasTarget($value);
    }
}
if (! function_exists('isFillable')) {
    /**
     * @param  mixed  $value
     * @param  string  $event
     * @return bool
     */
    function isFillable($value, $event)
    {
        return SyncHelper::isFillable($value, $event);
    }
}

if (! function_exists('getRequestToBeSync')) {
    /**
     * @param  string|mixed  $ref_id
     * @param  string  $modelOnTarget
     * @param  string  $methodOnTarget
     * @return Request
     */
    function getRequestToBeSync($ref_id, $modelOnTarget, Request $request, $methodOnTarget)
    {
        return SyncHelper::getRequestToBeSync($ref_id, $modelOnTarget, $request, $methodOnTarget);
    }
}

if (! function_exists('isRequestReadyToBeProcessed')) {
    /**
     * @return bool
     */
    function isRequestReadyToBeProcessed(Request $request)
    {
        return SyncHelper::isRequestReadyToBeProcessed($request);
    }
}

if (! function_exists('removeSubCollectionInput')) {
    /**
     * @return Request
     */
    function removeSubCollectionInput(Request $request)
    {
        return SyncHelper::removeSubCollectionInput($request);
    }
}

if (! function_exists('prepareRequest')) {

    /**
     * @param  array<string, mixed>  $additionalData
     * @return Request
     */
    function prepareRequest(Request $request, array $additionalData)
    {
        return SyncHelper::prepareRequest($request, $additionalData);
    }
}

if (! function_exists('getArrayWithEmptyObj')) {

    /**
     * @param  string  $model
     * @param  bool  $is_EO
     * @param  bool  $is_EM
     * @return array<int, object>
     */
    function getArrayWithEmptyObj($model, $is_EO, $is_EM)
    {
        return SyncHelper::getArrayWithEmptyObj($model, $is_EO, $is_EM);
    }
}

if (! function_exists('getCounterForRelationships')) {

    /**
     * @param  string  $method
     * @param  bool  $is_EO
     * @param  bool  $is_EM
     * @param  int|string  $i
     * @return string
     */
    function getCounterForRelationships($method, $is_EO, $is_EM, $i)
    {
        return SyncHelper::getCounterForRelationships($method, $is_EO, $is_EM, $i);
    }
}

if (! function_exists('getFullUrlImgByKey')) {
    /**
     * @param  string|null  $key
     * @return string
     */
    function getFullUrlImgByKey($key)
    {
        return '';
    }
}

if (! function_exists('getSiteGeneralValueByKey')) {
    /**
     * @param  string  $key
     * @return string
     */
    function getSiteGeneralValueByKey($key)
    {
        return '';
    }
}
