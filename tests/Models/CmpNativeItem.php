<?php

namespace Tests\Models;

use MongoDB\Laravel\Eloquent\Model as BaseModel;

class CmpNativeItem extends BaseModel
{
    protected $connection = 'mongodb';

    protected $guarded = [];
}
