<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use OfflineAgency\MongoAutoSync\Eloquent\Model;

/**
 * Model on the package base class, mirrored by CmpNativeModel on the laravel-mongodb base class:
 * the compatibility tests assert both behave the same.
 */
class CmpPackageModel extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'cmp_models';

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'published_at' => 'datetime',
    ];

    protected function fullName(): Attribute
    {
        return Attribute::make(get: fn ($value, array $attributes) => trim(($attributes['first'] ?? '').' '.($attributes['last'] ?? '')));
    }

    public function setNicknameAttribute(?string $value): void
    {
        $this->attributes['nickname'] = $value === null ? null : strtolower($value);
    }
}
