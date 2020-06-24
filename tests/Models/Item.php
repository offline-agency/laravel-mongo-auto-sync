<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Item extends MDModel
{
    protected $items = [
        'name' => [],
        'code' => [],
        'price' => [],
        'quantity' => [],
        'discount' => [],
        'taxable_price' => [],
        'partial_vat' => [],
        'total_price' => [],
        'vat_code' => [],
        'vat_value' => [],
        'vat_label' => [],
        'collection_type' => [],
        'navigation_code' => []
    ];

    protected $mongoRelation = [
        'navigation' => [
            'type' => 'EmbedsOne',
            'mode' => 'classic',
            'model' => 'Tests\Models\MiniNavigation',
            'modelTarget' => 'Tests\Models\Navigation',
            'methodOnTarget' => 'sub_items',
            'modelOnTarget' => 'Tests\Models\MiniItem',
        ],
    ];

    public function navigation()
    {
        return $this->embedsOne('Tests\Models\MiniNavigation');
    }
}
