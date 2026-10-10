<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Referenced relationships declared with target keys: destroyWithSync() deletes their children. Such a
 * model cannot go through storeWithSync / updateWithSync (no mini model exists for a HasMany / HasOne),
 * so the tests write it with plain Eloquent saves.
 *
 * @property string $id
 * @property string $name
 */
class RefPublisher extends MDModel
{
    protected $table = 'ref_publishers';

    protected $items = [
        'name' => [],
    ];

    protected $mongoRelation = [
        'books' => [
            'type' => 'HasMany',
            'model' => RefBook::class,
            'foreignKey' => 'ref_publisher_id',
            'modelTarget' => RefBook::class,
            'methodOnTarget' => 'publisher',
            'modelOnTarget' => RefBook::class,
        ],
        'profile' => [
            'type' => 'HasOne',
            'model' => RefProfile::class,
            'foreignKey' => 'ref_publisher_id',
            'modelTarget' => RefProfile::class,
            'methodOnTarget' => 'publisher',
            'modelOnTarget' => RefProfile::class,
        ],
    ];

    public function books()
    {
        return $this->hasMany(RefBook::class, 'ref_publisher_id');
    }

    public function profile()
    {
        return $this->hasOne(RefProfile::class, 'ref_publisher_id');
    }
}
