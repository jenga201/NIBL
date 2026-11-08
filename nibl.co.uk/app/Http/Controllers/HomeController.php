<?php

namespace App\Http\Controllers;

use App\Domain\SiteView;
use App\Infrastructure\Service\SiteNewsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function index(Request $request, SiteNewsService $newsService): View
    {
        if (SiteView::fromCookie($request->cookie(SiteView::COOKIE))->isLegacy()) {
            return view('legacy.news');
        }

        return view('index', [
            'news' => $newsService->getLatestNews(),
        ]);
    }
}
