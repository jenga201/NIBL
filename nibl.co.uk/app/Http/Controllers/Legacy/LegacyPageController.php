<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class LegacyPageController extends Controller
{
    public function guides(): View
    {
        return view('legacy.guides');
    }

    public function links(): View
    {
        return view('legacy.links');
    }

    public function staff(): View
    {
        return view('legacy.staff');
    }

    public function irc(): View
    {
        return view('legacy.irc');
    }
}
