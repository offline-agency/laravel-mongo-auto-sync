<?php

namespace Tests\ConsoleFixtures\OtherModels;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * A model registered through the other_models config instead of the model path.
 */
class Gizmo extends MDModel
{
    protected $collection = 'console_fixture_gizmos';

    protected $items = [
        'serial' => [],
    ];
}
