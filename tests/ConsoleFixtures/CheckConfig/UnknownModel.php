<?php

namespace Tests\ConsoleFixtures\CheckConfig;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class UnknownModel extends MDModel
{
    protected $mongoRelation = [
        'author' => [
            'type' => 'EmbedsOne',
            'model' => 'Tests\Models\DoesNotExist',
        ],
    ];
}
