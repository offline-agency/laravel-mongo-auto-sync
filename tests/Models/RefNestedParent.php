<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * @property string $id
 * @property string $name
 */
class RefNestedParent extends MDModel
{
    protected $table = 'ref_nested_parents';

    protected $items = ['name' => []];

    protected $mongoRelation = [
        'children' => ['type' => 'HasMany', 'model' => RefNestedChild::class, 'has-target' => false, 'foreignKey' => 'ref_nested_parent_id'],
    ];

    public function children()
    {
        return $this->hasMany(RefNestedChild::class, 'ref_nested_parent_id');
    }
}
