<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Photo extends MDModel
{
    protected $connection = 'mongodb';

    protected $table = 'photos';

    protected static $unguarded = true;
}
