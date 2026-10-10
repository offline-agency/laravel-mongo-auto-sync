<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Eloquent\Model as BaseModel;

class CmpPackageNote extends BaseModel
{
    protected $connection = 'mongodb';

    protected $table = 'cmp_notes';

    protected $guarded = [];
}
