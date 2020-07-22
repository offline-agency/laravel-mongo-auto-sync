<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;
use Jenssegers\Mongodb\Eloquent\SoftDeletes;

/**
 * Class Soft.
 * @property \Carbon\Carbon $deleted_at
 */
class Soft extends MDModel
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'soft';
    protected static $unguarded = true;
    protected $dates = ['deleted_at'];
}
