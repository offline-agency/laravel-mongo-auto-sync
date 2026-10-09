<?php

namespace Tests\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Native laravel-mongodb twin of CmpPackageModel:
 * the compatibility tests assert both behave the same.
 */
class CmpNativeModel extends Model
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
