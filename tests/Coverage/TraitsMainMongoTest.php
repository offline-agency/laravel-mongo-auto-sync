<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Tests\Models\RefBook;

test('storeWithSync returns the in-memory model when a listener deleted the document', function () {
    Event::listen('eloquent.storeWithSync: '.RefBook::class, function (RefBook $book): void {
        $book->delete();
    });
    $book = new RefBook;

    $result = $book->storeWithSync(new Request, ['title' => 'short lived']);

    expect($result)->toBe($book);
    expect($result->title)->toBe('short lived');
    expect(RefBook::find($book->id))->toBeNull();
});

test('updateWithSync returns the in-memory model when a listener deleted the document', function () {
    $book = (new RefBook)->storeWithSync(new Request, ['title' => 'before']);
    Event::listen('eloquent.updateWithSync: '.RefBook::class, function (RefBook $book): void {
        $book->delete();
    });
    $loaded = RefBook::find($book->id);

    $result = $loaded->updateWithSync(new Request, ['title' => 'after']);

    expect($result)->toBe($loaded);
    expect($result->title)->toBe('after');
    expect(RefBook::find($book->id))->toBeNull();
});

test('a model never synced has no options and no target additional data', function () {
    $book = new RefBook;

    expect($book->getOptions())->toBe([]);
    expect($book->getTargetAdditionalData())->toBe([]);

    $book->setOptions(['request_type' => 'partial']);
    $book->setHasPartialRequest();
    expect($book->getHasPartialRequest())->toBeTrue();
});
