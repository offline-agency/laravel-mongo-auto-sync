<?php

namespace OfflineAgency\MongoAutoSync\Eloquent;

use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use LogicException;
use MongoDB\Driver\Cursor;
use MongoDB\Laravel\Eloquent\Builder as MongoDbEloquentBuilder;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Query\Builder as QueryBuilder;
use MongoDB\Model\BSONDocument;
use OfflineAgency\MongoAutoSync\Relationships\EmbedsOneOrMany;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends MongoDbEloquentBuilder<TModel>
 */
class Builder extends MongoDbEloquentBuilder
{
    /**
     * {@inheritdoc}
     *
     * @param  array<mixed>  $values
     * @param  array<mixed>  $options
     */
    public function update(array $values, array $options = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if (method_exists($this->model, 'getParentRelation') && $relation = $this->model->getParentRelation()) {
            /** @var EmbedsOneOrMany<Model, Model, Collection<int, Model>> $relation */
            /** @var Model $model */
            $model = $this->model;
            $relation->performUpdate($model, $values);

            return 1;
        }

        return $this->toBase()->update($this->addUpdatedAtColumn($values), $options);
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<mixed>  $values
     * @return bool
     */
    public function insert(array $values)
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if (method_exists($this->model, 'getParentRelation') && $relation = $this->model->getParentRelation()) {
            $relation->performInsert($this->model, $values);

            return true;
        }

        return EloquentBuilder::insert($values);
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<mixed>  $values
     * @param  string|null  $sequence
     * @return mixed
     */
    public function insertGetId(array $values, $sequence = null)
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if (method_exists($this->model, 'getParentRelation') && $relation = $this->model->getParentRelation()) {
            $relation->performInsert($this->model, $values);

            return $this->model->getKey();
        }

        return EloquentBuilder::insertGetId($values, $sequence);
    }

    /**
     * {@inheritdoc}
     */
    public function delete()
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if (method_exists($this->model, 'getParentRelation') && $relation = $this->model->getParentRelation()) {
            $relation->performDelete($this->model);

            return $this->model->getKey();
        }

        return EloquentBuilder::delete();
    }

    /**
     * {@inheritdoc}
     *
     * @param  string|Expression  $column
     * @param  float|int  $amount
     * @param  array<mixed>  $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if (method_exists($this->model, 'getParentRelation') && $relation = $this->model->getParentRelation()) {
            // Embedded documents can only be incremented by attribute name.
            if (! is_string($column)) {
                return 0;
            }

            /** @var mixed $value */
            $value = $this->model->{$column};

            // When doing increment and decrements, Eloquent will automatically
            // sync the original attributes. We need to change the attribute
            // temporary in order to trigger an update query.
            $this->model->{$column} = null;
            $this->model->syncOriginalAttribute($column);

            /** @var array<string, mixed> $updateData */
            $updateData = [$column => $value];

            return (int) $this->model->update($updateData);
        }

        return EloquentBuilder::increment($column, $amount, $extra);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string|Expression  $column
     * @param  float|int  $amount
     * @param  array<mixed>  $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if (method_exists($this->model, 'getParentRelation') && $relation = $this->model->getParentRelation()) {
            // Embedded documents can only be decremented by attribute name.
            if (! is_string($column)) {
                return 0;
            }

            /** @var mixed $value */
            $value = $this->model->{$column};

            // When doing increment and decrements, Eloquent will automatically
            // sync the original attributes. We need to change the attribute
            // temporary in order to trigger an update query.
            $this->model->{$column} = null;
            $this->model->syncOriginalAttribute($column);

            /** @var array<string, mixed> $updateData */
            $updateData = [$column => $value];

            return (int) $this->model->update($updateData);
        }

        return EloquentBuilder::decrement($column, $amount, $extra);
    }

    /**
     * {@inheritdoc}
     *
     * @param  (Closure(\MongoDB\Collection): mixed)|\Illuminate\Database\Query\Expression<literal-string|int|float>|null  $expression
     * @return Collection<int, TModel>|BSONDocument|TModel|array<mixed>|mixed
     */
    public function raw($expression = null)
    {
        // Get raw results from the MongoDB query builder (its raw() accepts a closure or an expression).
        $query = $this->getQuery();
        if (! $query instanceof QueryBuilder) {
            throw new LogicException('raw() requires a MongoDB query builder, got '.get_class($query));
        }
        $results = $query->raw($expression);

        // Convert MongoCursor results to a collection of models.
        if ($results instanceof Cursor) {
            $results = iterator_to_array($results, false);

            /** @var Collection<int, TModel> */
            return $this->model->hydrate($results);
        } // Convert Mongo BSONDocument to a single object.
        elseif ($results instanceof BSONDocument) {
            $results = $results->getArrayCopy();

            /** @var TModel */
            return $this->model->newFromBuilder((array) $results);
        } // The result is a single object.
        elseif (is_array($results) && array_key_exists('_id', $results)) {
            /** @var TModel */
            return $this->model->newFromBuilder((array) $results);
        }

        return $results;
    }

    /**
     * Add the "updated at" column to an array of values.
     * TODO Remove if https://github.com/laravel/framework/commit/6484744326531829341e1ff886cc9b628b20d73e
     * wiil be reverted
     * Issue in laravel frawework https://github.com/laravel/framework/issues/27791.
     *
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (! $this->model->usesTimestamps() || $this->model->getUpdatedAtColumn() === null) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();
        $values = array_merge(
            [$column => $this->model->freshTimestampString()],
            $values
        );

        return $values;
    }
}
