<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\DefaultMini;

class MiniUser extends DefaultMini
{
    protected $items = [
        'ref_id' => [],
        'name' => [],
        'surname' => [],
        'email' => [],
    ];
}
