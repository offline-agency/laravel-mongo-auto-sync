<?php

namespace OfflineAgency\MongoAutoSync\Eloquent;

use Jenssegers\Mongodb\Eloquent\Builder as MongoDbEloquentBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;


class Builder extends MongoDbEloquentBuilder
{
    /**
     * @inheritdoc
     */
    public function update(array $values, array $options = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $relation->performUpdate($this->model, $values);

            return 1;
        }

        return $this->toBase()->update($this->addUpdatedAtColumn($values), $options);
    }

    /**
     * @inheritdoc
     */
    public function insert(array $values)
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $relation->performInsert($this->model, $values);

            return true;
        }

        return EloquentBuilder::insert($values);
    }

    /**
     * @inheritdoc
     */
    public function insertGetId(array $values, $sequence = null)
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $relation->performInsert($this->model, $values);

            return $this->model->getKey();
        }

        return EloquentBuilder::insertGetId($values, $sequence);
    }

    /**
     * @inheritdoc
     */
    public function delete()
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $relation->performDelete($this->model);

            return $this->model->getKey();
        }

        return EloquentBuilder::delete();
    }

    /**
     * @inheritdoc
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $value = $this->model->{$column};

            // When doing increment and decrements, Eloquent will automatically
            // sync the original attributes. We need to change the attribute
            // temporary in order to trigger an update query.
            $this->model->{$column} = null;

            $this->model->syncOriginalAttribute($column);

            $result = $this->model->update([$column => $value]);

            return $result;
        }

        return EloquentBuilder::increment($column, $amount, $extra);
    }

    /**
     * @inheritdoc
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $value = $this->model->{$column};

            // When doing increment and decrements, Eloquent will automatically
            // sync the original attributes. We need to change the attribute
            // temporary in order to trigger an update query.
            $this->model->{$column} = null;

            $this->model->syncOriginalAttribute($column);

            return $this->model->update([$column => $value]);
        }

        return EloquentBuilder::decrement($column, $amount, $extra);
    }

    /**
     * @inheritdoc
     */
    public function chunkById($count, callable $callback, $column = '_id', $alias = null)
    {
        return EloquentBuilder::chunkById($count, $callback, $column, $alias);
    }

    /**
     * @inheritdoc
     */
    public function raw($expression = null)
    {
        // Get raw results from the query builder.
        $results = $this->query->raw($expression);

        // Convert MongoCursor results to a collection of models.
        if ($results instanceof Cursor) {
            $results = iterator_to_array($results, false);

            return $this->model->hydrate($results);
        } // Convert Mongo BSONDocument to a single object.
        elseif ($results instanceof BSONDocument) {
            $results = $results->getArrayCopy();

            return $this->model->newFromBuilder((array) $results);
        } // The result is a single object.
        elseif (is_array($results) && array_key_exists('_id', $results)) {
            return $this->model->newFromBuilder((array) $results);
        }

        return $results;
    }

    /**
     * Add the "updated at" column to an array of values.
     * TODO Remove if https://github.com/laravel/framework/commit/6484744326531829341e1ff886cc9b628b20d73e
     * wiil be reverted
     * Issue in laravel frawework https://github.com/laravel/framework/issues/27791
     * @param array $values
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (!$this->model->usesTimestamps() || $this->model->getUpdatedAtColumn() === null) {
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
