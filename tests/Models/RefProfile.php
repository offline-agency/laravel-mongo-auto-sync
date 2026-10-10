<?php

namespace Tests\Models;

use OfflineAgency\MongoAutoSync\Http\Models\MDModel;

/**
 * Child of the referenced (HasOne) relationships of RefAuthor and RefPublisher.
 *
 * @property string $id
 * @property string $bio
 * @property string|null $ref_author_id
 * @property string|null $ref_publisher_id
 */
class RefProfile extends MDModel
{
    protected $table = 'ref_profiles';

    protected $items = [
        'bio' => [],
    ];
}
