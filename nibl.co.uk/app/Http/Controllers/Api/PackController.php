<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LatestPacksRequest;
use App\Http\Requests\PaginatePacksRequest;
use App\Http\Requests\SearchPacksRequest;
use App\Http\Resources\PackResource;
use App\Infrastructure\Service\PackSearch;
use App\Models\Bot;
use App\Models\Pack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PackController extends Controller
{
    public function __construct(private readonly PackSearch $packSearch)
    {
    }

    /**
     * @param  array{packs: \Illuminate\Support\Collection<int, Pack>, total: int, next: ?string, prev: ?string}  $result
     */
    private function keysetJson(PaginatePacksRequest $request, array $result): JsonResponse
    {
        return response()->json([
            'data' => array_values(PackResource::collection($result['packs'])->toArray($request)),
            'meta' => [
                'total' => $result['total'],
                'per_page' => $request->size(),
                'next' => $result['next'],
                'prev' => $result['prev'],
            ],
        ]);
    }

    public function botPacks(int $botId): AnonymousResourceCollection
    {
        $bot = Bot::listable()->findOrFail($botId);

        return PackResource::collection(
            $bot->packs()->sorted(Pack::DEFAULT_SORT, 'asc')->get()
        );
    }

    public function botPacksPage(PaginatePacksRequest $request, int $botId): JsonResponse
    {
        $bot = Bot::listable()->findOrFail($botId);

        return $this->keysetJson(
            $request,
            $this->packSearch->keysetPage(
                $bot->id,
                '',
                $request->sort(),
                $request->direction(),
                $request->size(),
                $request->cursor(),
                $request->episodeNumber()
            )
        );
    }

    public function latest(LatestPacksRequest $request): AnonymousResourceCollection
    {
        return PackResource::collection($this->packSearch->latest($request->size()));
    }

    public function search(SearchPacksRequest $request): AnonymousResourceCollection
    {
        return PackResource::collection($this->searchQuery($request)->get());
    }

    public function searchPage(SearchPacksRequest $request): JsonResponse
    {
        return $this->keysetJson(
            $request,
            $this->packSearch->keysetPage(
                null,
                $request->searchTerm(),
                $request->sort(),
                $request->direction(),
                $request->size(),
                $request->cursor(),
                $request->episodeNumber()
            )
        );
    }

    public function searchInBot(SearchPacksRequest $request, int $botId): AnonymousResourceCollection
    {
        Bot::listable()->findOrFail($botId);

        return PackResource::collection($this->searchQuery($request, $botId)->get());
    }

    public function searchInBotPage(SearchPacksRequest $request, int $botId): JsonResponse
    {
        Bot::listable()->findOrFail($botId);

        return $this->keysetJson(
            $request,
            $this->packSearch->keysetPage(
                $botId,
                $request->searchTerm(),
                $request->sort(),
                $request->direction(),
                $request->size(),
                $request->cursor(),
                $request->episodeNumber()
            )
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
