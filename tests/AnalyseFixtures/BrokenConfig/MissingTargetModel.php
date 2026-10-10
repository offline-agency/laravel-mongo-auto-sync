<?php

namespace Tests\AnalyseFixtures\BrokenConfig;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Relation with a target whose model class does not exist.
 */
class MissingTargetModel extends MDModel
{
    protected $table = 'analyse_broken';

    protected $mongoRelation = [
        'links' => [
            'type' => 'EmbedsMany',
            'model' => 'Tests\Models\MiniSubItem',
            'modelTarget' => 'Tests\Models\DoesNotExist',
            'methodOnTarget' => 'navigation',
            'modelOnTarget' => 'Tests\Models\MiniNavigation',
        ],
    ];
}
