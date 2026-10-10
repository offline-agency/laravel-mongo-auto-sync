<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Model without relationships (see RelationshipRequestValidationTest).
 */
class VldPlain extends MDModel
{
    protected $table = 'vld_plains';

    protected $items = [
        'name' => [],
    ];
}
