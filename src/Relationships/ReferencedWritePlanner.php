<?php

namespace OfflineAgency\MongoAutoSync\Relationships;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use OfflineAgency\MongoAutoSync\Context\SyncContext;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Validates the payload of a referenced (HasOne / HasMany) relationship and turns it into a ReferencedWritePlan,
 * with reads only. A child is identified only among the children the parent holds through its declared relation
 * (connection and global scopes included), so a payload can never update or re-parent another parent's child;
 * the foreign key comes from the relationship configuration, never from the payload.
 */
final class ReferencedWritePlanner
{
    /**
     * @param  array<string, mixed>  $relation  the relationship configuration ($mongoRelation entry)
     * @param  list<object|null>  $documents  the payload documents (SyncHelper::payloadDocuments())
     *
     * @throws InvalidConfigurationException
     * @throws InvalidRequestException
     */
    public static function plan(MDModel $parent, string $method, array $relation, array $documents): ReferencedWritePlan
    {
        [$childClass, $foreignKey, $localKey, $instance] = self::relationship($parent, $method, $relation);
        $isHasOne = $instance instanceof HasOne;

        // the declared relation, on the connection of the parent (a child model sharing the parent model's connection
        // follows a parent stored elsewhere, see SyncContext::targetModel())
        $childModel = SyncContext::targetModel($parent::class, SyncContext::connectionName($parent), $childClass);
        $query = $childModel->newQuery()->where($foreignKey, $parent->getAttribute($localKey))->whereNotNull($foreignKey);
        /** @var list<MDModel> $children */
        $children = $parent->exists ? array_values(array_filter($query->get()->all(), fn ($child) => $child instanceof MDModel)) : [];
        $childrenById = [];
        foreach ($children as $child) {
            $childrenById[SyncHelper::toString($child->getKey())] = $child;
        }

        $writable = self::writableFields(new $childClass, $foreignKey);
        $documents = array_values(array_filter($documents, fn (?object $document) => $document !== null));
        if ($isHasOne && count($documents) > 1) {
            throw new InvalidRequestException("Relationship '{$method}' holds at most one document.");
        }

        $creates = [];
        $updates = [];
        $retained = [];
        foreach ($documents as $document) {
            $attributes = self::attributes($document);
            $id = self::id($method, $attributes);
            unset($attributes['id'], $attributes['_id']);

            foreach (array_keys($attributes) as $field) {
                if (! in_array($field, $writable, true)) {
                    throw new InvalidRequestException("Field '{$field}' is not writable in relationship '{$method}'.");
                }
            }

            if ($id === null) {
                $creates[] = $attributes;

                continue;
            }
            if (isset($retained[$id])) {
                throw new InvalidRequestException("Duplicate referenced document in relationship '{$method}'.");
            }
            // the same message for an unknown id and for another parent's child: no existence oracle
            $child = $childrenById[$id] ?? throw new InvalidRequestException("Referenced document not found in relationship '{$method}'.");
            $retained[$id] = true;
            $updates[] = ['child' => $child, 'attributes' => $attributes];
        }

        // HasOne keeps the 3.x semantics: only the current child (the first one) is replaced
        $current = $isHasOne ? array_slice($children, 0, 1) : $children;
        $deletes = array_values(array_filter($current, fn (MDModel $child) => ! isset($retained[SyncHelper::toString($child->getKey())])));

        return new ReferencedWritePlan($method, $childClass, SyncContext::connectionName($childModel), $foreignKey, $localKey, $creates, $updates, $deletes);
    }

    /**
     * @param  array<string, mixed>  $relation
     * @return array{class-string<MDModel>, string, string, HasOneOrMany<Model, Model, mixed>}
     *
     * @throws InvalidConfigurationException
     */
    private static function relationship(MDModel $parent, string $method, array $relation): array
    {
        $childClass = $relation['model'] ?? null;
        $foreignKey = $relation['foreignKey'] ?? null;
        $localKey = $relation['localKey'] ?? 'id';
        $instance = method_exists($parent, $method) ? $parent->$method() : null;

        if (! is_string($childClass) || ! is_subclass_of($childClass, MDModel::class)) {
            throw new InvalidConfigurationException("The model of relationship '{$method}' must extend ".MDModel::class.'.');
        }
        if (! is_string($foreignKey) || $foreignKey === '' || ! is_string($localKey)) {
            throw new InvalidConfigurationException("Relationship '{$method}' must declare its foreignKey.");
        }
        if (! $instance instanceof HasOneOrMany || $instance->getForeignKeyName() !== $foreignKey || ! $instance->getRelated() instanceof $childClass) {
            throw new InvalidConfigurationException("Relationship '{$method}' must be a hasOne/hasMany of {$childClass} on '{$foreignKey}'.");
        }

        return [$childClass, $foreignKey, $localKey, $instance];
    }

    /**
     * The fields a payload may write: the child's items and relationships, never its foreign key.
     *
     * @return list<string>
     */
    private static function writableFields(MDModel $child, string $foreignKey): array
    {
        $fields = array_merge(array_keys($child->getItems()), array_keys($child->getMongoRelation()));

        return array_values(array_filter($fields, fn (string $field) => $field !== $foreignKey));
    }

    /**
     * The document as nested arrays (stdClass values are not accepted by a Request).
     *
     * @return array<string, mixed>
     */
    private static function attributes(object $document): array
    {
        $attributes = json_decode((string) json_encode($document), true);

        return SyncHelper::attributes(is_array($attributes) ? $attributes : []);
    }

    /**
     * The child id of a document, from id or _id (plain strings, see SyncHelper::assertSafeRefIds()).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidRequestException
     */
    private static function id(string $method, array $attributes): ?string
    {
        $ids = [];
        foreach (['id', '_id'] as $key) {
            $value = $attributes[$key] ?? null;
            if ($value === null) {
                continue;
            }
            if (! is_string($value) || $value === '') {
                throw new InvalidRequestException("Invalid {$key} in relationship '{$method}': expected a string id.");
            }
            $ids[] = $value;
        }
        if (count(array_unique($ids)) > 1) {
            throw new InvalidRequestException("Duplicate referenced document in relationship '{$method}'.");
        }

        return $ids[0] ?? null;
    }
}
