<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Client extends MDModel
{
    protected $connection = 'mongodb';
    protected $collection = 'clients';
    protected static $unguarded = true;
}
