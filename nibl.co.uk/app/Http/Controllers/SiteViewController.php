<?php

namespace App\Http\Controllers;

use App\Domain\SiteView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

final class SiteViewController extends Controller
{
    public function switch(string $to): RedirectResponse
    {
        $view = SiteView::tryFrom($to);

        if ($view === null) {
            abort(404);
        }

        return redirect('/')->withCookie(
            Cookie::forever(SiteView::COOKIE, $to)
        );
    }
}
