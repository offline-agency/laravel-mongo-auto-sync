<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use MongoDB\BSON\UTCDateTime;

trait PlainMongoTrait
{
    /**
     * @param  array  $request
     * @param  string  $event
     * @param  array  $options
     *
     * @throws Exception
     */
    public function storeEditAllItems(array $request, string $event, array $options)
    {
        //Get the item name
        $items = $this->getItems();

        //Current Obj Create
        foreach ($items as $key => $item) {
            $is_ML = isML($item);
            $is_MD = isMD($item);

            $is_fillable = isFillable($item, $event);
            $is_skippable = $this->getIsSkippable(Arr::has($request, $key));

            if ($is_skippable) {
                continue;
            } else {
                $this->checkRequestExistence(
                    $request,
                    $key
                );
            }

            if ($is_fillable) {
                if ($is_ML) {
                    if (is_null($this->$key)) {
                        $old_value = [];
                    } else {
                        $old_value = $this->$key;
                    }
                    $this->$key = ml($old_value, Arr::get($request, $key));
                } elseif ($is_MD) {
                    if (Arr::get($request, $key) == '' || Arr::get($request, $key) == null) {
                        $this->$key = null;
                    } else {
                        $this->$key = new UTCDateTime(new DateTime(Arr::get($request, $key)));
                    }
                } else {
                    $this->$key = Arr::get($request, $key);
                }
            }
        }

        $this->save();
    }
}
