<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Article extends MDModel
{
    protected $items = [
        'autoincrement_id' => [],
        'title' => [
            'is-ml' => true,
            'is-editable' => true,
        ],
        'content' => [
            'is-ml' => true,
        ],
        'slug' => [
            'is-ml' => true,
        ],
        'visibility' => [],
        'status' => [],
        'is_deleted' => [],
        'is_active' => [],
    ];

    protected $mongoRelation = [
        'primarycategory' => [
            'type' => 'EmbedsOne',
            'model' => 'Tests\Models\MiniCategory',
            'has-target' => false,
        ],
        'categories' => [
            'type' => 'EmbedsMany',
            'mode' => 'classic',
            'model' => 'Tests\Models\MiniCategory',
            'modelTarget' => 'Tests\Models\Category',
            'methodOnTarget' => 'articles',
            'modelOnTarget' => 'Tests\Models\MiniArticle',
        ],
    ];

    public function categories()
    {
        return $this->embedsMany('Tests\Models\MiniCategory');
    }

    public function primarycategory()
    {
        return $this->embedsOne('Tests\Models\MiniCategory');
    }
}
