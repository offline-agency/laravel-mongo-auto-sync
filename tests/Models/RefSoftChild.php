<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Referenced child with soft deletes.
 *
 * @property string $id
 * @property string $title
 */
class RefSoftChild extends MDModel
{
    use SoftDeletes;

    protected $table = 'ref_soft_children';

    protected $items = ['title' => []];
}
