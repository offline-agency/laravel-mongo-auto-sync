<?php

use Illuminate\Support\Carbon;
use MongoDB\BSON\UTCDateTime;
use Tests\Models\ElqDotted;

test('setting a dotted date attribute stores it as a nested mongo date', function () {
    $model = new ElqDotted;

    $model->setAttribute('meta.updated_at', Carbon::parse('2024-01-02 03:04:05'));

    $stored = $model->getAttributes()['meta']['updated_at'];
    expect($stored)->toBeInstanceOf(UTCDateTime::class)
        ->and($stored->toDateTime()->format('Y-m-d H:i:s'))->toBe('2024-01-02 03:04:05');
});

test('setting a dotted attribute that is not a date stores the raw value in the sub document', function () {
    $model = new ElqDotted;

    $model->setAttribute('meta.author', 'giacomo');
    $model->setAttribute('meta.created_at', null);

    expect($model->getAttributes()['meta'])->toBe(['author' => 'giacomo', 'created_at' => null]);
});
