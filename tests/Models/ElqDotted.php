<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Eloquent\Model;

/**
 * Model whose timestamp columns live in a nested "meta" sub-document.
 */
class ElqDotted extends Model
{
    public const CREATED_AT = 'meta.created_at';

    public const UPDATED_AT = 'meta.updated_at';

    protected $connection = 'mongodb';

    protected $table = 'elq_dotted';

    protected static $unguarded = true;
}
