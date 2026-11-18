<?php

namespace Tests\Feature;

use App\Models\Bot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\XdccData;
use Tests\TestCase;

class LegacyBotsTest extends TestCase
{
    use RefreshDatabase;
    use XdccData;

    /** Legacy skin cookie value (App\Domain\SiteView::Legacy). */
    private const LEGACY = 'legacy';

    /** Keep in sync with PackService::PAGE_SIZE. */
    private const PAGE_SIZE = 1000;

    private Bot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->bot = $this->createBot('Web|Bot');
    }

    public function test_legacy_bot_packs_are_ordered_by_number_descending(): void
    {
        $this->createPack($this->bot, 1, '[Group] z-one.mkv');
        $this->createPack($this->bot, 2, '[Group] z-two.mkv');
        $this->createPack($this->bot, 3, '[Group] z-three.mkv');

        $this->withUnencryptedCookie('nibl_view', self::LEGACY)
            ->get('/bots?bot=Web|Bot')
            ->assertOk()
            ->assertSeeInOrder(['z-three.mkv', 'z-two.mkv', 'z-one.mkv']);
    }

    public function test_legacy_bot_paginates_packs_and_shows_total(): void
    {
        for ($n = 1; $n <= self::PAGE_SIZE + 1; $n++) {
            $this->createPack($this->bot, $n, sprintf('[Group] Pack %04d.mkv', $n));
        }

        $this->withUnencryptedCookie('nibl_view', self::LEGACY)
            ->get('/bots?bot=Web|Bot')
            ->assertOk()
            ->assertSee('1001 packs')
            ->assertSeeInOrder(['« First', '← Previous', 'Page 1', 'Next →', 'Last »']);

        $this->withUnencryptedCookie('nibl_view', self::LEGACY)
            ->get('/bots?bot=Web|Bot&page=1')
            ->assertOk()
            ->assertSeeInOrder(['« First', '← Previous', 'Page 2', 'Next →', 'Last »']);
    }

    public function test_legacy_bot_shows_no_pagination_for_a_single_page(): void
    {
        $this->createPack($this->bot, 1, '[Group] only.mkv');

        $this->withUnencryptedCookie('nibl_view', self::LEGACY)
            ->get('/bots?bot=Web|Bot')
            ->assertOk()
            ->assertSee('[Group] only.mkv')
            ->assertDontSee('Previous')
            ->assertDontSee('Next');
    }

    public function test_legacy_bot_search_lists_matching_packs_ordered_by_number(): void
    {
        $this->createPack($this->bot, 1, '[Group] Naruto 01.mkv');
        $this->createPack($this->bot, 2, '[Group] Bleach 02.mkv');
        $this->createPack($this->bot, 3, '[Group] Naruto 03.mkv');

        $this->withUnencryptedCookie('nibl_view', self::LEGACY)
            ->get('/bots?bot=Web|Bot&search=naruto')
            ->assertOk()
            ->assertSeeInOrder(['Naruto 03.mkv', 'Naruto 01.mkv'])
            ->assertDontSee('Bleach 02.mkv');
    }
}