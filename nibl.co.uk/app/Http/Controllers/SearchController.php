<?php

namespace App\Http\Controllers;

use App\Domain\FuzzySearch;
use App\Domain\JsMode;
use App\Infrastructure\Service\PackSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

final class SearchController extends Controller
{
    public function index(Request $request, PackSearch $packSearch): View|RedirectResponse
    {
        if ($request->isMethod('post') && $request->request->has('query')) {
            $query = trim((string) $request->input('query', ''));

            Cookie::queue(FuzzySearch::fromBool($request->boolean('fuzzy'))->cookie());

            return redirect()->route('search', $query === '' ? [] : ['query' => $query]);
        }

        $data = [];

        if (JsMode::current()->isDisabled()) {
            $search = trim((string) $request->query('query', ''));
            $fuzzy = FuzzySearch::fromCookie($request->cookie(FuzzySearch::COOKIE))->isOn();
            $cursor = $request->input('cursor');

            if (strlen($search) >= 3) {
                $result = $packSearch->keysetPage(null, $search, 'number', 'desc', 100, $cursor, 0, $fuzzy);

                $data['packs'] = $result['packs'];
                $data['total'] = $result['total'];
                $data['next'] = $result['next'];
                $data['prev'] = $result['prev'];
            } else {
                $data['packs'] = collect();
                $data['total'] = 0;
                $data['next'] = null;
                $data['prev'] = null;
            }

            $data['searchQuery'] = $search;
            $data['fuzzy'] = $fuzzy;
        }

        return $this->jsView('search', $data);
    }
}
