<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $exitCode = Artisan::call('odds:sync');

    if ($exitCode !== 0) {
        return;
    }

    Artisan::call('ai:sync-predictions', [
        '--limit' => 2,
        '--featured-only' => true,
    ]);
})
    ->name('sync-odds-and-ai-predictions')
    ->hourly()
    ->withoutOverlapping();
