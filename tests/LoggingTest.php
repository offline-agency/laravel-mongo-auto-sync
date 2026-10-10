<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

test('storeWithSync and updateWithSync log at debug level when logging is enabled', function () {
    config()->set('laravel-mongo-auto-sync.logging', true);

    Log::shouldReceive('debug')->once()->withArgs(fn (string $message) => str_contains($message, 'storeWithSync'));
    Log::shouldReceive('debug')->once()->withArgs(fn (string $message) => str_contains($message, 'updateWithSync'));
    Log::shouldReceive('info')->never();

    $navigation = $this->createNavigation();

    $navigation->updateWithSync(new Request, ['text' => 'logged'], ['request_type' => 'partial']);
});

test('nothing is logged when logging is disabled', function () {
    config()->set('laravel-mongo-auto-sync.logging', false);

    Log::shouldReceive('debug')->never();
    Log::shouldReceive('info')->never();

    $navigation = $this->createNavigation();
    $navigation->updateWithSync(new Request, ['text' => 'not logged'], ['request_type' => 'partial']);
});
