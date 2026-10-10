<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Eloquent\Model as BaseModel;

/**
 * Model with the deprecated "array" cast, Package variant (see Compatibility\ArrayCastTest).
 */
class CmpPackageArrayCast extends BaseModel
{
    protected $connection = 'mongodb';

    protected $table = 'cmp_array_casts';

    protected $guarded = [];

    protected $casts = ['tags' => 'array'];
}
