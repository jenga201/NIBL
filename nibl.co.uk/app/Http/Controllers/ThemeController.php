<?php

namespace App\Http\Controllers;

use App\Domain\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

final class ThemeController extends Controller
{
    public function switch(string $to): RedirectResponse
    {
        $theme = Theme::tryFrom($to);

        if ($theme === null) {
            abort(404);
        }

        return redirect()->back()->withCookie(
            Cookie::forever(Theme::COOKIE, $to)
        );
    }
}
