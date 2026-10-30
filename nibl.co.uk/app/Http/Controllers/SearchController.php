<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

final class SearchController extends Controller
{
    public function index(): View
    {
        return view('search');
    }
}
