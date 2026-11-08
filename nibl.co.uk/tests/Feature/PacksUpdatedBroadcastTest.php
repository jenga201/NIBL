<?php

namespace Tests\Feature;

use App\Events\PacksUpdated;
use App\Infrastructure\Service\PackSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Mockery;
use Tests\Support\XdccData;
use Tests\TestCase;

final class PacksUpdatedBroadcastTest extends TestCase
{
    use RefreshDatabase;
    use XdccData;

    protected function setUp(): void
    {
        parent::setUp();

        $index = Mockery::mock(Indexes::class);
        $index->allows()->updateFilterableAttributes(Mockery::any());
        $index->allows()->updateSortableAttributes(Mockery::any());
        $index->allows()->updateSearchableAttributes(Mockery::any());
        $index->allows()->updatePagination(Mockery::any());
        $index->allows()->updateTypoTolerance(Mockery::any());
        $index->allows()->deleteDocuments(Mockery::any());

        $client = Mockery::mock(Client::class);
        $client->allows()->index(Mockery::any())->andReturn($index);

        $this->app->instance(Client::class, $client);
    }

    public function test_broadcasts_when_a_bot_changed(): void
    {
        Event::fake([PacksUpdated::class]);

        // No sync watermark row yet, so the bot is treated as dirty.
        $this->createBot('Changed|Bot');

        $this->app->make(PackSearch::class)->sync();

        Event::assertDispatched(PacksUpdated::class);
    }

    public function test_broadcasts_once_per_sync_run_when_anything_changed(): void
    {
        Event::fake([PacksUpdated::class]);

        $this->createBot('Changed|Bot A');
        $this->createBot('Changed|Bot B');

        $this->app->make(PackSearch::class)->sync();

        Event::assertDispatchedTimes(PacksUpdated::class, 1);
    }

    public function test_does_not_broadcast_when_nothing_changed(): void
    {
        Event::fake([PacksUpdated::class]);

        $bot = $this->createBot('Stable|Bot');

        DB::table('meilisearch_sync')->insert([
            'bot_id' => $bot->id,
            'last_processed' => $bot->last_processed,
            'status_id' => $bot->status_id,
            'informative' => $bot->informative,
        ]);

        $this->app->make(PackSearch::class)->sync();

        Event::assertNotDispatched(PacksUpdated::class);
    }
}
