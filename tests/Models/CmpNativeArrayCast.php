<?php

namespace Tests\Models;

use MongoDB\Laravel\Eloquent\Model as BaseModel;

/**
 * Model with the deprecated "array" cast, Native variant (see Compatibility\ArrayCastTest).
 */
class CmpNativeArrayCast extends BaseModel
{
    protected $connection = 'mongodb';

    protected $table = 'cmp_array_casts';

    protected $guarded = [];

    protected $casts = ['tags' => 'array'];
}
