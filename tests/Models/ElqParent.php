<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Eloquent\Model;
use OfflineAgency\MongoAutoSync\Relationships\EmbedsMany;
use OfflineAgency\MongoAutoSync\Relationships\EmbedsOne;

class ElqParent extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'elq_parents';

    protected static $unguarded = true;

    public function children(): EmbedsMany
    {
        return $this->embedsMany(ElqChild::class);
    }

    public function profile(): EmbedsOne
    {
        return $this->embedsOne(ElqChild::class);
    }
}
