<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\DefaultMini;

class MiniPermission extends DefaultMini
{
    protected $items = [
        'ref_id' => [],
        'name' => [],
        'label' => [
            'is-ml' => true,
        ],
    ];
}
