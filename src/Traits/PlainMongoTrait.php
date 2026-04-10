<?php

namespace OfflineAgency\MongoAutoSync\Traits;

use Exception;
use Illuminate\Http\Request;
use MongoDB\BSON\UTCDateTime;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;

trait PlainMongoTrait
{
    /**
     * @param  array<string, mixed>  $options
     * @return void
     *
     * @throws Exception
     */
    public function storeEditAllItems(Request $request, string $event, array $options)
    {
        // Get the item name
        $items = $this->getItems();

        // Current Obj Create
        foreach ($items as $key => $item) {
            $is_ML = SyncHelper::isML($item);
            $is_MD = SyncHelper::isMD($item);

            $is_fillable = SyncHelper::isFillable($item, $event);
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
                    $this->$key = SyncHelper::ml($old_value, $request->input($key));
                } elseif ($is_MD) {
                    if ($request->input($key) == '' || $request->input($key) == null) {
                        $this->$key = null;
                    } else {
                        $this->$key = new UTCDateTime(new \DateTime($request->input($key)));
                    }
                } else {
                    $this->$key = $request->input($key);
                }
            }
        }

        $this->save();
    }
}
