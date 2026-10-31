<?php

namespace App\Infrastructure\Service;

use App\Http\Resources\PackResource;
use App\Models\Bot;
use Illuminate\Support\Facades\Cache;

final class PackCache
{
    /** Entries live ~6h, but are normally superseded by last_processed bumping. */
    public const TTL_SECONDS = 21600;

    /** Cache-aside for a bot's pack page; returns the serialized JSON payload. */
    public function botPage(Bot $bot, string $sort, string $direction, int $page, int $size): string
    {
        $key = $this->key($bot, $sort, $direction, $page, $size);

        return Cache::store('redis')->remember(
            $key,
            self::TTL_SECONDS,
            fn (): string => PackResource::collection(
                $bot->packs()
                    ->sorted($sort, $direction)
                    ->paginate(perPage: $size, page: $page)
            )->toResponse(request())->getContent()
        );
    }

    /** Pre-warm the common first-page sort combos for a bot that just changed. */
    public function warm(Bot $bot): void
    {
        foreach ([['number', 'desc'], ['name', 'asc'], ['sizekbits', 'desc']] as [$sort, $direction]) {
            $this->botPage($bot, $sort, $direction, 1, 100);
        }
    }

    private function key(Bot $bot, string $sort, string $direction, int $page, int $size): string
    {
        $version = $bot->last_processed?->timestamp ?? 0;

        return "packs:{$bot->id}:{$sort}:{$direction}:{$page}:{$size}:{$version}";
    }
}
