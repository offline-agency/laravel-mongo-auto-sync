<?php

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\ConsoleFixtures\Analyse\Loose;

use function Termwind\renderUsing;

beforeEach(function () {
    $this->rendered = new BufferedOutput;
    renderUsing($this->rendered);
    config()->set('laravel-mongo-auto-sync.model_path', __DIR__.'/../ConsoleFixtures/Analyse');
    config()->set('laravel-mongo-auto-sync.model_namespace', 'Tests\ConsoleFixtures\Analyse');
    Loose::truncate();
});

afterEach(function () {
    renderUsing(null);
});

test('skips classes whose relations cannot be read or that are not eloquent models', function () {
    expect(Artisan::call('mongo-sync:analyse'))->toBe(0);

    expect($this->rendered->fetch())
        ->toContain('Analysing Tests\ConsoleFixtures\Analyse\ThrowingRelations...')
        ->toContain('Analysing Tests\ConsoleFixtures\Analyse\NotEloquent...')
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

    expect(Artisan::call('mongo-sync:analyse'))->toBe(0);

    $rendered = $this->rendered->fetch();
    expect($rendered)
        ->toContain("Target not found: Tests\\Models\\SubItem ID {$missingTarget} referenced by Tests\\ConsoleFixtures\\Analyse\\Loose ID {$dangling->id}")
        ->not->toContain("Loose ID {$empty->id}");
});
