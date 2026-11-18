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
     * @return array{packs: Pack[], total: int, page: int, hasPrev: bool, hasNext: bool, lastPage: int}
     */
    public function searchInBot(Bot $bot, BotQueryDto $botQuery): array
    {
        return $this->botPacksPage(
            $bot,
            (string) $botQuery->getQueryDto()->getValue(),
            $botQuery->getPageNumber()
        );
    }

    /**
     * @return array{packs: Pack[], total: int, page: int, hasPrev: bool, hasNext: bool, lastPage: int}
     */
    public function latestBotPacks(Bot $bot, BotQueryDto $botQuery): array
    {
        return $this->botPacksPage($bot, '', $botQuery->getPageNumber());
    }

    /**
     * One page of a bot's packs, ordered by XDCC pack number (highest first) —
     * the same order the modern site uses — with offset pagination and a
     * total so the view can render prev/next navigation.
     *
     * @return array{packs: Pack[], total: int, page: int, hasPrev: bool, hasNext: bool, lastPage: int}
     */
    private function botPacksPage(Bot $bot, string $search, int $page): array
    {
        $query = PackModel::with('bot')->where('bot_id', $bot->getId());

        if ($search !== '') {
            $query->search($search);
        }

        $total = $query->count();

        $packs = (clone $query)
            ->orderByDesc('number')
            ->orderByDesc('id')
            ->offset($page * self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE)
            ->get();

        return [
            'packs' => $this->toDomain($packs),
            'total' => $total,
            'page' => $page,
            'hasPrev' => $page > 0,
            'hasNext' => ($page + 1) * self::PAGE_SIZE < $total,
            'lastPage' => $total > 0 ? intdiv($total - 1, self::PAGE_SIZE) : 0,
        ];
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
