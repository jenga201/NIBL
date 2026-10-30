<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

final class PageController extends Controller
{
    public function about(): View
    {
        return view('about');
    }

    public function faq(): View
    {
        return view('faq');
    }
}
