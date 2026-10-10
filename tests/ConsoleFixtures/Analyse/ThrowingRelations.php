<?php

namespace Tests\ConsoleFixtures\Analyse;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;
use RuntimeException;

/**
 * A model whose relation configuration cannot be read.
 */
class ThrowingRelations extends MDModel
{
    public function getMongoRelation(): array
    {
        throw new RuntimeException('relations unavailable');
    }
}
