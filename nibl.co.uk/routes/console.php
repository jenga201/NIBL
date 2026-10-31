<?php

use App\Infrastructure\Service\PackSearch;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('meilisearch:sync', function (PackSearch $packSearch): void {
    if (! $packSearch->enabled()) {
        $this->warn('Meilisearch is disabled; nothing to sync.');

        return;
    }

    try {
        $count = $packSearch->sync();
        $this->info("meilisearch:sync re-indexed {$count} bots.");
    } catch (\Throwable $e) {
        report($e);
        $this->error('meilisearch:sync failed: '.$e->getMessage());
    }
})->purpose('Re-index bots whose pack list, status or informative flag changed');
