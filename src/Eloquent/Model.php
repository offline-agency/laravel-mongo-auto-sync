<?php

namespace OfflineAgency\MongoAutoSync\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use MongoDB\Laravel\Eloquent\Model as MongoDbModel;

class Model extends MongoDbModel
{
    use EmbedsRelationships;

    /**
     * The parent relation instance.
     *
     * @var Relation<BaseModel, BaseModel, mixed>|null
     */
    protected $parentRelation;

    /**
     * Set the parent relation.
     *
     * @param  Relation<BaseModel, BaseModel, mixed>  $relation
     * @return void
     */
    public function setParentRelation(Relation $relation)
    {
        $this->parentRelation = $relation;
    }

    /**
     * Get the parent relation.
     *
     * @return Relation<BaseModel, BaseModel, mixed>|null
     */
    public function getParentRelation(): ?Relation
    {
        return $this->parentRelation;
    }

    /**
     * Delegates to laravel-mongodb (operator check on id/_id, ObjectId conversion, decimal casts,
     * dot notation, unset cancellation). Only addition: dotted keys listed in $dates (for example
     * "meta.created_at") are stored as UTCDateTime, as in 3.x.
     *
     * {@inheritdoc}
     */
    public function setAttribute($key, $value)
    {
        $key = (string) $key;

        if (str_contains($key, '.') && $value && in_array($key, $this->getDates(), true)) {
            $value = $this->fromDateTime($value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }
}
