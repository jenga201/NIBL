<?php

namespace App\Jobs;

use App\Infrastructure\Service\PackSearch;
use App\Models\Bot;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SyncBot implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Re-indexing the largest (~220k-pack) bot takes minutes; don't let the
     * default 60s queue timeout kill or retry it mid-flight.
     */
    public int $timeout = 1800;

    public int $tries = 1;

    /** Auto-release the uniqueness lock after an hour even if a worker crashes. */
    public int $uniqueFor = 3600;

    public function __construct(public readonly int $botId)
    {
    }

    public function uniqueId(): string
    {
        return 'sync-bot-'.$this->botId;
    }

    public function handle(PackSearch $packSearch): void
    {
        $bot = Bot::find($this->botId);
        if ($bot === null) {
            return; // deleted between dispatch and run
        }

        $active = (int) $bot->status_id === Bot::STATUS_ACTIVE && (int) $bot->informative === 0;

        if ($active) {
            $packSearch->indexBot($bot);
        } else {
            $packSearch->deleteBot($this->botId);
        }
    }

    /**
     * On failure, reset the sync watermark so the next minute's sync
     * re-dispatches this bot instead of leaving the index drifted.
     */
    public function failed(?\Throwable $e): void
    {
        DB::table('meilisearch_sync')->where('bot_id', $this->botId)->delete();
    }
}
