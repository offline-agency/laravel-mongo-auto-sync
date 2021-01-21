<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class User extends MDModel
{
    protected $items = [
        'email' => [],
        'name' => [],
        'surname' => [],
    ];

    protected $mongoRelation = [
        'permissions' => [
            'type' => 'EmbedsMany',
            'mode' => 'classic',
            'model' => 'Tests\Models\MiniPermission',
            'modelTarget' => 'Tests\Models\Permission',
            'methodOnTarget' => 'users',
            'modelOnTarget' => 'Tests\Models\MiniUser',
        ],
        'roles' => [
            'type' => 'EmbedsMany',
            'mode' => 'classic',
            'model' => 'Tests\Models\MiniRole',
            'modelTarget' => 'Tests\Models\Role',
            'methodOnTarget' => 'users',
            'modelOnTarget' => 'Tests\Models\MiniUser',
        ],
    ];

    public function roles()
    {
        return $this->embedsMany('Tests\Models\MiniRole');
    }

    public function permissions()
    {
        return $this->embedsMany('Tests\Models\MiniPermission');
    }
}
