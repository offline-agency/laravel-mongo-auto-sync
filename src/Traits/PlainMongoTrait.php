<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use DateTime;
use Exception;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;

trait PlainMongoTrait
{
    /**
     * @param  Request  $request
     * @param  string  $event
     * @param  array  $options
     *
     * @throws Exception
     */
    public function storeEditAllItems(Request $request, string $event, array $options)
    {
        //Get the item name
        $items = $this->getItems();

        //Current Obj Create
        foreach ($items as $key => $item) {
            $is_ML = isML($item);
            $is_MD = isMD($item);

            $is_fillable = isFillable($item, $event);
            $is_skippable = $this->getIsSkippable($request->has($key));

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
                    $this->$key = ml($old_value, $request->input($key));
                } elseif ($is_MD) {
                    if ($request->input($key) == '' || $request->input($key) == null) {
                        $this->$key = null;
                    } else {
                        $this->$key = new UTCDateTime(new DateTime($request->input($key)));
                    }
                } else {
                    $this->$key = $request->input($key);
                }
            }
        }

        $this->save();
    }
}
