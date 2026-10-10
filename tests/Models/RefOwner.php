<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * A parent whose referenced relation uses a custom local key (code) instead of the id.
 *
 * @property string $id
 * @property string $name
 * @property string $code
 */
class RefOwner extends MDModel
{
    protected $table = 'ref_owners';

    protected $items = ['name' => [], 'code' => []];

    protected $mongoRelation = [
        'books' => ['type' => 'HasMany', 'model' => RefBook::class, 'has-target' => false, 'foreignKey' => 'ref_owner_code', 'localKey' => 'code'],
    ];

    public function books()
    {
        return $this->hasMany(RefBook::class, 'ref_owner_code', 'code');
    }
}
