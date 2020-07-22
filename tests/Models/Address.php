<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Address extends MDModel
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;

    public function addresses()
    {
        return $this->embedsMany(Address::class);
    }
}
