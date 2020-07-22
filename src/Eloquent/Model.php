<?php


namespace OfflineAgency\MongoAutoSync\Eloquent;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Jenssegers\Mongodb\Eloquent\Model as MongoDbModel;
use Illuminate\Database\Eloquent\Model as BaseModel;

class Model extends MongoDbModel
{
    use EmbedsRelationships;

    /**
     * {@inheritdoc}
     */
    public function setAttribute($key, $value)
    {
        // Convert _id to ObjectID.
        if ($key == '_id' && is_string($value)) {
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
     * @inheritdoc
     */
    public function getAttribute($key)
    {
        // This checks for embedded relation support.
        if (method_exists($this, $key) && !method_exists(self::class, $key)) {
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
