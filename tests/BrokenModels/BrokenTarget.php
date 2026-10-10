<?php

namespace Tests\BrokenModels;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Deliberately broken: the relation has a target (has-target defaults to true) but no modelTarget.
 * Only used by tests/Console/CheckModelConfigTest.php.
 */
class BrokenTarget extends MDModel
{
    protected $items = [
        'name' => [],
    ];

    protected $mongoRelation = [
        'author' => [
            'type' => 'EmbedsOne',
            'model' => 'Tests\Models\MiniUser',
            'methodOnTarget' => 'books',
            'modelOnTarget' => 'Tests\Models\MiniUser',
        ],
    ];
}
