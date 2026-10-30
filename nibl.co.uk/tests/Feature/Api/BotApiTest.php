<?php

namespace Tests\Feature\Api;

use App\Models\Bot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\XdccData;
use Tests\TestCase;

class BotApiTest extends TestCase
{
    use RefreshDatabase;
    use XdccData;

    public function test_bot_list_only_contains_active_bots_with_packs(): void
    {
        $listed = $this->createBot('Listed|Bot');
        $this->createPack($listed, 1, '[Group] Listed Show - 01.mkv');

        $inactive = $this->createBot('Inactive|Bot', statusId: 2);
        $this->createPack($inactive, 1, '[Group] Inactive Show - 01.mkv');

        $this->createBot('Empty|Bot');

        $this->getJson('/api/bots')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Listed|Bot')
            ->assertJsonPath('data.0.pack_count', 1)
            ->assertJsonPath('data.0.batch_enable', true)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'owner', 'last_processed', 'batch_enable', 'pack_count']],
            ]);
    }

    public function test_single_bot_can_be_retrieved(): void
    {
        $bot = $this->createBot('Solo|Bot');
        $this->createPack($bot, 1, '[Group] Solo Show - 01.mkv');
        $this->createPack($bot, 2, '[Group] Solo Show - 02.mkv');

        $this->getJson('/api/bots/'.$bot->id)
            ->assertOk()
            ->assertJsonPath('data.id', $bot->id)
            ->assertJsonPath('data.name', 'Solo|Bot')
            ->assertJsonPath('data.pack_count', 2);
    }

    public function test_unknown_bot_returns_not_found(): void
    {
        $this->getJson('/api/bots/999999')->assertNotFound();
    }

    public function test_active_bot_without_packs_is_retrievable(): void
    {
        $bot = $this->createBot('Lonely|Bot');

        $this->getJson('/api/bots/'.$bot->id)
            ->assertOk()
            ->assertJsonPath('data.id', $bot->id)
            ->assertJsonPath('data.pack_count', 0);
    }

    public function test_inactive_bot_returns_not_found(): void
    {
        $bot = $this->createBot('Gone|Bot', statusId: Bot::STATUS_ACTIVE + 1);
        $this->createPack($bot, 1, '[Group] Gone Show - 01.mkv');

        $this->getJson('/api/bots/'.$bot->id)->assertNotFound();
    }
}
