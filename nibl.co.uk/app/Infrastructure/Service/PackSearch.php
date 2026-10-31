<?php

namespace App\Infrastructure\Service;

use App\Models\Bot;
use App\Models\Pack;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;

/**
 * Read model for packs backed by Meilisearch: tokenized/typo-tolerant search
 * plus sortable, deep-paginated listings. MySQL stays the system of record; a
 * minute sync worker (meilisearch:sync) keeps this index current.
 *
 * When Meilisearch is disabled (config MEILI_ENABLED=false, e.g. tests), every
 * read falls back to the Eloquent queries this replaced so behaviour is
 * identical.
 */
final class PackSearch
{
    private const INDEX = 'packs';

    public function enabled(): bool
    {
        return (bool) config('meili.enabled');
    }

    private function client(): Client
    {
        return new Client((string) config('meili.host'), config('meili.key'));
    }

    private function index()
    {
        return $this->client()->index((string) config('meili.index'));
    }

    // ------------------------------------------------------------------
    // Read path
    // ------------------------------------------------------------------

    /**
     * @return LengthAwarePaginator<Pack>
     */
    public function botPage(Bot $bot, string $sort, string $direction, int $page, int $size, int $episodeNumber = 0): LengthAwarePaginator
    {
        if (! $this->enabled()) {
            return $bot->packs()
                ->forEpisode($episodeNumber)
                ->sorted($sort, $direction)
                ->paginate($size, ['*'], 'page', $page);
        }

        $filters = ["bot_id = {$bot->id}"];
        if ($episodeNumber > 0) {
            $filters[] = "episode_number = {$episodeNumber}";
        }

        return $this->meiliPaginate(
            null,
            ['filter' => $filters, 'sort' => [$this->sortString($sort, $direction)]],
            $page,
            $size,
            false
        );
    }

    /**
     * @return LengthAwarePaginator<Pack>
     */
    public function searchPage(string $query, ?int $botId, string $sort, string $direction, int $page, int $size, int $episodeNumber = 0): LengthAwarePaginator
    {
        if (! $this->enabled()) {
            $packs = Pack::ofActiveBots()
                ->with('bot:id,name,batchenable')
                ->search($query)
                ->forEpisode($episodeNumber)
                ->sorted($sort, $direction);

            if ($botId !== null) {
                $packs->where('bot_id', $botId);
            }

            return $packs->paginate($size, ['*'], 'page', $page);
        }

        $filters = ['bot_active = true'];
        if ($botId !== null) {
            $filters[] = "bot_id = {$botId}";
        }
        if ($episodeNumber > 0) {
            $filters[] = "episode_number = {$episodeNumber}";
        }

        return $this->meiliPaginate(
            $query,
            [
                'filter' => $filters,
                'sort' => [$this->sortString($sort, $direction)],
                'matchingStrategy' => 'all',
            ],
            $page,
            $size,
            true
        );
    }

    /**
     * @return Collection<int, Pack>
     */
    public function latest(int $size): Collection
    {
        if (! $this->enabled()) {
            return Pack::ofActiveBots()
                ->orderByDesc('last_modified')
                ->limit($size)
                ->get();
        }

        $result = $this->index()->search(null, [
            'filter' => 'bot_active = true',
            'sort' => ['last_modified:desc'],
            'limit' => $size,
        ]);

        return collect($result->getHits())->map(fn (array $hit) => $this->hydrate($hit, false));
    }

    // ------------------------------------------------------------------
    // Sync
    // ------------------------------------------------------------------

    /**
     * Re-index every bot whose packs, status or informative flag changed since
     * the last run, and de-index any bot deleted from MySQL. Returns the number
     * of bots touched.
     */
    public function sync(): int
    {
        $this->configure();

        $bots = Bot::query()->get(['id', 'name', 'status_id', 'informative', 'batchenable', 'last_processed']);
        $synced = DB::table('meilisearch_sync')->get()->keyBy('bot_id');

        $indexed = 0;
        foreach ($bots as $bot) {
            $row = $synced->get((int) $bot->id);
            $dirty = $row === null
                || (string) $row->last_processed !== (string) $bot->last_processed
                || (int) $row->status_id !== (int) $bot->status_id
                || (int) $row->informative !== (int) $bot->informative;

            if (! $dirty) {
                continue;
            }

            $this->indexBot($bot);

            DB::table('meilisearch_sync')->updateOrInsert(
                ['bot_id' => $bot->id],
                [
                    'last_processed' => $bot->last_processed,
                    'status_id' => $bot->status_id,
                    'informative' => $bot->informative,
                ]
            );
            $indexed++;
        }

        // Drop docs for bots that no longer exist in MySQL.
        $currentIds = $bots->pluck('id')->map(static fn ($id) => (int) $id)->all();
        foreach ($synced as $botId => $row) {
            if (! in_array((int) $botId, $currentIds, true)) {
                $this->index()->deleteDocuments(['filter' => "bot_id = {$botId}"]);
                DB::table('meilisearch_sync')->where('bot_id', $botId)->delete();
                $indexed++;
            }
        }

        return $indexed;
    }

    private function configure(): void
    {
        $index = $this->index();

        // Meilisearch requires every sortable attribute to also be filterable.
        $index->updateFilterableAttributes([
            'id', 'bot_id', 'bot_name', 'bot_active', 'number', 'name', 'size', 'sizekbits', 'episode_number', 'last_modified',
        ]);
        $index->updateSortableAttributes([
            'id', 'bot_id', 'bot_name', 'number', 'name', 'size', 'sizekbits', 'episode_number', 'last_modified',
        ]);
        $index->updateSearchableAttributes(['name']);

        // Without this, Meilisearch caps the pagination window at 1000 total
        // hits, so deep pages (e.g. SakuraServ page 2200) come back empty.
        $index->updatePagination(['maxTotalHits' => 1000000]);

        // Allow mistakes on short words (2+ chars) so "Mo" matches "Ma". The
        // old REGEXP search did substring matching, so short-word typos are
        // expected behaviour here; keep two-typo tolerance on longer words only.
        $index->updateTypoTolerance(['minWordSizeForTypos' => ['oneTypo' => 2, 'twoTypos' => 5]]);
    }

    private function indexBot(Bot $bot): void
    {
        $index = $this->index();

        // Remove any packs that disappeared from this bot's current list.
        $index->deleteDocuments(['filter' => "bot_id = {$bot->id}"]);

        // Stream in chunks so a 220k-pack bot stays well under the PHP memory
        // limit (loading the whole relation at once exhausted 256M).
        $bot->packs()
            ->select(['id', 'bot_id', 'number', 'name', 'size', 'sizekbits', 'episode_number', 'last_modified'])
            ->chunkById(5000, function ($packs) use ($index, $bot): void {
                $docs = $packs
                    ->map(fn (Pack $pack): array => $this->toDoc($pack, $bot))
                    ->values()
                    ->all();

                $index->addDocuments($docs, 'id');
            });
    }

    private function toDoc(Pack $pack, Bot $bot): array
    {
        return [
            'id' => (int) $pack->id,
            'bot_id' => (int) $pack->bot_id,
            'bot_name' => $bot->name,
            'bot_active' => (int) $bot->status_id === 1 && (int) $bot->informative === 0,
            'bot_batch_enabled' => (bool) $bot->batchenable,
            'number' => (int) $pack->number,
            'name' => (string) $pack->name,
            'size' => (string) $pack->size,
            'sizekbits' => (int) $pack->sizekbits,
            'episode_number' => $pack->episode_number === null ? null : (int) $pack->episode_number,
            'last_modified' => $pack->last_modified->toJSON(),
        ];
    }

    private function meiliPaginate(?string $query, array $params, int $page, int $size, bool $withBot): LengthAwarePaginator
    {
        $result = $this->index()->search($query, array_merge($params, [
            'page' => $page,
            'hitsPerPage' => $size,
        ]));

        $estimated = $result->getEstimatedTotalHits();
        $total = $estimated !== null ? (int) $estimated : (int) $result->getTotalHits();

        $items = array_map(fn (array $hit) => $this->hydrate($hit, $withBot), $result->getHits());

        return new LengthAwarePaginator($items, $total, $size, $page, [
            'path' => request()->url(),
        ]);
    }

    private function hydrate(array $hit, bool $withBot): Pack
    {
        $pack = new Pack();
        $pack->id = (int) $hit['id'];
        $pack->bot_id = (int) $hit['bot_id'];
        $pack->number = (int) $hit['number'];
        $pack->name = (string) $hit['name'];
        $pack->size = (string) $hit['size'];
        $pack->sizekbits = (int) $hit['sizekbits'];
        $pack->episode_number = $hit['episode_number'] === null ? null : (int) $hit['episode_number'];
        $pack->last_modified = $hit['last_modified'];

        if ($withBot) {
            $bot = new Bot();
            $bot->id = (int) $hit['bot_id'];
            $bot->name = (string) ($hit['bot_name'] ?? '');
            $bot->batchenable = (bool) ($hit['bot_batch_enabled'] ?? false);
            $pack->setRelation('bot', $bot);
        }

        return $pack;
    }

    private function sortString(string $sort, string $direction): string
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $sort.':'.$dir;
    }
}
