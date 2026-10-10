<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * A parent whose referenced relation carries an extra condition: only active books belong to it.
 *
 * @property string $id
 * @property string $name
 */
class RefCurator extends MDModel
{
    protected $table = 'ref_curators';

    protected $items = ['name' => []];

    protected $mongoRelation = [
        'books' => ['type' => 'HasMany', 'model' => RefBook::class, 'has-target' => false, 'foreignKey' => 'ref_curator_id'],
    ];

    public function books()
    {
        return $this->hasMany(RefBook::class, 'ref_curator_id')->where('status', 'active');
    }
}
