<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\DefaultMini;

class MiniEvent extends DefaultMini
{
    protected $items = [
        'ref_id' => [],
        'name' => [],
        'starts_at' => [
            'is-md' => true,
        ],
        'created_at' => [],
        'updated_at' => [],
    ];
}
