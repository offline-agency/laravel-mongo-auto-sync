<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Permission extends MDModel
{
    protected $items = [
        'name' => [],
        'label' => [
            'is-ml' => true,
        ],
    ];

    protected $mongoRelation = [
        'roles' => [
            'type' => 'EmbedsMany',
            'mode' => 'classic',
            'model' => 'Tests\Models\MiniRole',
            'modelTarget' => 'Tests\Models\Role',
            'methodOnTarget' => 'permissions',
            'modelOnTarget' => 'Tests\Models\MiniPermission',
        ],
        'users' => [
            'type' => 'EmbedsMany',
            'mode' => 'classic',
            'model' => 'Tests\Models\MiniUser',
            'modelTarget' => 'Tests\Models\User',
            'methodOnTarget' => 'permissions',
            'modelOnTarget' => 'Tests\Models\MiniPermission',
        ]
    ];

    public function roles()
    {
        return $this->embedsMany('Tests\Models\Role');
    }

    public function users()
    {
        return $this->embedsMany('Tests\Models\User');
    }
}
