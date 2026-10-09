<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Child of the referenced (HasMany) relationships of RefAuthor and RefPublisher.
 *
 * @property string $id
 * @property string $title
 * @property string|null $ref_author_id
 * @property string|null $ref_publisher_id
 */
class RefBook extends MDModel
{
    protected $table = 'ref_books';

    protected $items = [
        'title' => [],
    ];
}
