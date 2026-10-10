<?php

namespace Tests\Models;

use Illuminate\Support\Carbon;
use MongoDB\BSON\UTCDateTime;
use OfflineAgency\MongoAutoSync\Http\Models\DefaultMini;

/**
 * Embedded mini model with a MongoDB date (is-md) and a Laravel-managed timestamp.
 *
 * @property string $label
 * @property UTCDateTime|null $due_at
 * @property Carbon $updated_at
 */
class RefMiniSlot extends DefaultMini
{
    protected $items = [
        'label' => [],
        'due_at' => [
            'is-md' => true,
        ],
        'updated_at' => [],
    ];
}
