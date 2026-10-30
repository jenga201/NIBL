<?php

namespace Tests\Feature\Api;

use App\Models\Bot;
use App\Models\Pack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\XdccData;
use Tests\TestCase;

class PackApiTest extends TestCase
{
    use RefreshDatabase;
    use XdccData;

    private Bot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = $this->createBot('Test|Bot');
        $this->createPack($this->bot, 1, '[Group] Show Alpha - 01 [1080p].mkv', episode: 1, modified: '2026-01-01 00:00:00');
        $this->createPack($this->bot, 2, '[Group] Show Beta - 02 [1080p].mkv', episode: 2, modified: '2026-01-03 00:00:00');
        $this->createPack($this->bot, 3, '[Group] Show Alpha - 03 [720p].mkv', episode: 3, modified: '2026-01-02 00:00:00');
    }

    public function test_bot_packs_returns_every_pack_of_the_bot(): void
    {
        $this->getJson('/api/packs/'.$this->bot->id)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'bot_id', 'number', 'name', 'size', 'sizekbits', 'episode_number', 'last_modified']],
            ]);
    }

    public function test_bot_packs_of_unknown_bot_returns_not_found(): void
    {
        $this->getJson('/api/packs/999999')->assertNotFound();
    }

    public function test_bot_packs_page_paginates_and_sorts(): void
    {
        $this->getJson('/api/packs/'.$this->bot->id.'/page?page=1&size=2&sort=number&direction=desc')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.number', 3)
            ->assertJsonPath('data.1.number', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_bot_packs_page_filters_by_episode_number(): void
    {
        $this->getJson('/api/packs/'.$this->bot->id.'/page?episode_number=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', 2);
    }

    public function test_legacy_camel_case_episode_parameter_is_accepted(): void
    {
        $this->getJson('/api/packs/'.$this->bot->id.'/page?episodeNumber=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', 3);
    }

    public function test_latest_returns_packs_newest_first(): void
    {
        $this->getJson('/api/latest?size=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.number', 2)
            ->assertJsonPath('data.1.number', 3);
    }

    public function test_search_is_case_insensitive_and_keeps_term_order(): void
    {
        $this->getJson('/api/search?query=show alpha')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/search?query=SHOW ALPHA')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Terms must appear in order: "alpha show" matches nothing.
        $this->getJson('/api/search?query=alpha show')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_paginates(): void
    {
        $this->getJson('/api/search/page?query=show&size=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_search_in_bot(): void
    {
        $this->getJson('/api/search/'.$this->bot->id.'?query=alpha')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/search/'.$this->bot->id.'/page?query=alpha&size=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_search_in_unknown_bot_returns_not_found(): void
    {
        $this->getJson('/api/search/999999?query=alpha')->assertNotFound();
    }

    public function test_search_requires_a_query(): void
    {
        $this->getJson('/api/search')->assertStatus(422);
    }

    public function test_invalid_pagination_parameters_are_rejected(): void
    {
        $this->getJson('/api/packs/'.$this->bot->id.'/page?size='.(Pack::MAX_PAGE_SIZE + 1))->assertStatus(422);
        $this->getJson('/api/packs/'.$this->bot->id.'/page?sort=not_a_column')->assertStatus(422);
        $this->getJson('/api/packs/'.$this->bot->id.'/page?direction=sideways')->assertStatus(422);
        $this->getJson('/api/packs/'.$this->bot->id.'/page?page=0')->assertStatus(422);
    }

    public function test_packs_of_inactive_bots_are_excluded(): void
    {
        $inactive = $this->createBot('Dead|Bot', statusId: Bot::STATUS_ACTIVE + 1);
        $this->createPack($inactive, 9, '[Group] Show Alpha - 09 [1080p].mkv', modified: '2026-02-01 00:00:00');

        $this->getJson('/api/search?query=show alpha')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/latest?size=10')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
