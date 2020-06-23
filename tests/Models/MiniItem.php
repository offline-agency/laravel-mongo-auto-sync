<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\DefaultMini;

class MiniItem extends DefaultMini
{
    protected $items = [
        'ref_id' => [],
        'name' => [],
        'code' => [],
    ];
}
