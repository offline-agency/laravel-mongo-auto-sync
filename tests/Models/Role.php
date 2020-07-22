<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Role extends MDModel
{
    protected $connection = 'mongodb';
    protected $collection = 'roles';
    protected static $unguarded = true;
}
