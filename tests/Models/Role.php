<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Role extends MDModel
{
    protected $items = [
        'name' => [],
        'label' => [
            'is-ml' => true,
        ],
    ];

    protected $mongoRelation = [
        'permissions' => [
            'type' => 'EmbedsMany',
            'mode' => 'classic',
            'model' => 'Tests\Models\MiniPermission',
            'modelTarget' => 'Tests\Models\Permission',
            'methodOnTarget' => 'roles',
            'modelOnTarget' => 'Tests\Models\MiniRole',
        ],
        'users' => [
            'type' => 'EmbedsMany',
            'mode' => 'classic',
            'model' => 'Tests\Models\MiniUser',
            'modelTarget' => 'Tests\Models\User',
            'methodOnTarget' => 'roles',
            'modelOnTarget' => 'Tests\Models\MiniRole',
        ],
    ];

    public function permissions()
    {
        return $this->embedsMany('Tests\Models\MiniPermission');
    }

    public function users()
    {
        return $this->embedsMany('Tests\Models\MiniUser');
    }
}
