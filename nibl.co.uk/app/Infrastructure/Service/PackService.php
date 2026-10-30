<?php

namespace App\Infrastructure\Service;

use App\Domain\Bot;
use App\Domain\BotQueryDto;
use App\Domain\Pack;
use App\Domain\QueryDto;
use App\Models\Pack as PackModel;
use Illuminate\Database\Eloquent\Collection;

final class PackService
{
    /** Result window per page, matching the legacy site behaviour. */
    private const PAGE_SIZE = 1000;

    /**
     * @return Pack[]
     */
    public function latest(int $limit = 50): array
    {
        return $this->toDomain(
            PackModel::ofActiveBots()
                ->with('bot')
                ->orderByDesc('last_modified')
                ->limit($limit)
                ->get()
        );
    }

    /**
     * @return Pack[]
     */
    public function search(QueryDto $queryDto, int $page = 0): array
    {
        $sort = $queryDto->getSortData();

        return $this->toDomain(
            PackModel::ofActiveBots()
                ->with('bot')
                ->search((string) $queryDto->getValue())
                ->sorted($sort->getBy(), (string) $sort->getDirection())
                ->offset($page * self::PAGE_SIZE)
                ->limit(self::PAGE_SIZE)
                ->get()
        );
    }

    /**
     * @return Pack[]
     */
    public function searchInBot(Bot $bot, BotQueryDto $botQuery): array
    {
        $sort = $botQuery->getQueryDto()->getSortData();

        return $this->toDomain(
            PackModel::with('bot')
                ->where('bot_id', $bot->getId())
                ->search((string) $botQuery->getQueryDto()->getValue())
                ->sorted($sort->getBy(), (string) $sort->getDirection())
                ->offset($botQuery->getPageNumber() * self::PAGE_SIZE)
                ->limit(self::PAGE_SIZE)
                ->get()
        );
    }

    /**
     * @return Pack[]
     */
    public function latestBotPacks(Bot $bot, BotQueryDto $botQuery): array
    {
        $sort = $botQuery->getQueryDto()->getSortData();

        return $this->toDomain(
            PackModel::with('bot')
                ->where('bot_id', $bot->getId())
                ->sorted($sort->getBy(), (string) $sort->getDirection())
                ->offset($botQuery->getPageNumber() * self::PAGE_SIZE)
                ->limit(self::PAGE_SIZE)
                ->get()
        );
    }

    /**
     * @param  Collection<int, PackModel>  $packs
     * @return Pack[]
     */
    private function toDomain(Collection $packs): array
    {
        return $packs
            ->toBase()
            ->map(static fn (PackModel $pack): Pack => Pack::fromModel($pack))
            ->all();
    }
}
