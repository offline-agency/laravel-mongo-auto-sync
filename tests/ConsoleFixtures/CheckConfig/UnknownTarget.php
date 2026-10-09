<?php

namespace Tests\ConsoleFixtures\CheckConfig;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class UnknownTarget extends MDModel
{
    protected $mongoRelation = [
        'author' => [
            'type' => 'EmbedsOne',
            'model' => 'Tests\Models\MiniUser',
            'modelTarget' => 'Tests\Models\DoesNotExist',
            'methodOnTarget' => 'books',
            'modelOnTarget' => 'Tests\Models\MiniBook',
        ],
    ];
}
