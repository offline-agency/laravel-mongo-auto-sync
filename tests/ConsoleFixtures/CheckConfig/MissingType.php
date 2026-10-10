<?php

namespace Tests\ConsoleFixtures\CheckConfig;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class MissingType extends MDModel
{
    protected $mongoRelation = [
        'author' => [
            'model' => 'Tests\Models\MiniUser',
        ],
    ];
}
