<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Category extends MDModel
{
    protected $items = [
        'category_id' => [],
        'name' => [
            'is-ml' => true,
        ],
        'slug' => [
            'is-ml' => true,
        ],
        'description' => [
            'is-ml' => true,
        ],
    ];

    protected $mongoRelation = [
        'articles' => [
            'type' => 'EmbedsMany',
            'model' => 'Tests\Models\MiniArticle',
            'mode' => 'classic',
            'modelTarget' => 'Tests\Models\Article',
            'methodOnTarget' => 'categories',
            'modelOnTarget' => 'Tests\Models\MiniCategory',
        ],
    ];

    public function articles()
    {
        return $this->embedsMany('Tests\Models\MiniArticle');
    }
}
