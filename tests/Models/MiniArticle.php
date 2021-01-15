<?php


namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\DefaultMini;

class MiniArticle extends DefaultMini
{
    protected $items = [
        'ref_id' => [],
        'title' => [
            'is-ml' => true,
            'is-editable' => true,
        ],
        'slug' => [
            'is-ml' => true,
        ],
        'visibility' => [],
        'status' => [],
        'last_updated_by' => [],

    ];
}
