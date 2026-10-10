<?php

namespace OfflineAgency\MongoAutoSync\Eloquent;

use Illuminate\Contracts\Database\Query\Expression;
use MongoDB\Laravel\Eloquent\Builder as MongoDbEloquentBuilder;

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
     * @param  string|Expression  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
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

            // forceFill: the incremented value is not subject to mass assignment protection
            $this->model->forceFill($updateData);

            return (int) $this->model->save();
        }

        return parent::increment($column, $amount, $extra);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string|Expression  $column
     * @param  float|int  $amount
     * @param  array<string, mixed>  $extra
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

            // forceFill: the incremented value is not subject to mass assignment protection
            $this->model->forceFill($updateData);

            return (int) $this->model->save();
        }

        return parent::decrement($column, $amount, $extra);
    }
}
