<?php

namespace OfflineAgency\MongoAutoSync\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Model as MongoDbModel;

class Model extends MongoDbModel
{
    use EmbedsRelationships;

    /**
     * The parent relation instance.
     *
     * @var Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|null
     */
    protected $parentRelation;

    /**
     * Set the parent relation.
     *
     * @param  Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $relation
     * @return void
     */
    public function setParentRelation(Relation $relation)
    {
        $this->parentRelation = $relation;
    }

    /**
     * Get the parent relation.
     *
     * @return Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>|null
     */
    public function getParentRelation(): ?Relation
    {
        return $this->parentRelation;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($key, $value)
    {
        // Convert _id to ObjectID.
        if ($key == '_id' && is_string($value)) {
            /** @var \MongoDB\Laravel\Query\Builder $builder */
            $builder = $this->newBaseQueryBuilder();

            $value = $builder->convertKey($value);
        } // Support keys in dot notation.
        elseif (Str::contains($key, '.')) {
            if (in_array($key, $this->getDates()) && $value) {
                $value = $this->fromDateTime($value);
            }

            Arr::set($this->attributes, $key, $value);

            return;
        }

        return BaseModel::setAttribute($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($key)
    {
        // This checks for embedded relation support.
        if (method_exists($this, $key) && ! method_exists(self::class, $key)) {
            return $this->getRelationValue($key);
        }

        return BaseModel::getAttribute($key);
    }

    /**
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }
}
