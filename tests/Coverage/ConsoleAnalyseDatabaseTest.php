<?php

use Illuminate\Support\Facades\Artisan;
use Tests\ConsoleFixtures\Analyse\Loose;

beforeEach(function () {
    config()->set('laravel-mongo-auto-sync.model_path', __DIR__.'/../ConsoleFixtures/Analyse');
    config()->set('laravel-mongo-auto-sync.model_namespace', 'Tests\ConsoleFixtures\Analyse');
    Loose::truncate();
});

test('reports classes whose relations cannot be read and skips classes that are not models', function () {
    // a relation configuration that cannot be read is an error (#43); foreign classes are not analysed
    expect(Artisan::call('mongo-sync:analyse'))->toBe(1);

    expect(Artisan::output())
        ->toContain('Analysing Tests\ConsoleFixtures\Analyse\ThrowingRelations...')
        ->toContain('Cannot read the relations of Tests\ConsoleFixtures\Analyse\ThrowingRelations: relations unavailable')
        ->not->toContain('Analysing Tests\ConsoleFixtures\Analyse\NotEloquent...')
        ->not->toContain('Target not found')
        ->not->toContain('Inconsistency');
});

test('a document without a value for an embeds-many relation does not stop the analysis', function () {
    $empty = new Loose;
    $empty->save();
    $missingTarget = '0000000000000000000000aa';
    $dangling = new Loose;
    $dangling->links = [['ref_id' => $missingTarget]];
    $dangling->save();

    // a missing target fails the analysis (#43)
    expect(Artisan::call('mongo-sync:analyse'))->toBe(1);

    $rendered = Artisan::output();
    expect($rendered)
        ->toContain("Target not found: Tests\\Models\\SubItem ID {$missingTarget} referenced by Tests\\ConsoleFixtures\\Analyse\\Loose ID {$dangling->id}")
        ->not->toContain("Loose ID {$empty->id}");
});
