<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Eloquent\Builder;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * A model whose package builder returns the raw aggregation result untouched (no hydration into models).
 */
class HlpScalarRawSample extends MDModel
{
    protected $table = 'hlp_scalar_raw_samples';

    protected $items = [
        'name' => [],
    ];

    /**
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query)
    {
        return new class($query) extends Builder
        {
            public function raw($expression = null)
            {
                return 'not a collection';
            }
        };
    }
}
