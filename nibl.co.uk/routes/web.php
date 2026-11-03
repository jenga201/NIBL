<?php

use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JsModeController;
use App\Http\Controllers\Legacy\LegacyPageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SiteViewController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('index');
Route::get('/bots', [BotController::class, 'index'])->name('bots');
Route::match(['get', 'post'], '/bots/{name}', [BotController::class, 'show'])->name('bot');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::match(['get', 'post'], '/search', [SearchController::class, 'index'])->name('search');
Route::get('/api', [ApiDocsController::class, 'show'])->name('api.docs');

// Legacy (nibl.co.uk-old) pages — always render the legacy layout.
Route::get('/guides', [LegacyPageController::class, 'guides'])->name('legacy.guides');
Route::get('/links', [LegacyPageController::class, 'links'])->name('legacy.links');
Route::get('/staff', [LegacyPageController::class, 'staff'])->name('legacy.staff');
Route::get('/irc', [LegacyPageController::class, 'irc'])->name('legacy.irc');

// Switch between the modern and legacy site views.
Route::get('/site/view/{to}', [SiteViewController::class, 'switch'])
    ->whereIn('to', ['modern', 'legacy'])
    ->name('site.view');

// Toggle the JavaScript mode (no-JS fallback) for the modern views.
Route::get('/site/js/{to}', [JsModeController::class, 'switch'])
    ->whereIn('to', ['on', 'off'])
    ->name('site.js');

// Toggle light/dark theme for the no-JS modern views.
Route::get('/site/theme/{to}', [ThemeController::class, 'switch'])
    ->whereIn('to', ['dark', 'light'])
    ->name('site.theme');
