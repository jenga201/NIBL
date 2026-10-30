<?php

use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Legacy\LegacyPageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SiteViewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('index');
Route::get('/bots', [BotController::class, 'index'])->name('bots');
Route::get('/bots/{name}', [BotController::class, 'show'])->name('bot');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/search', [SearchController::class, 'index'])->name('search');
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
