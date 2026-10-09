<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Referenced relationships (HasMany / HasOne) handled by storeWithSync / updateWithSync: the children are
 * separate documents holding the foreign key. They need 'has-target' => false (mini models are only built
 * for embedded relationships).
 *
 * @property string $id
 * @property string $name
 */
class RefAuthor extends MDModel
{
    protected $table = 'ref_authors';

    protected $items = [
        'name' => [],
    ];

    protected $mongoRelation = [
        'books' => [
            'type' => 'HasMany',
            'model' => RefBook::class,
            'has-target' => false,
            'foreignKey' => 'ref_author_id',
        ],
        'profile' => [
            'type' => 'HasOne',
            'model' => RefProfile::class,
            'has-target' => false,
            'foreignKey' => 'ref_author_id',
        ],
    ];

    public function books()
    {
        return $this->hasMany(RefBook::class, 'ref_author_id');
    }

    public function profile()
    {
        return $this->hasOne(RefProfile::class, 'ref_author_id');
    }
}
