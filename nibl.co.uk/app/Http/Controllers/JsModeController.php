<?php

namespace App\Http\Controllers;

use App\Domain\JsMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

final class JsModeController extends Controller
{
    public function switch(string $to): RedirectResponse
    {
        $mode = JsMode::tryFrom($to);

        if ($mode === null) {
            abort(404);
        }

        return redirect()->back()->withCookie(
            Cookie::forever(JsMode::COOKIE, $to)
        );
    }
}
