<?php

namespace App\Http\Controllers;

use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\View\View;

final class ApiDocsController extends Controller
{
    #[ExcludeRouteFromDocs]
    public function show(): View
    {
        return view('api');
    }
}
