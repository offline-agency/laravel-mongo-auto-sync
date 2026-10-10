<?php

namespace Tests\ConsoleFixtures\Catalog;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * A model without relations.
 */
class Plain extends MDModel
{
    protected $collection = 'console_fixture_plains';

    protected $items = [
        'label' => [],
    ];
}
