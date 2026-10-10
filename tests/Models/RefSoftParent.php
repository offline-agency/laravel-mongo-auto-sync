<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * @property string $id
 * @property string $name
 */
class RefSoftParent extends MDModel
{
    protected $table = 'ref_soft_parents';

    protected $items = ['name' => []];

    protected $mongoRelation = [
        'children' => ['type' => 'HasMany', 'model' => RefSoftChild::class, 'has-target' => false, 'foreignKey' => 'ref_soft_parent_id'],
    ];

    public function children()
    {
        return $this->hasMany(RefSoftChild::class, 'ref_soft_parent_id');
    }
}
