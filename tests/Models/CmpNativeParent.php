<?php

namespace Tests\Models;

use MongoDB\Laravel\Eloquent\Model as BaseModel;

/**
 * Parent with an embedded and a referenced relation, Native variant (see Compatibility\AggregateParityTest).
 */
class CmpNativeParent extends BaseModel
{
    protected $connection = 'mongodb';

    protected $table = 'cmp_parents';

    protected $guarded = [];

    public function items()
    {
        return $this->embedsMany(CmpNativeItem::class);
    }

    public function notes()
    {
        return $this->hasMany(CmpNativeNote::class, 'parent_id');
    }
}
