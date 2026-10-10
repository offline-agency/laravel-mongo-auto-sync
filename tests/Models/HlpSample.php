<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class HlpSample extends MDModel
{
    protected $table = 'hlp_samples';

    protected $items = [
        'name' => [],
    ];
}
