<?php

namespace App\Http\Controllers;

use App\Domain\BotQueryDto;
use App\Domain\FuzzySearch;
use App\Domain\JsMode;
use App\Domain\QueryDto;
use App\Domain\SiteView;
use App\Infrastructure\Service\BotService;
use App\Infrastructure\Service\PackSearch;
use App\Infrastructure\Service\PackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

final class BotController extends Controller
{
    public function index(Request $request, BotService $botService, PackService $packService): View
    {
        if (! SiteView::fromCookie($request->cookie(SiteView::COOKIE))->isLegacy()) {
            return $this->jsView('bots', ['bots' => $botService->listAll()]);
        }

        return $this->legacyBots($request, $botService, $packService);
    }

    public function show(string $name, Request $request, BotService $botService, PackSearch $packSearch): View|RedirectResponse
    {
        $bot = $botService->findByName($name);
        if ($bot === null) {
            abort(404, 'Bot not found');
        }

        if ($request->isMethod('post') && $request->request->has('query')) {
            $query = trim((string) $request->input('query', ''));

            Cookie::queue(FuzzySearch::fromBool($request->boolean('fuzzy'))->cookie());

            $redirectQuery = $query === '' ? [] : ['query' => $query];

            return redirect()->route('bot', array_merge(['name' => $bot->getName()], $redirectQuery));
        }

        $data = ['bot' => $bot];

        if (JsMode::current()->isDisabled()) {
            $search = trim((string) $request->query('query', ''));
            $fuzzy = FuzzySearch::fromCookie($request->cookie(FuzzySearch::COOKIE))->isOn();
            $cursor = $request->input('cursor');

            $result = $packSearch->keysetPage(
                $bot->getId(),
                strlen($search) >= 3 ? $search : '',
                'number',
                'desc',
                100,
                $cursor,
                0,
                $fuzzy
            );

            $data['packs'] = $result['packs'];
            $data['total'] = $result['total'];
            $data['next'] = $result['next'];
            $data['prev'] = $result['prev'];
            $data['searchQuery'] = $search;
            $data['fuzzy'] = $fuzzy;
        }

        return $this->jsView('bot', $data);
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
