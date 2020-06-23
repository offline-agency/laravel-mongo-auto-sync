<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\DefaultMini;

class MiniItem extends DefaultMini
{
    protected $items = array(
        'ref_id' => array(),
        'name' => array(),
        'code' => array(),
    );
}
