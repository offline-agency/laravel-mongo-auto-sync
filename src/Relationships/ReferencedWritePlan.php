<?php

namespace OfflineAgency\MongoAutoSync\Relationships;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * The validated writes of one referenced (HasOne / HasMany) relationship: built by ReferencedWritePlanner from
 * reads only, applied by RelationshipMongoTrait::processReferencedRelationship().
 */
final class ReferencedWritePlan
{
    /**
     * @param  class-string<MDModel>  $childClass
     * @param  list<array<string, mixed>>  $creates  attributes of the children to create
     * @param  list<array{child: MDModel, attributes: array<string, mixed>}>  $updates  children of the parent to update
     * @param  list<MDModel>  $deletes  children of the parent that the payload leaves out
     */
    public function __construct(
        public readonly string $method,
        public readonly string $childClass,
        public readonly string $foreignKey,
        public readonly string $localKey,
        public readonly array $creates,
        public readonly array $updates,
        public readonly array $deletes,
    ) {}
}
