<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Article extends MDModel
{
    protected $collection = 'article';

    protected $items = [
        'title' => [],
    ];
}
