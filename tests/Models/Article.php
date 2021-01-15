<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

class Article extends MDModel
{
    protected $items = [
        'article_id' => [],
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
        'last_updated_by' => [],
    ];

    protected $mongoRelation = [
        'primary_category' => [
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
        ]
    ];

    public function categories()
    {
        return $this->embedsMany('Tests\Models\MiniCategory');
    }
}
