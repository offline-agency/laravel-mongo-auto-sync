<?php

namespace Tests\Models;

use Jenssegers\Mongodb\Eloquent\SoftDeletes;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

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
