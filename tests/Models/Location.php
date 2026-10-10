<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Location extends MDModel
{
    protected $connection = 'mongodb';

    protected $table = 'locations';

    protected static $unguarded = true;
}
