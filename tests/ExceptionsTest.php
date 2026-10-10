<?php

use Illuminate\Http\Request;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidConfigurationException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRelationshipException;
use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Exceptions\ModelNotFoundException;
use OfflineAgency\MongoAutoSync\Exceptions\MongoAutoSyncException;
use Tests\Models\Article;

test('storeWithSync without ref_id on an embedded object throws InvalidConfigurationException', function () {
    $navigationWithoutRefId = json_encode([(object) ['code' => 'NAV', 'text' => 'text', 'title' => 'title']]);

    $this->createSubItems(['navigation' => $navigationWithoutRefId]);
})->throws(InvalidConfigurationException::class, 'ref_id attribute not found');

test('a missing request key for a relationship throws InvalidRequestException', function () {
    // full request without the primarycategory key (with or without target the key is required)
    (new Article)->storeWithSync(new Request, [
        'autoincrement_id' => 1,
        'title' => 'title',
        'content' => 'content',
        'slug' => 'slug',
        'visibility' => 'public',
        'status' => 'published',
        'is_deleted' => false,
        'is_active' => true,
    ]);
})->throws(InvalidRequestException::class, "Missing relationship 'primarycategory' in the request.");

test('every custom exception extends MongoAutoSyncException', function (string $exception) {
    expect(is_subclass_of($exception, MongoAutoSyncException::class))->toBeTrue();
})->with([
    InvalidConfigurationException::class,
    InvalidRelationshipException::class,
    InvalidRequestException::class,
    ModelNotFoundException::class,
]);
