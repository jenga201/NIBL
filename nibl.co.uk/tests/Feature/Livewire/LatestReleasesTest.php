<?php

namespace Tests\Feature\Livewire;

use App\Livewire\LatestReleases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\XdccData;
use Tests\TestCase;

final class LatestReleasesTest extends TestCase
{
    use RefreshDatabase;
    use XdccData;

    public function test_renders_newest_packs(): void
    {
        $bot = $this->createBot('Live|Bot');
        $this->createPack($bot, 1, '[Group] Fresh Release - 01.mkv', modified: now()->subMinute()->toDateTimeString());
        $this->createPack($bot, 2, '[Group] Older Release - 02.mkv', modified: now()->subHour()->toDateTimeString());

        Livewire::test(LatestReleases::class)
            ->assertSee('Latest releases')
            ->assertSee('[Group] Fresh Release - 01.mkv')
            ->assertSee('[Group] Older Release - 02.mkv');
    }
}
