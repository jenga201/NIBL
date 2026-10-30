<?php

namespace Tests\Support;

use App\Models\Bot;
use App\Models\Pack;

trait XdccData
{
    protected function createBot(string $name, int $statusId = Bot::STATUS_ACTIVE): Bot
    {
        return Bot::create([
            'name' => $name,
            'url' => 'irc://irc.example.com/'.$name,
            'type' => 'XDCC',
            'owner' => 'tester',
            'status_id' => $statusId,
            'last_seen' => now()->toDateTimeString(),
            'last_processed' => now()->toDateTimeString(),
            'informative' => 0,
            'batchenable' => true,
            'external' => false,
            'parser_id' => 0,
        ]);
    }

    protected function createPack(
        Bot $bot,
        int $number,
        string $name,
        int $episode = 1,
        ?string $modified = null,
        string $size = '1.0G',
        int $sizekbits = 1000,
    ): Pack {
        return Pack::create([
            'bot_id' => $bot->id,
            'number' => $number,
            'name' => $name,
            'size' => $size,
            'sizekbits' => $sizekbits,
            'episode_number' => $episode,
            'last_modified' => $modified ?? now()->toDateTimeString(),
        ]);
    }
}
