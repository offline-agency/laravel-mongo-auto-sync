<?php

namespace Tests\AnalyseFixtures\Unsupported;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * HasMany with target keys: the analyser does not support it and must report it.
 */
class ReferencedWithTarget extends MDModel
{
    protected $table = 'analyse_referenced';

    protected $mongoRelation = [
        'books' => [
            'type' => 'HasMany',
            'model' => 'Tests\Models\RefBook',
            'modelTarget' => 'Tests\Models\RefBook',
            'methodOnTarget' => 'publisher',
            'modelOnTarget' => 'Tests\Models\RefBook',
        ],
    ];
}
