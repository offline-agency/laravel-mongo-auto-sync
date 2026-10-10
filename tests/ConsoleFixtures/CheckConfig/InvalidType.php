<?php

namespace Tests\ConsoleFixtures\CheckConfig;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class InvalidType extends MDModel
{
    protected $mongoRelation = [
        'author' => [
            'type' => 'BelongsTo',
            'model' => 'Tests\Models\MiniUser',
        ],
    ];
}
