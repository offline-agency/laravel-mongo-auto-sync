<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Eloquent\Model;

/**
 * Embedded model with a non-incrementing key, so Eloquent persists it
 * through Builder::insert() instead of Builder::insertGetId().
 */
class ElqChild extends Model
{
    protected $connection = 'mongodb';

    public $incrementing = false;

    protected static $unguarded = true;
}
