<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BotResource;
use App\Models\Bot;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class BotController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BotResource::collection(
            Bot::listable()->withCount('packs')->orderBy('name')->get()
        );
    }

    public function show(int $botId): BotResource
    {
        return new BotResource(
            Bot::active()->withCount('packs')->findOrFail($botId)
        );
    }
}
