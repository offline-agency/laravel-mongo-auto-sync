<?php

namespace Tests\Models;

use MongoDB\Laravel\Eloquent\Builder as MongoDbEloquentBuilder;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * A model whose builder is the plain mongodb/laravel-mongodb one instead of the package builder.
 */
class HlpPlainBuilderSample extends MDModel
{
    protected $table = 'hlp_plain_builder_samples';

    protected $items = [
        'name' => [],
    ];

    /**
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query)
    {
        return new MongoDbEloquentBuilder($query);
    }
}
