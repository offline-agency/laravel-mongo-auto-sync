<?php

namespace Tests\ConsoleFixtures\Analyse;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * An EmbedsMany relation without a relation method: documents may hold no value for it at all.
 */
class Loose extends MDModel
{
    protected $collection = 'console_fixture_looses';

    protected $mongoRelation = [
        'links' => [
            'type' => 'EmbedsMany',
            'model' => 'Tests\Models\MiniSubItem',
            'modelTarget' => 'Tests\Models\SubItem',
            'methodOnTarget' => 'navigation',
            'modelOnTarget' => 'Tests\Models\MiniNavigation',
        ],
    ];
}
