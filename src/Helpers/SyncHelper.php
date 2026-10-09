<?php

namespace OfflineAgency\MongoAutoSync\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use MongoDB\BSON\ObjectId;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;
use stdClass;
use Stringable;

class SyncHelper
{
    /**
     * @param  array<mixed>|null  $mlCollection
     * @return string
     */
    public static function getTranslatedContent($mlCollection)
    {
        $cl = Config::get('app.locale');

        if (! is_array($mlCollection)) {
            return '';
        }

        $value = $mlCollection[$cl] ?? '';

        return is_string($value) ? $value : '';
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
     * Distinct ref_id values (as strings) of an embedded relation value: null, a single mini model or array
     * (EmbedsOne), or an iterable of mini models or arrays (EmbedsMany).
     *
     * @return list<string>
     */
    public static function refIds(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $items = self::isSingleEmbedded($value) ? [$value] : $value;
        if (! is_iterable($items)) {
            return [];
        }

        $ids = [];
        foreach ($items as $item) {
            $refId = match (true) {
                is_array($item) => $item['ref_id'] ?? null,
                $item instanceof Model => $item->getAttribute('ref_id'),
                is_object($item) => $item->ref_id ?? null,
                default => null,
            };
            if (is_string($refId) || $refId instanceof ObjectId) {
                $ids[] = (string) $refId;
            }
        }

        return array_values(array_unique(array_filter($ids, fn (string $id) => $id !== '')));
    }

    /**
     * Compare two ids regardless of their representation (MongoDB\BSON\ObjectId or string).
     */
    public static function sameId(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }
        if (! (is_scalar($a) || $a instanceof Stringable) || ! (is_scalar($b) || $b instanceof Stringable)) {
            return false;
        }

        return (string) $a === (string) $b;
    }

    /**
     * A relationship payload (JSON list of mini models) may only reference documents by a plain id:
     * a string, an integer or null. Objects and lists (MongoDB operators, composite keys) are rejected
     * before they reach a query or the database. Values that are not a JSON list are left to the parser.
     *
     * @throws InvalidRequestException
     */
    public static function assertSafeRefIds(string $key, mixed $value): void
    {
        if (! is_string($value)) {
            return;
        }

        $objs = json_decode($value);
        if (! is_array($objs)) {
            return;
        }

        foreach ($objs as $obj) {
            if (! $obj instanceof stdClass || ! property_exists($obj, 'ref_id')) {
                continue;
            }
            $refId = $obj->ref_id;
            if ($refId !== null && ! is_string($refId) && ! is_int($refId)) {
                throw new InvalidRequestException(sprintf(
                    "Invalid ref_id in relationship '%s': expected a string or integer id, got %s.",
                    $key,
                    get_debug_type($refId),
                ));
            }
        }
    }

    /**
     * Both stored forms of an id (string and ObjectId), to match documents written by either.
     *
     * @return list<string|ObjectId>
     */
    public static function idVariants(mixed $id): array
    {
        if ($id instanceof ObjectId) {
            return [$id, (string) $id];
        }
        if (! is_string($id) || $id === '') {
            return [];
        }

        return strlen($id) === 24 && ctype_xdigit($id) ? [$id, new ObjectId($id)] : [$id];
    }

    private static function isSingleEmbedded(mixed $value): bool
    {
        if ($value instanceof Model) {
            return true;
        }
        if (is_array($value)) {
            return array_key_exists('ref_id', $value);
        }

        return $value instanceof stdClass;
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
        /** @var MDModel $model */
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
            /** @var MDModel $embedObj */
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
        // Same rule as 3.x: no counter on the root level and for EmbedsOne, "-<i>" for nested EmbedsMany items
        if ($method === '' || $is_EO) {
            return '';
        }

        return '-'.$i;
    }
}
