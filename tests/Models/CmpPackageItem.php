<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Eloquent\Model as BaseModel;

class CmpPackageItem extends BaseModel
{
    protected $connection = 'mongodb';

    protected $guarded = [];
}
