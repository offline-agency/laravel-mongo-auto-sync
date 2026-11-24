<?php

namespace OfflineAgency\MongoAutoSync\Http\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use OfflineAgency\MongoAutoSync\Traits\Helper;
use OfflineAgency\MongoAutoSync\Traits\MainMongoTrait;
use OfflineAgency\MongoAutoSync\Traits\ModelAdditionalMethod;
use OfflineAgency\MongoAutoSync\Traits\PlainMongoTrait;
use OfflineAgency\MongoAutoSync\Traits\RelationshipMongoTrait;

/**
 * @property string|null $collection
 * @property array $items
 * @property mixed $slug
 * @property mixed $status
 * @property mixed $primarycategory
 * @property mixed $is_deleted
 * @property mixed $visibility
 * @property mixed $ref_id
 * @property mixed $is_active
 * @property int|float|null $autoincrement_id
 * @property mixed $name
 */
class MDModel extends Eloquent
{
    use Helper, MainMongoTrait, ModelAdditionalMethod, PlainMongoTrait, RelationshipMongoTrait;

    protected $connection = 'mongodb';

    /**
     * Name of the MongoDB collection. mongodb/laravel-mongodb 5 only honours
     * $table, so $collection is still supported for existing models.
     *
     * @var string|null
     */
    protected $collection;

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
    public function getTable()
    {
        return $this->collection ?? parent::getTable();
    }

    /**
     * @return string
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * @param  int  $size
     * @return mixed
     */
    public function getRandom($size = 3)
    {
        return $this->all()->random($size);
    }
}
