<?php

use Illuminate\Support\Facades\DB;
use Tests\Models\CmpNoUpdatedAtNavigation;
use Tests\Models\MiniSubItem;

test('atomic target writes do not add an empty key when the model has no updated_at column', function () {
    DB::table('navigation')->truncate();
    $target = new CmpNoUpdatedAtNavigation;
    $target->text = 'no updated_at';
    $target->sub_items = [];
    $target->save();

    $mini = new MiniSubItem;
    $mini->ref_id = '650000000000000000000001';
    $mini->code = 'c';
    $target->updateRelationWithSync($mini, 'sub_items', false, true);
    $target->removeRelationWithSync('sub_items', '650000000000000000000001', false, true);

    $raw = DB::connection('mongodb')->getCollection('navigation')->findOne([], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
    expect(array_keys($raw))->not->toContain('')->not->toContain('updated_at');
    expect($raw['sub_items'])->toBe([]);
});
