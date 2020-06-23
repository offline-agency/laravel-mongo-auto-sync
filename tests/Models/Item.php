<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Item extends MDModel
{
    protected $items = array(
        'name' => array(),
        'code' => array(),
        'price' => array(),
        'quantity' => array(),
        'discount' => array(),
        'taxable_price' => array(),
        'partial_vat' => array(),
        'total_price' => array(),
        'vat_code' => array(),
        'vat_value' => array(),
        'vat_label' => array(),
        'collection_type' => array(),
        'navigation_code' => array()
    );

    protected $mongoRelation = array(
        'navigation' => [
            'type' => 'EmbedsOne',
            'mode' => 'classic',
            'model' => 'Tests\Models\MiniNavigation',
            'modelTarget' => 'Tests\Models\Navigation',
            'methodOnTarget' => 'items',
            'modelOnTarget' => 'Tests\Models\MiniItem'
        ],
    );

    public function navigation()
    {
        return $this->embedsOne('Tests\Models\MiniNavigation');
    }
}
