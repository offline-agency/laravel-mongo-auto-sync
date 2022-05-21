<?php

namespace Tests\Models;

use Jenssegers\Mongodb\Relations\EmbedsOne;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Plain Fields.
 *
 * @property string $id
 * @property array $text
 * @property string $code
 * @property string $href
 * @property MiniNavigation $navigation
 *
 **/
class SubItem extends MDModel
{
    protected $items = [
        'text' => [
            'is-ml' => true,
        ],
        'code' => [],
        'href' => [],
    ];

    protected $mongoRelation = [
        'navigation' => [
            'type' => 'EmbedsOne',
            'mode' => 'classic',
            'model' => 'Tests\Models\MiniNavigation',
            'modelTarget' => 'Tests\Models\Navigation',
            'methodOnTarget' => 'sub_items',
            'modelOnTarget' => 'Tests\Models\MiniSubItem',
        ],
    ];

    public function navigation(): EmbedsOne
    {
        return $this->embedsOne('Tests\Models\MiniNavigation');
    }
}
