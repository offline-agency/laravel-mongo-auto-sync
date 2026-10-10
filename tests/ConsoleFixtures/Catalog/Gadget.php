<?php

namespace Tests\ConsoleFixtures\Catalog;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Plain and multi-language fields plus relations, used by model-doc:generate and drop:collection tests.
 */
class Gadget extends MDModel
{
    protected $collection = 'console_fixture_gadgets';

    protected $items = [
        'name' => [],
        'title' => [
            'is-ml' => true,
        ],
    ];

    protected $mongoRelation = [
        'owner' => [
            'type' => 'EmbedsOne',
            'model' => 'App\Models\MiniUser',
            'has-target' => false,
        ],
        'parts' => [
            'type' => 'EmbedsMany',
            'model' => 'Tests\Models\MiniItem',
            'has-target' => false,
        ],
    ];
}
