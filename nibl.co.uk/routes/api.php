<?php

use App\Http\Controllers\Api\BotController;
use App\Http\Controllers\Api\PackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NIBL API routes (mounted under the /api prefix — see bootstrap/app.php)
|--------------------------------------------------------------------------
| Replaces the deprecated api.nibl.co.uk (niblapi) service: the same paths,
| served straight from the ooinuza database with Laravel JSON responses.
*/

Route::get('bots', [BotController::class, 'index'])->name('api.bots.index');
Route::get('bots/{botId}', [BotController::class, 'show'])->whereNumber('botId')->name('api.bots.show');

Route::get('packs/{botId}', [PackController::class, 'botPacks'])->whereNumber('botId')->name('api.packs.bot');
Route::get('packs/{botId}/page', [PackController::class, 'botPacksPage'])->whereNumber('botId')->name('api.packs.bot.page');

Route::get('latest', [PackController::class, 'latest'])->name('api.latest');

Route::get('search', [PackController::class, 'search'])->name('api.search');
Route::get('search/page', [PackController::class, 'searchPage'])->name('api.search.page');
Route::get('search/{botId}', [PackController::class, 'searchInBot'])->whereNumber('botId')->name('api.search.bot');
Route::get('search/{botId}/page', [PackController::class, 'searchInBotPage'])->whereNumber('botId')->name('api.search.bot.page');
