<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Eloquent\Model;

/**
 * Embedded model with mass assignment protection (see Compatibility\ReviewFindingsTest).
 */
class CmpGuardedItem extends Model
{
    protected $connection = 'mongodb';

    protected $fillable = ['name'];
}
