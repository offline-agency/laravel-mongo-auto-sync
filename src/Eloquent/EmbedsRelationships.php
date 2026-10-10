<?php

namespace OfflineAgency\MongoAutoSync\Eloquent;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;
use OfflineAgency\MongoAutoSync\Relationships\EmbedsMany;
use OfflineAgency\MongoAutoSync\Relationships\EmbedsOne;

trait EmbedsRelationships
{
    /**
     * Define an embedded one-to-many relationship.
     *
     * @param  class-string  $related
     * @param  string|null  $localKey
     * @param  string|null  $foreignKey
     * @param  string|null  $relation
     * @return \MongoDB\Laravel\Relations\EmbedsMany<Model, Model, Collection<int, Model>>|EmbedsMany
     */
    public function embedsMany($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if ($relation === null) {
            [, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $relation = $caller['function'];
        }

        if ($localKey === null) {
            $localKey = $relation;
        }

        if ($foreignKey === null) {
            $foreignKey = Str::snake(class_basename($this));
        }

        /** @var Builder<Model> $query */
        $query = $this->newQuery();

        /** @var Model $instance */
        $instance = new $related;

        $embedsMany = new EmbedsMany($query, $this, $instance, $localKey, $foreignKey, $relation);

        return $embedsMany;
    }

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param  class-string  $related
     * @param  string|null  $localKey
     * @param  string|null  $foreignKey
     * @param  string|null  $relation
     * @return \MongoDB\Laravel\Relations\EmbedsOne<Model, Model, Model>|EmbedsOne
     */
    public function embedsOne($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if ($relation === null) {
            [, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $relation = $caller['function'];
        }

        if ($localKey === null) {
            $localKey = $relation;
        }

        if ($foreignKey === null) {
            $foreignKey = Str::snake(class_basename($this));
        }

        /** @var Builder<Model> $query */
        $query = $this->newQuery();

        /** @var Model $instance */
        $instance = new $related;

        $embedsOne = new EmbedsOne($query, $this, $instance, $localKey, $foreignKey, $relation);

        return $embedsOne;
    }
}
