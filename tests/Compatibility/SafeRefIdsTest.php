<?php

use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;

test('payloads that are not a JSON list are left to the relationship parser', function (mixed $value) {
    SyncHelper::assertSafeRefIds('navigation', $value);

    expect(true)->toBeTrue();
})->with([
    'array (not a string)' => [[['ref_id' => ['$ne' => null]]]],
    'JSON object' => ['{"ref_id": {"$ne": null}}'],
    'not JSON' => ['not json'],
    'null' => [null],
]);

test('a JSON list with an operator ref_id is rejected', function () {
    SyncHelper::assertSafeRefIds('navigation', '[{"ref_id": {"$ne": null}}]');
})->throws(InvalidRequestException::class, "Invalid ref_id in relationship 'navigation': expected a string or integer id, got stdClass.");
