<?php

namespace Tests\Models;

use MongoDB\Laravel\Eloquent\Model as BaseModel;

class CmpNativeNote extends BaseModel
{
    protected $connection = 'mongodb';

    protected $table = 'cmp_notes';

    protected $guarded = [];
}
