<?php

namespace Tests\Models;

use Jenssegers\Mongodb\Helpers\EloquentBuilder;

class Article extends EloquentBuilder
{
    protected $collection = 'article';

    protected $items = [
        'title' => []
    ];
}
