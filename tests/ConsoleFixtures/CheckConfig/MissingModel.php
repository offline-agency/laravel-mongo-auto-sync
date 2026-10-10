<?php

namespace Tests\ConsoleFixtures\CheckConfig;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class MissingModel extends MDModel
{
    protected $mongoRelation = [
        'author' => [
            'type' => 'EmbedsOne',
        ],
    ];
}
