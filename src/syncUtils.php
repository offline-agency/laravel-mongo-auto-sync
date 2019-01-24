<?php

use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Http\Request;
use Jenssegers\Mongodb\Eloquent\Model;

if (!function_exists('isML')) {
    function isML($value)
    {
        if (array_key_exists('is-ml', $value)) {
            return $value['is-ml'];
        } else {
            return false;
        }
    }
}


if (!function_exists('isMD')) {
    function isMD($value)
    {
        if (array_key_exists('is-md', $value)) {
            return $value['is-md'];
        } else {
            return false;
        }
    }
}
if (!function_exists('is_EM')) {
    function is_EM($value)
    {
        if ($value === 'EmbedsMany') {
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('is_EO')) {
    function is_EO($value)
    {
        if ($value === 'EmbedsOne') {
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('is_HM')) {
    function is_HM($value)
    {
        if ($value === 'HasMany') {
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('is_HO')) {
    function is_HO($value)
    {
        if ($value === 'HasOne') {
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('isEditable')) {
    function isEditable($value)
    {
        if (array_key_exists('is-editable', $value)) {
            return $value['is-editable'];
        } else {
            return true;
        }
    }
}

if (!function_exists('hasTarget')) {
    function hasTarget($value)
    {
        if (array_key_exists('has-target', $value)) {
            return $value['has-target'];
        } else {
            return true;
        }
    }
}
if (!function_exists('isFillable')) {
    function isFillable($value, $event)
    {
        if ($event === 'add') {
            return true;
        } else {
            return isEditable($value);
        }
    }
}

if (!function_exists('getRequestToBeSync')) {
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
            if ($key == "ref_id") {
                $new_req_embeded->$key = $ref_id;
            } else {
                $new_req_embeded->$key = $request->input($key);
            }
        }

        $new_req = array();
        $new_req[$methodOnTarget] = json_encode($new_req_embeded);
        $request = new Request;
        $request->merge($new_req);
        return $request;
    }
}

if (!function_exists('getPrimaryRequest')) {
    /**
     * @param String $request
     *
     * @return String
     */
    function getPrimaryRequest($request)
    {

        if (!is_null($request)) {
            $arr = [];
            $categorylistdataJson = $request;
            $categorylistdataArr = json_decode($categorylistdataJson);
            if (empty($categorylistdataArr)) {
                return null;
            } else {
                //extract first array  and create the primary category
                $primarycategory = ($categorylistdataArr[0]);
                $arr[] = $primarycategory;
                $out = json_encode($arr);
                return $out;
            }
        } else {
            return "";
        }
    }
}

if (!function_exists('getAID')) {
    /**
     * @param String $request
     *
     * @return String
     */
    function getAID(Model $model)
    {
        //Get Last Obj
        $q = $model->orderBy('created_at', 'desc')->first();
        //check if Obj exist and increments numeric id
        if ($q == null) {
            $arr['autoincrement_id'] = 1;
        } else {
            $arr['autoincrement_id'] = $q->autoincrement_id + 1;
        }
        return $arr['autoincrement_id'];

    }
}

if (!function_exists('processList')) {
    /**
     * @param array $array
     *
     * @return String
     */
    function processList($array)
    {
        $final = [];
        for ($i = 0; $i < count($array); $i++) {
            $obj = [];
            if ($array[$i] != null) {
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


if (!function_exists('processInvoiceItemList')) {

    function processInvoiceItemList($request, $type)
    {
        // create obj for every product
        $list = [];
        $total_products_vat = 0;
        $total_products_taxable_price = 0;

        if (!is_null($request->product_price)) {
            for ($i = 0; $i < count($request->product_price); $i++) {
                $obj = ['ref_id' => $request->product_id[$i],
                    'name' => $request->product_name[$i],
                    'code' => $request->product_code[$i],
                    'price' => $request->product_price[$i],
                    'vat_name' => $request->vatlist[$i],
                    'vat_value' => $request->vat_value[$i],
                    'vat_label' => $request->vat_label[$i],
                    'quantity' => $request->quantity[$i],
                    'discount' => $request->product_discount[$i],
                    'taxable_price' => $request->product_taxable_price[$i],
                    'partial_vat' => $request->product_partial_vat[$i],
                    'total_price' => $request->product_total_price[$i],
                    'collection_type' => 'product',
                ];
                $list[] = $obj;
                $total_products_vat += $request->product_partial_vat[$i];
                $total_products_taxable_price += $request->product_taxable_price[$i];
            }
        }

        if ($type = 'event') {
            $obj = ['ref_id' => $request->course_id,
                'name' => $request->title,
                'code' => $request->code_alfanumeric,
                'price' => $request->event_price,
                'vat_name' => array_values(array_slice($request->vat_name, -1))[0],
                'vat_value' => array_values(array_slice($request->vat_value, -1))[0],
                'vat_label' => array_values(array_slice($request->vat_label, -1))[0],
                'quantity' => "1",
                'discount' => $request->event_discount,
                'taxable_price' => $request->event_taxable_price,
                'partial_vat' => $request->event_partial_vat,
                'total_price' => $request->event_total_price,
                'collection_type' => 'course',
            ];
        }
        //create the event obj

        $list[] = $obj;

        return [json_encode($list), $total_products_vat, $total_products_taxable_price];
    }
}


if (!function_exists('isRequestReadyToBeProcessed')) {

    function isRequestReadyToBeProcessed(Request $request)
    {
        $requests = $request->all();
        unset($requests['_token']);

        foreach ($requests as $key => $val) {
            $pos = strpos($key, "-");
            if ($pos === false) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('removeSubCollectionInput')) {

    function removeSubCollectionInput(Request $request)
    {
        return $request;
    }
}

if (!function_exists('prepareRequest')) {

    /**
     * @param Request $request
     * @param array $additionalData
     *
     * @return Request
     */
    function prepareRequest(Request $request, Array $additionalData)
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


if (!function_exists('getArrayWithEmptyObj')) {

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

if (!function_exists('getCounterForRelationships')) {

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
        if ($method == "" || ($method !== "" && $is_EO) || ($method == "" && $is_EM)) {
            return "";
        } else {
            return "-" . $i;
        }
    }
}

if (!function_exists('getAddress')) {

    /**
     * @param Request $request
     * @param $i
     *
     * @return stdClass
     */
    function getAddress(Request $request, $i)
    {


        $address = new stdClass();
        $address->officeName = $request->officeName[$i];

        $address->ref_id = null;
        $address->phonenumber = $request->phonenumber[$i];
        $address->country = $request->country[$i];
        $address->zip = $request->zip[$i];
        $address->address = $request->address[$i];
        $address->houseNumber = $request->houseNumber[$i];
        $address->latitude = $request->latitude[$i];
        $address->longitude = $request->longitude[$i];
        return $address;
    }
}

if (!function_exists('getBillingAddress')) {
    /**
     * @param Request $request
     *
     * @return stdClass
     */
    function getBillingAddress(Request $request)
    {
        return getAddress($request, 0);
    }
}
if (!function_exists('getShippingAddress')) {

    /**
     * @param Request $request
     *
     * @return stdClass
     */
    function getShippingAddress(Request $request)
    {
        return getAddress($request, 1);
    }

}
if (!function_exists('getAdditionalDataForCompany')) {
    /**
     * @param $request
     *
     * @return array
     */
    function getAdditionalDataForCompany($request)
    {
        $arr_BA = [];   //billing address
        $arr_SA = [];   //shipping address
        $arr_AA = [];   //additional address
        $arr_MU = [];   //municipality
        $arr_PR = [];   //province
        $arr = [];   //additional data
        $aa_counter = 0;
        $sizeAddress = count($request->officeName);
        $shipping_exist = $request->is_billing_shipping_equal == "on" ? true : false;
        $shippingAddress = [];
        $billingAddress = getBillingAddress($request);

        if ($shipping_exist) {
            $shippingAddress = getShippingAddress($request);
        }

        //Create BillingAddress Field
        $arr_BA[0] = $billingAddress;
        $arr['billingaddress-municipalitywithname'] = $request->municipalitywithname[0];
        $arr['billingaddress-provincewithname'] = $request->provincewithname[0];

        //Add BillingAddress as AdditionalAddress
        $arr_AA[0] = $billingAddress;
        $arr['additionaladdress-municipalitywithname-0'] = $request->municipalitywithname[0];
        $arr['additionaladdress-provincewithname-0'] = $request->provincewithname[0];
        $aa_counter++;
        //  dd($request,$sizeAddress <= 2 && $shipping_exist,$sizeAddress <= 2 && !$shipping_exist,$sizeAddress >= 2 && $shipping_exist,$sizeAddress >= 2 && !$shipping_exist);


        if ($sizeAddress <= 2 && $shipping_exist) {

            //Add BillingAddress as ShippingAddress
            $arr_SA[0] = $shippingAddress;
            $arr['shippingaddress-municipalitywithname'] = $request->municipalitywithname[1];
            $arr['shippingaddress-provincewithname'] = $request->provincewithname[1];

            $arr_AA[1] = $shippingAddress;
            $arr['additionaladdress-municipalitywithname-1'] = $request->municipalitywithname[1];
            $arr['additionaladdress-provincewithname-1'] = $request->provincewithname[1];
            $aa_counter++;

        } else if ($sizeAddress == 2 && !$shipping_exist) {

            $arr_AA[$aa_counter] = getAddress($request, $aa_counter);
            $arr['additionaladdress-municipalitywithname-' . $aa_counter] = $request->municipalitywithname[1];
            $arr['additionaladdress-provincewithname-' . $aa_counter] = $request->provincewithname[1];


        } else if ($sizeAddress >= 2 && $shipping_exist) {
            $arr_SA[0] = $shippingAddress;
            $arr['shippingaddress-municipalitywithname'] = $request->municipalitywithname[1];
            $arr['shippingaddress-provincewithname'] = $request->provincewithname[1];

            //Add ShippingAddress as AdditionalAddress
            $arr_AA[1] = $shippingAddress;
            $arr['additionaladdress-municipalitywithname-1'] = $request->municipalitywithname[1];
            $arr['additionaladdress-provincewithname-1'] = $request->provincewithname[1];
            $aa_counter++;

            //Add all Additional Addresses
            for ($i = 2; $i < $sizeAddress; $i++) {
                $arr_AA[$aa_counter] = getAddress($request, $i);
                $arr['additionaladdress-municipalitywithname-' . $aa_counter] = $request->municipalitywithname[$i];
                $arr['additionaladdress-provincewithname-' . $aa_counter] = $request->provincewithname[$i];
                $aa_counter++;
            }
            //Add BillingAddress as ShippingAddress


        } else if ($sizeAddress >= 2 && !$shipping_exist) {
            for ($i = 1; $i < $sizeAddress; $i++) {
                $arr_AA[$aa_counter] = getAddress($request, $i);
                $arr['additionaladdress-municipalitywithname-' . $aa_counter] = $request->municipalitywithname[$i];
                $arr['additionaladdress-provincewithname-' . $aa_counter] = $request->provincewithname[$i];
                $aa_counter++;
            }

        } else {//!$shipping_is_null
            // Forse non serve
            for ($i = 2; $i < $sizeAddress; $i++) {
                $arr_AA[$aa_counter] = getAddress($request, $i);
                $arr['additionaladdress-municipalitywithname-' . $aa_counter] = $request->municipalitywithname[$i];
                $arr['additionaladdress-provincewithname-' . $aa_counter] = $request->provincewithname[$i];
                $aa_counter++;

            }
        }

        $arr['billingaddress'] = json_encode($arr_BA);
        $arr['shippingaddress'] = json_encode($arr_SA);
        $arr['additionaladdress'] = json_encode($arr_AA);
        return $arr;
    }
}