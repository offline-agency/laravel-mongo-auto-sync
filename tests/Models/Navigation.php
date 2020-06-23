<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Extensions\MongoCollection;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Plain Fields.
 *
 * @property string $id
 * @property string $text
 * @property string $code
 * @property string $href
 * @property string $date
 * @property string $target
 * @property array $title
 *
 *
 * Relationships
 *
 * @property MongoCollection $sub_items
 *
 **/
class Navigation extends MDModel
{
    protected $collection = 'navigation';

    protected $dates = ['date'];

    protected $items = [
        'text' => [],
        'code' => [],
        'href' => [],
        'date' => [
            'is-carbon-date' => true,
        ],
        'target' => [],
        'title' => [
            'is-ml' => true,
        ],
    ];

    protected $mongoRelation = [
        'sub_items' => [
            'type' => 'EmbedsMany',
            'mode' => 'classic',
            'model' => 'Tests\Models\MiniSubItem',
            'modelTarget' => 'Tests\Models\SubItem',
            'methodOnTarget' => 'navigation',
            'modelOnTarget' => 'Tests\Models\MiniNavigation',
        ],
    ];

    public function sub_items()
    {
        return $this->embedsMany('Tests\Models\MiniItem');
    }
}
