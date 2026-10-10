<?php

namespace Tests\ConsoleFixtures\CheckConfig\Nested;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class NestedMissingType extends MDModel
{
    protected $mongoRelation = [
        'author' => [
            'model' => 'Tests\Models\MiniUser',
        ],
    ];
}
