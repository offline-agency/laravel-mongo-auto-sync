<?php

use OfflineAgency\MongoAutoSync\Exceptions\InvalidRequestException;
use OfflineAgency\MongoAutoSync\Helpers\SyncHelper;

test('strings that are not a JSON list or object are left to the relationship parser', function (mixed $value) {
    SyncHelper::assertSafeRefIds('navigation', $value);

    expect(true)->toBeTrue();
})->with([
    'not JSON' => ['not json'],
    'empty string' => [''],
    'JSON null' => ['null'],
    'null' => [null],
]);

test('documents of a JSON list or object with plain ids are accepted', function (string $payload) {
    SyncHelper::assertSafeRefIds('navigation', $payload);

    expect(true)->toBeTrue();
})->with([
    'list' => ['[{"ref_id": "650000000000000000000001"}, {"ref_id": null}, {"text": "no id"}]'],
    'object' => ['{"first": {"ref_id": "plain-id"}}'],
]);

test('payloads that are not a JSON string, or that hold a non plain id, are rejected', function (mixed $payload, string $message) {
    expect(fn () => SyncHelper::assertSafeRefIds('navigation', $payload))->toThrow(InvalidRequestException::class, $message);
})->with([
    'array' => [[['ref_id' => ['$ne' => null]]], "Invalid payload for relationship 'navigation': expected a JSON string, got array."],
    'operator in a list' => ['[{"ref_id": {"$ne": null}}]', "Invalid ref_id in relationship 'navigation': expected a string id, got stdClass."],
    'operator in an object' => ['{"x": {"ref_id": {"$ne": null}}}', "Invalid ref_id in relationship 'navigation': expected a string id, got stdClass."],
    'integer' => ['[{"ref_id": 7}]', "Invalid ref_id in relationship 'navigation': expected a string id, got int."],
]);

test('referenced relationships check id and _id', function () {
    SyncHelper::assertSafeRefIds('books', '[{"id": "650000000000000000000001", "ref_id": {"ignored": true}}]', SyncHelper::idKeysFor('HasMany'));

    expect(fn () => SyncHelper::assertSafeRefIds('books', '[{"_id": {"$gt": ""}}]', SyncHelper::idKeysFor('HasMany')))
        ->toThrow(InvalidRequestException::class, "Invalid _id in relationship 'books': expected a string id, got stdClass.");
});
