<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Scoped extends MDModel
{
    protected $connection = 'mongodb';
    protected $collection = 'scoped';
    protected $fillable = ['name', 'favorite'];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('favorite', function (Builder $builder) {
            $builder->where('favorite', true);
        });
    }
}
