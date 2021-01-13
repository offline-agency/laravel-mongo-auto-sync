<?php


namespace Tests\Models;


use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Plain Fields.
 *
 * @property string $id
 * @property array $text
 * @property string $name
 * @property string $autoincrement_id
 *
 **/
class ModelTest extends MDModel
{
    protected $items = [
        'text' => [
            'is-ml' => true,
        ],
        'name' => [],
        'autoincrement_id'=>[]
    ];
}
