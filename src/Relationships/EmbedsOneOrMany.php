<?php

namespace OfflineAgency\MongoAutoSync\Relationships;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 * @template TResult of Collection|Model
 *
 * @extends Relation<TRelatedModel, TDeclaringModel, TResult>
 */
abstract class EmbedsOneOrMany extends Relation
{
    /**
     * The local key of the parent model.
     *
     * @var string
     */
    protected $localKey;

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The "name" of the relationship.
     *
     * @var string
     */
    protected $relation;

    /**
     * The Eloquent query builder instance.
     *
     * @var \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    protected $query;

    /**
     * @var TDeclaringModel
     */
    protected $parent;

    /**
     * @var TRelatedModel
     */
    protected $related;

    /**
     * Create a new embeds many relationship instance.
     *
     * @param  Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  TRelatedModel  $related
     * @param  string  $localKey
     * @param  string  $foreignKey
     * @param  string  $relation
     */
    public function __construct(Builder $query, Model $parent, Model $related, $localKey, $foreignKey, $relation)
    {
        // MongoDB\Laravel\Eloquent\Builder extends Illuminate\Database\Eloquent\Builder
        // so this assignment is valid at runtime
        /** @phpstan-ignore-next-line */
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $related;
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;
        $this->relation = $relation;

        // If this is a nested relation, we need to get the parent query instead.
        if ($parentRelation = $this->getParentRelation()) {
            $parentQuery = $parentRelation->getQuery();
            // MongoDB\Laravel\Eloquent\Builder extends Illuminate\Database\Eloquent\Builder
            // so this assignment is valid at runtime
            /** @var Builder<TRelatedModel> $parentQuery */
            /** @phpstan-ignore-next-line */
            $this->query = $parentQuery;
        }

        $this->addConstraints();
    }

    /**
     * {@inheritdoc}
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->getQualifiedParentKeyName(), '=', $this->getParentKey());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models)
    {
        // There are no eager loading constraints.
    }

    /**
     * Save a new model and attach it to the parent model.
     *
     * @return Model|bool
     */
    abstract public function performInsert(Model $model);

    /**
     * Save an existing model and attach it to the parent model.
     *
     * @param  array<string, mixed>  $values
     * @return Model|bool
     */
    abstract public function performUpdate(Model $model, array $values = []);

    /**
     * Delete an existing model and detach it from the parent model.
     *
     * @return int
     */
    abstract public function performDelete(Model $model);

    /**
     * {@inheritdoc}
     */
    public function match(array $models, Collection $results, $relation)
    {
        foreach ($models as $model) {
            $results = $model->$relation()->getResults();

            if (method_exists($model, 'setParentRelation')) {
                $model->setParentRelation($this);
            }

            $model->setRelation($relation, $results);
        }

        return $models;
    }

    /**
     * Shorthand to get the results of the relationship.
     *
     * @param  array<int|string, string>  $columns
     * @return TResult
     */
    public function get($columns = ['*'])
    {
        /** @var TResult $results */
        $results = $this->getResults();

        return $results;
    }

    /**
     * Get the number of embedded models.
     *
     * @return int
     */
    public function count()
    {
        $embedded = $this->getEmbedded();
        if ($embedded === null) {
            return 0;
        }

        return count($embedded);
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @return Model|bool
     */
    public function save(Model $model)
    {
        $model->setParentRelation($this);

        return $model->save() ? $model : false;
    }

    /**
     * Attach a collection of models to the parent instance.
     *
     * @param  Collection<int, Model>|array<int, Model>  $models
     * @return Collection<int, Model>|array<int, Model>
     */
    public function saveMany($models)
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }

    /**
     * Create a new instance of the related model.
     *
     * @param  array<string, mixed>  $attributes
     * @return Model
     */
    public function create(array $attributes = [])
    {
        // Here we will set the raw attributes to avoid hitting the "fill" method so
        // that we do not have to worry about a mass accessor rules blocking sets
        // on the models. Otherwise, some of these attributes will not get set.
        $instance = $this->related->newInstance($attributes);

        $instance->setParentRelation($this);

        $instance->save();

        return $instance;
    }

    /**
     * Create an array of new instances of the related model.
     *
     * @param  array<int|string, array<string, mixed>>  $records
     * @return array<int, Model>
     */
    public function createMany(array $records)
    {
        $instances = [];

        foreach ($records as $record) {
            if (is_array($record)) {
                $instances[] = $this->create($record);
            }
        }

        return $instances;
    }

    /**
     * Transform single ID, single Model or array of Models into an array of IDs.
     *
     * @param  mixed  $ids
     * @return array<int|string, mixed>
     */
    protected function getIdsArrayFrom($ids)
    {
        if ($ids instanceof \Illuminate\Support\Collection) {
            $ids = $ids->all();
        }

        if (! is_array($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as &$id) {
            if ($id instanceof Model) {
                $id = $id->getKey();
            }
        }

        return $ids;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>|null
     */
    protected function getEmbedded()
    {
        // Get raw attributes to skip relations and accessors.
        $attributes = $this->parent->getAttributes();

        // Get embedded models form parent attributes.
        $embedded = isset($attributes[$this->localKey]) ? (array) $attributes[$this->localKey] : null;

        return $embedded;
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<string, mixed>|null  $records
     * @return void
     */
    protected function setEmbedded($records)
    {
        // Assign models to parent attributes array.
        $attributes = $this->parent->getAttributes();
        $attributes[$this->localKey] = $records;

        // Set raw attributes to skip mutators.
        $this->parent->setRawAttributes($attributes);

        // Set the relation on the parent.
        $this->parent->setRelation($this->relation, $records === null ? null : $this->getResults());
    }

    /**
     * Get the foreign key value for the relation.
     *
     * @param  mixed  $id
     * @return mixed
     */
    protected function getForeignKeyValue($id)
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        // Convert the id to MongoId if necessary.
        /** @var \MongoDB\Laravel\Query\Builder $baseQuery */
        $baseQuery = $this->getBaseQuery();

        return $baseQuery->convertKey($id);
    }

    /**
     * Convert an array of records to a Collection.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return Collection<int, Model>
     */
    protected function toCollection(array $records = [])
    {
        $models = [];

        foreach ($records as $attributes) {
            $models[] = $this->toModel($attributes);
        }

        // Note: Eager loading is handled by the query builder, not needed here

        return $this->related->newCollection($models);
    }

    /**
     * Create a related model instanced.
     *
     * @param  array<string, mixed>|null  $attributes
     * @return Model
     */
    protected function toModel($attributes = [])
    {
        if ($attributes === null) {
            return $this->related->newInstance();
        }

        $connection = $this->related->getConnection();

        $model = $this->related->newFromBuilder(
            (array) $attributes,
            $connection ? $connection->getName() : null
        );

        $model->setParentRelation($this);

        $model->setRelation($this->foreignKey, $this->parent);

        // If you remove this, you will get segmentation faults!
        $model->setHidden(array_merge($model->getHidden(), [$this->foreignKey]));

        return $model;
    }

    /**
     * Get the relation instance of the parent.
     *
     * @return Relation<TRelatedModel, TDeclaringModel, mixed>|null
     */
    protected function getParentRelation()
    {
        $parentRelation = $this->parent->getParentRelation();
        if ($parentRelation === null) {
            return null;
        }

        /** @var Relation<TRelatedModel, TDeclaringModel, mixed> $parentRelation */
        return $parentRelation;
    }

    /**
     * {@inheritdoc}
     *
     * @return \Illuminate\Database\Eloquent\Builder<TRelatedModel>
     */
    public function getQuery()
    {
        // Because we are sharing this relation instance to models, we need
        // to make sure we use separate query instances.
        // MongoDB\Laravel\Eloquent\Builder extends Illuminate\Database\Eloquent\Builder
        /** @var \Illuminate\Database\Eloquent\Builder<TRelatedModel> $query */
        $query = clone $this->query;

        return $query;
    }

    /**
     * {@inheritdoc}
     *
     * @return \MongoDB\Laravel\Query\Builder
     */
    public function getBaseQuery()
    {
        // Because we are sharing this relation instance to models, we need
        // to make sure we use separate query instances.
        /** @var \MongoDB\Laravel\Query\Builder $baseQuery */
        $baseQuery = clone $this->query->getQuery();

        return $baseQuery;
    }

    /**
     * Check if this relation is nested in another relation.
     *
     * @return bool
     */
    protected function isNested()
    {
        return $this->getParentRelation() != null;
    }

    /**
     * Get the fully qualified local key name.
     *
     * @param  string  $glue
     * @return string
     */
    protected function getPathHierarchy($glue = '.')
    {
        $parentRelation = $this->getParentRelation();
        if ($parentRelation !== null && $parentRelation instanceof self) {
            return $parentRelation->getPathHierarchy($glue).$glue.$this->localKey;
        }

        return $this->localKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getQualifiedParentKeyName()
    {
        $parentRelation = $this->getParentRelation();
        if ($parentRelation !== null && $parentRelation instanceof self) {
            return $parentRelation->getPathHierarchy().'.'.$this->parent->getKeyName();
        }

        return $this->parent->getKeyName();
    }

    /**
     * Get the primary key value of the parent.
     *
     * @return string
     */
    protected function getParentKey()
    {
        return $this->parent->getKey();
    }

    /**
     * Return update values.
     *
     * @param  array<string, mixed>  $array
     * @param  string  $prepend
     * @return array<string, mixed>
     */
    public static function getUpdateValues($array, $prepend = '')
    {
        $results = [];

        foreach ($array as $key => $value) {
            $results[$prepend.$key] = $value;
        }

        return $results;
    }

    /**
     * Get the foreign key for the relationship.
     *
     * @return string
     */
    public function getQualifiedForeignKeyName()
    {
        return $this->foreignKey;
    }

    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param  string  $key
     * @return string
     */
    protected function whereInMethod(EloquentModel $model, $key)
    {
        return 'whereIn';
    }
}
