<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Group extends MDModel
{
    protected $connection = 'mongodb';
    protected $collection = 'groups';
    protected static $unguarded = true;
}
