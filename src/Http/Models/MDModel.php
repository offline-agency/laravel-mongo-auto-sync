<?php

namespace OfflineAgency\MongoAutoSync\Http\Models;

use OfflineAgency\MongoAutoSync\Traits\Helper;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use OfflineAgency\MongoAutoSync\Traits\ModelAdditionalMethod;
use OfflineAgency\MongoAutoSync\Traits\MongoSyncTrait;

class MDModel extends Eloquent
{
    use MongoSyncTrait, ModelAdditionalMethod, Helper;

    protected $connection = 'mongodb';
    protected $dates = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * @param int $size
     *
     * @return mixed
     */
    public function getRandom($size = 3)
    {
        return $this->all()->random($size);
    }
}
