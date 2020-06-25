<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\DefaultMini;

/**
 * Plain Fields.
 *
 * @property string $id
 * @property string $ref_id
 * @property string $code
 * @property string $title
 * @property array $text
 *
 * */
class MiniNavigation extends DefaultMini
{
    protected $items = [
        'ref_id' => [],
        'code' => [],
        'text' => [],
        'title' => [
            'is-ml' => true,
        ],
    ];
}
