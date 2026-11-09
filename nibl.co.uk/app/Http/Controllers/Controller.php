<?php

namespace App\Http\Controllers;

use App\Domain\JsMode;
use Illuminate\View\View;

abstract class Controller
{
    /**
     * Render the JS-enabled view, or its `_no_js` sibling when the visitor has
     * JavaScript disabled. Centralises the JS/no-JS choice so views carry no
     * `@if ($noJs)` branching and every view has one `_no_js` counterpart.
     */
    final protected function jsView(string $view, array $data = []): View
    {
        return view(
            JsMode::current()->isDisabled() ? $view.'_no_js' : $view,
            $data,
        );
    }
}
