<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Event extends MDModel
{
    protected $items = [
        'name' => [],
        'starts_at' => [
            'is-md' => true,
        ],
    ];

    protected $mongoRelation = [
        'featured' => [
            'type' => 'EmbedsOne',
            'model' => 'Tests\Models\MiniEvent',
            'has-target' => false,
        ],
    ];

    public function featured()
    {
        return $this->embedsOne('Tests\Models\MiniEvent');
    }
}
