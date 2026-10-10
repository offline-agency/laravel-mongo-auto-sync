<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Eloquent\Model as BaseModel;

/**
 * Parent with an embedded and a referenced relation, Package variant (see Compatibility\AggregateParityTest).
 */
class CmpPackageParent extends BaseModel
{
    protected $connection = 'mongodb';

    protected $table = 'cmp_parents';

    protected $guarded = [];

    public function items()
    {
        return $this->embedsMany(CmpPackageItem::class);
    }

    public function notes()
    {
        return $this->hasMany(CmpPackageNote::class, 'parent_id');
    }
}
