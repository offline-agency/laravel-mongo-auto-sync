<?php

use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;
use Tests\Models\MiniSubItem;
use Tests\Models\Navigation;
use Tests\Models\SubItem;

function miniSubItem(string $refId, string $code): MiniSubItem
{
    $mini = new MiniSubItem;
    $mini->ref_id = $refId;
    $mini->text = ['en' => $code];
    $mini->code = $code;
    $mini->href = 'https://example.com/'.$code;

    return $mini;
}

test('concurrent upserts on the same target keep both entries', function () {
    $navigation = $this->createNavigation();
    $a = (string) new ObjectId;
    $b = (string) new ObjectId;

    // two workers loaded the same target before either one wrote it
    $first = Navigation::find($navigation->id);
    $second = Navigation::find($navigation->id);
    $first->updateRelationWithSync(miniSubItem($a, 'A'), 'sub_items', false, true);
    $second->updateRelationWithSync(miniSubItem($b, 'B'), 'sub_items', false, true);

    $refIds = collect(Navigation::find($navigation->id)->sub_items)->map(fn ($mini) => (string) $mini->ref_id)->all();
    expect($refIds)->toContain($a)->toContain($b)->toHaveCount(2);
});

test('concurrent removal and upsert on the same target keep the upsert', function () {
    $a = (string) new ObjectId;
    $b = (string) new ObjectId;
    $navigation = $this->createNavigation();
    Navigation::find($navigation->id)->updateRelationWithSync(miniSubItem($a, 'A'), 'sub_items', false, true);

    $first = Navigation::find($navigation->id);
    $second = Navigation::find($navigation->id);
    $first->removeRelationWithSync('sub_items', $a, false, true);
    $second->updateRelationWithSync(miniSubItem($b, 'B'), 'sub_items', false, true);

    $refIds = collect(Navigation::find($navigation->id)->sub_items)->map(fn ($mini) => (string) $mini->ref_id)->all();
    expect($refIds)->toBe([$b]);
});

test('upserting the same source twice keeps one entry with the latest values', function () {
    $navigation = $this->createNavigation();
    $a = (string) new ObjectId;

    Navigation::find($navigation->id)->updateRelationWithSync(miniSubItem($a, 'old'), 'sub_items', false, true);
    Navigation::find($navigation->id)->updateRelationWithSync(miniSubItem($a, 'new'), 'sub_items', false, true);

    $entries = collect(Navigation::find($navigation->id)->sub_items)->filter(fn ($mini) => SyncHelper::sameId($mini->ref_id, $a));
    expect($entries)->toHaveCount(1);
    expect($entries->first()->code)->toBe('new');
});

test('a partial update of a model with an empty EmbedsOne target relation does not crash', function () {
    $subItem = new SubItem;
    $subItem->code = 'c';
    $subItem->save();

    $updated = SubItem::find($subItem->id)->updateWithSync(new Request, ['code' => 'd'], ['request_type' => 'partial']);

    expect($updated->code)->toBe('d');
});
