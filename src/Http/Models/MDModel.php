<?php

namespace OfflineAgency\MongoAutoSync\Http\Models;

use OfflineAgency\MongoAutoSync\Eloquent\Model as Eloquent;
use OfflineAgency\MongoAutoSync\Traits\Helper;
use OfflineAgency\MongoAutoSync\Traits\MainMongoTrait;
use OfflineAgency\MongoAutoSync\Traits\ModelAdditionalMethod;
use OfflineAgency\MongoAutoSync\Traits\PlainMongoTrait;
use OfflineAgency\MongoAutoSync\Traits\RelationshipMongoTrait;

class MDModel extends Eloquent
{
    use MainMongoTrait, ModelAdditionalMethod, Helper, PlainMongoTrait, RelationshipMongoTrait;

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
