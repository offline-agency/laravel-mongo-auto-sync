<?php

namespace Tests\AnalyseFixtures\Operational;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Stored on a connection that is not configured: reading its documents fails (operational error).
 */
class BrokenConnection extends MDModel
{
    protected $connection = 'not-configured';

    protected $table = 'analyse_broken_connection';

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
