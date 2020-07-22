<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Photo extends MDModel
{
    protected $connection = 'mongodb';
    protected $collection = 'photos';
    protected static $unguarded = true;
}
