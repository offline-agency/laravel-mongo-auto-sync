<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\DefaultMini;

class MiniCategory extends DefaultMini
{
    protected $items = [
        'ref_id' => [],
        'name' => [],
        'slug' => [],
        'description' => [],
    ];
}
