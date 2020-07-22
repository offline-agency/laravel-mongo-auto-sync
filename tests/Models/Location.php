<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Location extends MDModel
{
    protected $connection = 'mongodb';
    protected $collection = 'locations';
    protected static $unguarded = true;
}
