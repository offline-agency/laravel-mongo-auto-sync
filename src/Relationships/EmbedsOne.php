<?php

namespace OfflineAgency\MongoAutoSync\Relationships;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use MongoDB\BSON\ObjectId;

/**
 * @extends EmbedsOneOrMany<\MongoDB\Laravel\Eloquent\Model, \MongoDB\Laravel\Eloquent\Model, \MongoDB\Laravel\Eloquent\Model>
 */
class EmbedsOne extends EmbedsOneOrMany
{
    /**
     * {@inheritdoc}
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     *
     * @return \MongoDB\Laravel\Eloquent\Model
     */
    public function getResults()
    {
        return $this->toModel($this->getEmbedded());
    }

    /**
     * Save a new model and attach it to the parent model.
     *
     * @return Model|bool
     */
    public function performInsert(Model $model)
    {
        // Generate a new key if needed.
        if ($model->getKeyName() == '_id' && ! $model->getKey()) {
            $model->setAttribute('_id', new ObjectId);
        }

        // For deeply nested documents, let the parent handle the changes.
        if ($this->isNested()) {
            $this->associate($model);

            return $this->parent->save() ? $model : false;
        }

        $result = $this->getBaseQuery()->update([$this->localKey => $model->getAttributes()]);

        // Attach the model to its parent.
        if ($result) {
            $this->associate($model);
        }

        return $result ? $model : false;
    }

    /**
     * Save an existing model and attach it to the parent model.
     *
     * @param  array<string, mixed>  $values
     * @return Model|bool
     */
    public function performUpdate(Model $model, array $values = [])
    {
        if ($this->isNested()) {
            $this->associate($model);

            return $this->parent->save();
        }

        $values = $this->getUpdateValues($model->getDirty(), $this->localKey.'.');

        $result = $this->getBaseQuery()->update($values);

        // Attach the model to its parent.
        if ($result) {
            $this->associate($model);
        }

        return $result ? $model : false;
    }

    /**
     * Delete an existing model and detach it from the parent model.
     *
     * @return int
     */
    public function performDelete(Model $model)
    {
        // For deeply nested documents, let the parent handle the changes.
        if ($this->isNested()) {
            $this->dissociate();

            return $this->parent->save() ? 1 : 0;
        }

        // Overwrite the local key with null.
        /** @var \MongoDB\Laravel\Query\Builder $baseQuery */
        $baseQuery = $this->getBaseQuery();
        $result = $baseQuery->update([$this->localKey => null]);

        // Detach the model from its parent.
        if ($result) {
            $this->dissociate();
        }

        return $result ? 1 : 0;
    }

    /**
     * Attach the model to its parent.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate(Model $model)
    {
        $this->setEmbedded($model->getAttributes());

        return $model;
    }

    /**
     * Detach the model from its parent.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function dissociate()
    {
        $this->setEmbedded(null);

        return null;
    }

    /**
     * Delete all embedded models.
     *
     * @return int
     */
    public function delete()
    {
        $model = $this->getResults();
        if ($model === null) {
            return 0;
        }

        return $this->performDelete($model);
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
