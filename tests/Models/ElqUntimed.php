<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Eloquent\Model;

class ElqUntimed extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'elq_untimed';

    public $timestamps = false;

    protected static $unguarded = true;
}
