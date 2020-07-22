<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Class Book.
 * @property string $title
 * @property string $author
 * @property array $chapters
 */
class Book extends MDModel
{
    protected $connection = 'mongodb';
    protected $collection = 'books';
    protected static $unguarded = true;
    protected $primaryKey = 'title';

}
