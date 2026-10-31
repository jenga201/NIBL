<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use App\Infrastructure\Service\PackCache;
use App\Models\Bot;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('packlists:warm', function (PackCache $packCache): void {
    $store = Cache::store('redis');
    $cursor = (string) $store->get('packcache:cursor', '1970-01-01 00:00:00');

    $bots = Bot::where('last_processed', '>', $cursor)
        ->orderBy('last_processed')
        ->limit(100)
        ->get();

    $max = $cursor;
    foreach ($bots as $bot) {
        $max = max($max, (string) $bot->last_processed);
        $packCache->warm($bot);
    }

    $store->forever('packcache:cursor', $max);
    $this->info(sprintf('packlists:warm warmed %d bots (cursor %s).', $bots->count(), $max));
})->purpose('Pre-warm the pack-list cache for bots processed since the last run');
