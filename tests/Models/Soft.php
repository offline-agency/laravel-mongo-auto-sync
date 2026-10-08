<?php

namespace Tests\Models;

use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\SoftDeletes;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Class Soft.
 *
 * @property Carbon $deleted_at
 */
class Soft extends MDModel
{
    use SoftDeletes;

    protected $connection = 'mongodb';

    protected $collection = 'soft';

    protected static $unguarded = true;

    protected $dates = ['deleted_at'];
}
