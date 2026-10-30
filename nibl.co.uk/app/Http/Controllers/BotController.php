<?php

namespace App\Http\Controllers;

use App\Domain\BotQueryDto;
use App\Domain\QueryDto;
use App\Domain\SiteView;
use App\Infrastructure\Service\BotService;
use App\Infrastructure\Service\PackService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BotController extends Controller
{
    public function index(Request $request, BotService $botService, PackService $packService): View
    {
        if (! SiteView::fromCookie($request->cookie(SiteView::COOKIE))->isLegacy()) {
            return view('bots', ['bots' => $botService->listAll()]);
        }

        return $this->legacyBots($request, $botService, $packService);
    }

    public function show(string $name, BotService $botService): View
    {
        $bot = $botService->findByName($name);
        if ($bot === null) {
            abort(404, 'Bot not found');
        }

        return view('bot', ['bot' => $bot]);
    }

    private function legacyBots(Request $request, BotService $botService, PackService $packService): View
    {
        $search = $request->query('search');
        $latest = $request->boolean('latest');
        $searchAll = $request->filled('searchall');
        $botName = $searchAll ? null : $request->query('bot');

        $bot = $botName !== null ? $botService->findByName($botName) : null;

        if ($latest) {
            return view('legacy.results', [
                'results' => $packService->latest(100),
                'bot' => null,
                'searchQuery' => null,
                'searchQueryVague' => false,
            ]);
        }

        if ($bot !== null) {
            $query = QueryDto::fromRequest(['query' => $search ?? '']);
            $vague = $search !== null && trim((string) $search) !== '' && ! $query->isValid();

            $results = $query->isValid()
                ? $packService->searchInBot($bot, BotQueryDto::fromRequest(['query' => $search]))
                : $packService->latestBotPacks($bot, BotQueryDto::fromRequest([]));

            return view('legacy.results', [
                'results' => $results,
                'bot' => $bot,
                'searchQuery' => $search,
                'searchQueryVague' => $vague,
            ]);
        }

        if ($search !== null) {
            $query = QueryDto::fromRequest(['query' => $search]);

            return view('legacy.results', [
                'results' => $query->isValid() ? $packService->search($query) : null,
                'bot' => null,
                'searchQuery' => $search,
                'searchQueryVague' => ! $query->isValid(),
            ]);
        }

        return view('legacy.bots', ['bots' => $botService->listAll()]);
    }
}
