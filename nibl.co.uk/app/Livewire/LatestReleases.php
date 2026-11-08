<?php

namespace App\Livewire;

use App\Domain\JsMode;
use App\Infrastructure\Service\PackService;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The Home page "Latest releases" table. It server-renders the 50 newest
 * packs and re-renders in place when the minute sync broadcasts a
 * {@see \App\Events\PacksUpdated} event, so visiting users see new releases
 * without polling or refreshing.
 */
final class LatestReleases extends Component
{
    #[On('echo:packs,PacksUpdated')]
    public function refreshPacks(): void
    {
        // Re-render triggers a fresh latest() query in render().
    }

    public function render(PackService $packService): View
    {
        return view('livewire.latest-releases', [
            'latest' => $packService->latest(),
            'noJs' => JsMode::current()->isDisabled(),
        ]);
    }
}
