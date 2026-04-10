<?php

namespace OfflineAgency\MongoAutoSync\Extensions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TKey of array-key
 * @template TModel of Model
 *
 * @extends Collection<TKey, TModel>
 */
class MongoCollection extends Collection
{
    // Check if the collection has an item with ref_id equal to id of the obj pass in to the parameter, useful to mark a category already selected in edit

    /**
     * @param  Model|null  $obj
     * @return bool
     */
    public function hasItem($obj)
    {
        if (is_null($obj)) {
            return false;
        }

        // Check if id exists
        if (! isset($obj->id) || is_null($obj->id)) {
            return false;
        }

        $id = $obj->id;

        $out = $this->filter(function ($col) use ($id) {
            /** @var TModel $col */
            return isset($col->ref_id) && $col->ref_id == $id;
        });

        return $out->count() > 0;
    }

    // Move the item with ref_id equal to the parameter, useful for edit primary category

    /**
     * @param  string|int  $id
     * @return $this
     */
    public function moveFirst($id)
    {
        // Iterate backwards or handle keys carefully if splicing
        // Actually, splice modifies the collection.
        // It's safer to find the item and prepend it.

        $keyToMove = null;
        foreach ($this->items as $key => $item) {
            /** @var TModel $item */
            if (isset($item->ref_id) && $item->ref_id == $id) {
                $keyToMove = $key;
                break;
            }
        }

        if (! is_null($keyToMove)) {
            /** @var TKey $keyToMove */
            /** @var TModel|null $item */
            $item = $this->pull($keyToMove);
            if ($item !== null) {
                /** @var TModel $item */
                $this->prepend($item);
            }
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function exist()
    {
        return $this->count() > 0;
    }
}
