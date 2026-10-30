<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LatestPacksRequest;
use App\Http\Requests\PaginatePacksRequest;
use App\Http\Requests\SearchPacksRequest;
use App\Http\Resources\PackResource;
use App\Models\Bot;
use App\Models\Pack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PackController extends Controller
{
    public function botPacks(int $botId): AnonymousResourceCollection
    {
        $bot = Bot::listable()->findOrFail($botId);

        return PackResource::collection(
            $bot->packs()->sorted(Pack::DEFAULT_SORT, 'asc')->get()
        );
    }

    public function botPacksPage(PaginatePacksRequest $request, int $botId): AnonymousResourceCollection
    {
        $bot = Bot::listable()->findOrFail($botId);

        return PackResource::collection(
            $bot->packs()
                ->forEpisode($request->episodeNumber())
                ->sorted($request->sort(), $request->direction())
                ->paginate(perPage: $request->size(), page: $request->page())
        );
    }

    public function latest(LatestPacksRequest $request): AnonymousResourceCollection
    {
        return PackResource::collection(
            Pack::ofActiveBots()
                ->orderByDesc('last_modified')
                ->limit($request->size())
                ->get()
        );
    }

    public function search(SearchPacksRequest $request): AnonymousResourceCollection
    {
        return PackResource::collection($this->searchQuery($request)->get());
    }

    public function searchPage(SearchPacksRequest $request): AnonymousResourceCollection
    {
        return PackResource::collection(
            $this->searchQuery($request)->paginate(perPage: $request->size(), page: $request->page())
        );
    }

    public function searchInBot(SearchPacksRequest $request, int $botId): AnonymousResourceCollection
    {
        Bot::listable()->findOrFail($botId);

        return PackResource::collection($this->searchQuery($request, $botId)->get());
    }

    public function searchInBotPage(SearchPacksRequest $request, int $botId): AnonymousResourceCollection
    {
        Bot::listable()->findOrFail($botId);

        return PackResource::collection(
            $this->searchQuery($request, $botId)->paginate(perPage: $request->size(), page: $request->page())
        );
    }

    /**
     * @return Builder<Pack>
     */
    private function searchQuery(SearchPacksRequest $request, ?int $botId = null): Builder
    {
        $packs = Pack::ofActiveBots()
            ->with('bot:id,name,batchenable')
            ->search($request->searchTerm())
            ->forEpisode($request->episodeNumber())
            ->sorted($request->sort(), $request->direction());

        return $botId === null ? $packs : $packs->where('bot_id', $botId);
    }
}
