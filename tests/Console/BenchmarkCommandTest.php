<?php

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Models\Navigation;

use function Termwind\renderUsing;

test('runs with the smallest iteration count', function () {
    $rendered = new BufferedOutput;
    renderUsing($rendered);

    try {
        expect(Artisan::call('mongo-sync:benchmark', ['model' => Navigation::class, 'count' => 1]))->toBe(0);
    } finally {
        renderUsing(null);
    }

    expect($rendered->fetch())->toContain('Insert')->toContain('Delete');
});
