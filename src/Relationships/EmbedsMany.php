<?php

namespace OfflineAgency\MongoAutoSync\Relationships;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use MongoDB\BSON\ObjectId;

/**
 * @extends EmbedsOneOrMany<\MongoDB\Laravel\Eloquent\Model, \MongoDB\Laravel\Eloquent\Model, \Illuminate\Database\Eloquent\Collection<int, \MongoDB\Laravel\Eloquent\Model>>
 */
class EmbedsMany extends EmbedsOneOrMany
{
    /**
     * {@inheritdoc}
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \MongoDB\Laravel\Eloquent\Model>
     */
    public function getResults()
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \MongoDB\Laravel\Eloquent\Model> $results */
        $results = $this->toCollection($this->getEmbeddedAsIndexed());

        return $results;
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

        // Push the new model to the database.
        /** @var \MongoDB\Laravel\Query\Builder $baseQuery */
        $baseQuery = $this->getBaseQuery();
        $result = $baseQuery->push($this->localKey, $model->getAttributes(), true);

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
        // For deeply nested documents, let the parent handle the changes.
        if ($this->isNested()) {
            $this->associate($model);

            return $this->parent->save();
        }

        // Get the correct foreign key value.
        $foreignKey = $this->getForeignKeyValue($model);

        $values = $this->getUpdateValues($model->getDirty(), $this->localKey.'.$.');

        // Update document in database.
        $result = $this->getBaseQuery()->where($this->localKey.'.'.$model->getKeyName(), $foreignKey)
            ->update($values);

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
            $this->dissociate($model);

            return $this->parent->save() ? 1 : 0;
        }

        // Get the correct foreign key value.
        $foreignKey = $this->getForeignKeyValue($model);

        /** @var \MongoDB\Laravel\Query\Builder $baseQuery */
        $baseQuery = $this->getBaseQuery();
        $result = $baseQuery->pull($this->localKey, [$model->getKeyName() => $foreignKey]);

        if ($result) {
            $this->dissociate($model);
        }

        return $result ? 1 : 0;
    }

    /**
     * Associate the model instance to the given parent, without saving it to the database.
     *
     * @return Model
     */
    public function associate(Model $model)
    {
        $results = $this->getResults();
        if (! $results->contains($model)) {
            return $this->associateNew($model);
        }

        return $this->associateExisting($model);
    }

    /**
     * Dissociate the model instance from the given parent, without saving it to the database.
     *
     * @param  mixed  $ids
     * @return int
     */
    public function dissociate($ids = [])
    {
        $ids = $this->getIdsArrayFrom($ids);

        $records = $this->getEmbeddedAsIndexed();

        $primaryKey = $this->related->getKeyName();

        // Remove the document from the parent model.
        foreach ($records as $i => $record) {
            if (in_array($record[$primaryKey], $ids)) {
                unset($records[$i]);
            }
        }

        $this->setEmbedded($records);

        // We return the total number of deletes for the operation. The developers
        // can then check this number as a boolean type value or get this total count
        // of records deleted for logging, etc.
        return count($ids);
    }

    /**
     * Destroy the embedded models for the given IDs.
     *
     * @param  mixed  $ids
     * @return int
     */
    public function destroy($ids = [])
    {
        $count = 0;

        $ids = $this->getIdsArrayFrom($ids);

        // Get all models matching the given ids.
        $allModels = $this->getResults();
        $models = $allModels->filter(function ($model) use ($ids) {
            return in_array($model->getKey(), $ids);
        });

        // Pull the documents from the database.
        foreach ($models as $model) {
            if ($model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete all embedded models.
     *
     * @return int
     */
    public function delete()
    {
        // Overwrite the local key with an empty array.
        $result = $this->query->update([$this->localKey => []]);

        if ($result) {
            $this->setEmbedded([]);
        }

        return $result;
    }

    /**
     * Destroy alias.
     *
     * @param  mixed  $ids
     * @return int
     */
    public function detach($ids = [])
    {
        return $this->destroy($ids);
    }

    /**
     * Save alias.
     *
     * @return Model
     */
    /**
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function attach(Model $model)
    {
        /** @var \MongoDB\Laravel\Eloquent\Model $model */
        $result = $this->save($model);
        if ($result === false) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            return $model;
        }

        /** @var \Illuminate\Database\Eloquent\Model $result */
        return $result;
    }

    /**
     * Associate a new model instance to the given parent, without saving it to the database.
     *
     * @param  Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function associateNew($model)
    {
        // Create a new key if needed.
        if ($model->getKeyName() === '_id' && ! $model->getAttribute('_id')) {
            $model->setAttribute('_id', new ObjectId);
        }

        $records = $this->getEmbeddedAsIndexed();

        // Add the new model to the embedded documents.
        $records[] = $model->getAttributes();

        $this->setEmbedded($records);

        return $model;
    }

    /**
     * Associate an existing model instance to the given parent, without saving it to the database.
     *
     * @param  Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function associateExisting($model)
    {
        // Get existing embedded documents.
        $records = $this->getEmbeddedAsIndexed();

        $primaryKey = $this->related->getKeyName();

        $key = $model->getKey();

        // Replace the document in the parent model.
        foreach ($records as &$record) {
            if ($record[$primaryKey] == $key) {
                $record = $model->getAttributes();
                break;
            }
        }

        $this->setEmbedded($records);

        return $model;
    }

    /**
     * @param  int|null  $perPage
     * @param  array<int|string, string>  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \MongoDB\Laravel\Eloquent\Model>
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);
        $perPage = $perPage ?: $this->related->getPerPage();

        $results = $this->getEmbeddedAsIndexed();
        $results = $this->toCollection($results);
        $total = $results->count();
        $start = ($page - 1) * $perPage;

        $sliced = $results->slice(
            $start,
            $perPage
        );

        return new LengthAwarePaginator(
            $sliced,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
            ]
        );
    }

    /**
     * Get embedded models for EmbedsMany relationship.
     * This overrides the parent method to return the correct type.
     *
     * @return array<string, mixed>|null
     */
    protected function getEmbedded()
    {
        $embedded = parent::getEmbedded();
        if ($embedded === null) {
            return null;
        }
        // Ensure it's an array
        if (! is_array($embedded)) {
            return null;
        }

        // Return as-is, maintaining parent's type signature
        return $embedded;
    }

    /**
     * Get embedded models as indexed array for EmbedsMany.
     * This is a helper method that returns the indexed array format.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getEmbeddedAsIndexed()
    {
        $embedded = $this->getEmbedded();
        if ($embedded === null) {
            return [];
        }
        // Convert associative array to indexed array if needed
        if (array_is_list($embedded)) {
            /** @var array<int, array<string, mixed>> $embedded */
            return $embedded;
        }
        // Convert to indexed array
        $result = [];
        foreach ($embedded as $value) {
            if (is_array($value)) {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Set embedded models for EmbedsMany relationship.
     * This overrides the parent method to accept the correct type.
     *
     * @param  array<int, array<string, mixed>>|array<string, mixed>|null  $models
     * @return void
     */
    protected function setEmbedded($models)
    {
        if ($models === null) {
            parent::setEmbedded(null);

            return;
        }
        if (! is_array($models)) {
            $models = [$models];
        }
        // Convert to the format expected by parent (array<string, mixed>|null)
        // For EmbedsMany, we store as indexed array, but parent expects associative or null
        // We need to convert the indexed array to an associative array
        $associativeArray = [];
        foreach ($models as $key => $value) {
            if (is_array($value)) {
                $associativeArray[(string) $key] = $value;
            }
        }
        parent::setEmbedded($associativeArray);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists(Collection::class, $method)) {
            /** @var callable $callable */
            $callable = [$this->getResults(), $method];

            return call_user_func_array($callable, $parameters);
        }

        return parent::__call($method, $parameters);
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
