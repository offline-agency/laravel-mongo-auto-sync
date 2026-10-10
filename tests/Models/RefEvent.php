<?php

namespace Tests\Models;

use MongoDB\BSON\UTCDateTime;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Plain MongoDB date (is-md) field and embedded relationships without target.
 *
 * @property string $id
 * @property string $name
 * @property UTCDateTime|null $starts_at
 */
class RefEvent extends MDModel
{
    protected $table = 'ref_events';

    protected $items = [
        'name' => [],
        'starts_at' => [
            'is-md' => true,
        ],
    ];

    protected $mongoRelation = [
        'main_slot' => [
            'type' => 'EmbedsOne',
            'model' => RefMiniSlot::class,
            'has-target' => false,
        ],
        'slots' => [
            'type' => 'EmbedsMany',
            'model' => RefMiniSlot::class,
            'has-target' => false,
        ],
    ];

    public function main_slot()
    {
        return $this->embedsOne(RefMiniSlot::class);
    }

    public function slots()
    {
        return $this->embedsMany(RefMiniSlot::class);
    }
}
