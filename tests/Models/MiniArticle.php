<?php


namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\DefaultMini;

class MiniArticle extends DefaultMini
{
    protected $items = [
        'ref_id' => [],
        'title' => [],
        'slug' => [],
        'visibility' => [],
        'status' => [],
        'last_updated_by' => [],

    ];
}
