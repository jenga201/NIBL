<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

final class PageController extends Controller
{
    public function about(): View
    {
        return $this->jsView('about');
    }

    public function faq(): View
    {
        return $this->jsView('faq');
    }
}
