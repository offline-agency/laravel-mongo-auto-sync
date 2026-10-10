<?php

namespace OfflineAgency\MongoAutoSync\Relationships;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * The validated writes of one referenced (HasOne / HasMany) relationship: built by ReferencedWritePlanner from
 * reads only, applied by RelationshipMongoTrait::processReferencedRelationship().
 */
final class ReferencedWritePlan
{
    /**
     * @param  class-string<MDModel>  $childClass
     * @param  string  $childConnection  the connection holding the children (the parent's, see SyncContext::targetModel())
     * @param  list<array<string, mixed>>  $creates  attributes of the children to create
     * @param  list<array{child: MDModel, attributes: array<string, mixed>}>  $updates  children of the parent to update
     * @param  list<MDModel>  $deletes  children of the parent that the payload leaves out
     * @param  EloquentBuilder<MDModel>|null  $owner  query of the declared relation (null for a new parent): the condition every update and delete of a child must still satisfy when it is written
     */
    public function __construct(
        public readonly string $method,
        public readonly string $childClass,
        public readonly string $childConnection,
        public readonly string $foreignKey,
        public readonly string $localKey,
        public readonly array $creates,
        public readonly array $updates,
        public readonly array $deletes,
        public readonly ?EloquentBuilder $owner = null,
    ) {}
}
