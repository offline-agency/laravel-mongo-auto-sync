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
        if (! is_array($mlCollection)) {
            return '';
        }

        $value = $mlCollection[self::cl()] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @return string current Lang
     */
    public static function cl()
    {
        $locale = Config::get('app.locale');

        return is_string($locale) ? $locale : '';
    }

    /**
     * @param  array<mixed>|null  $destination
     * @param  mixed  $input  the value for the current locale
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
     * A (string) cast that does not fail on arrays and objects: scalars, null and Stringable values (ObjectId)
     * are cast as usual, any other value is replaced by its type name.
     */
    public static function toString(mixed $value): string
    {
        return is_scalar($value) || $value === null || $value instanceof Stringable ? (string) $value : get_debug_type($value);
    }

    /**
     * The attributes of a stored document keyed by name, as Eloquent expects them; PHP turns numeric names into
     * integer keys, which Eloquent reads by their string value.
     *
     * @param  array<mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function attributes(array $attributes): array
    {
        $named = [];
        foreach ($attributes as $key => $value) {
            $named[(string) $key] = $value;
        }

        return $named;
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
     * A relationship payload must be a JSON string; the documents it references may only be identified by a
     * plain id: a string or null. Operators, composite keys, lists and numbers are rejected before they reach
     * a query or the database. The documents of a JSON list or object (both are iterated by the parser) are
     * checked; other strings (empty, "null", not JSON) are left to the parser.
     *
     * @param  list<string>  $idKeys  ref_id for embedded relationships, id and _id for referenced ones
     *
     * @phpstan-assert string|null $value
     *
     * @throws InvalidRequestException
     */
    public static function assertSafeRefIds(string $key, mixed $value, array $idKeys = ['ref_id']): void
    {
        if ($value === null) {
            return;
        }
        if (! is_string($value)) {
            throw new InvalidRequestException(sprintf(
                "Invalid payload for relationship '%s': expected a JSON string, got %s.",
                $key,
                get_debug_type($value),
            ));
        }

        $objs = json_decode($value);
        if ($objs instanceof stdClass) {
            $objs = get_object_vars($objs);
        }
        if (! is_array($objs)) {
            return;
        }

        foreach ($objs as $obj) {
            if (! $obj instanceof stdClass) {
                continue;
            }
            foreach ($idKeys as $idKey) {
                $id = property_exists($obj, $idKey) ? $obj->{$idKey} : null;
                if ($id !== null && ! is_string($id)) {
                    throw new InvalidRequestException(sprintf(
                        "Invalid %s in relationship '%s': expected a string id, got %s.",
                        $idKey,
                        $key,
                        get_debug_type($id),
                    ));
                }
            }
        }
    }

    /**
     * The documents of a relationship payload, iterated as the parser always did: the items of a JSON list or
     * the property values of a JSON object. "null" and strings that are not JSON hold no document; any other
     * JSON value, or an item that is neither a JSON object nor null (an empty EmbedsOne), is rejected instead
     * of being skipped.
     *
     * @return list<object|null>
     *
     * @throws InvalidRequestException
     */
    public static function payloadDocuments(string $key, string $value): array
    {
        $decoded = json_decode($value);
        $items = $decoded instanceof stdClass ? get_object_vars($decoded) : ($decoded ?? []);
        if (! is_array($items)) {
            throw new InvalidRequestException(sprintf("Invalid payload for relationship '%s': expected a JSON list or object, got %s.", $key, get_debug_type($decoded)));
        }

        $documents = [];
        foreach ($items as $item) {
            if (! is_object($item) && $item !== null) {
                throw new InvalidRequestException(sprintf("Invalid payload for relationship '%s': expected a JSON object or null for each document, got %s.", $key, get_debug_type($item)));
            }
            $documents[] = $item;
        }

        return $documents;
    }

    /**
     * Id keys checked by assertSafeRefIds() for a relationship type.
     *
     * @return list<string>
     */
    public static function idKeysFor(mixed $type): array
    {
        return self::is_HM($type) || self::is_HO($type) ? ['id', '_id'] : ['ref_id'];
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
     * @return list<stdClass>
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
