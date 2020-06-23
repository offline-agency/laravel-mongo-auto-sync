<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\DefaultMini;

/**
 * Plain Fields.
 *
 * @property string $id
 * @property string $ref_id
 * @property array $text
 * @property string $code
 * @property string $href
 *
 * */
class MiniSubItem extends DefaultMini
{
    protected $items = [
        'ref_id' => [],
        'text' => [
            'is-ml' => true,
        ],
        'code' => [],
        'href' => [],
    ];
}
