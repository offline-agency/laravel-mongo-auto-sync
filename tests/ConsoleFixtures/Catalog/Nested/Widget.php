<?php

namespace Tests\ConsoleFixtures\Catalog\Nested;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * A model in a subdirectory of the model path (PSR-4 sub-namespace).
 */
class Widget extends MDModel
{
    protected $collection = 'console_fixture_widgets';

    protected $items = [
        'code' => [],
    ];
}
