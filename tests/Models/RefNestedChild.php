<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * A referenced child that has a relationship of its own (an embedded list without target).
 *
 * @property string $id
 * @property string $title
 */
class RefNestedChild extends MDModel
{
    protected $table = 'ref_nested_children';

    protected $items = ['title' => []];

    protected $mongoRelation = [
        'notes' => ['type' => 'EmbedsMany', 'model' => MiniCategory::class, 'has-target' => false],
    ];

    public function notes()
    {
        return $this->embedsMany(MiniCategory::class);
    }
}
