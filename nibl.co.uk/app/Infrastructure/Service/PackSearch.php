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

    /** Sortable columns with a (bot_id, X) composite index — the keyset page. */
    private const KEYSET_COLUMNS = [
        'number' => 'number',
        'name' => 'name',
        'sizekbits' => 'sizekbits',
    ];

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
     * Cursor (keyset) pagination for a bot's pack list. The cursor encodes the
     * boundary (sort value + id) of the previous page; a deep page seeks from
     * there instead of skipping O(offset). Meilisearch serves the search path
     * via a range filter + sort; the Eloquent fallback mirrors it on MySQL.
     *
     * @return array{packs: Collection<int, Pack>, total: int, next: ?string, prev: ?string}
     */
    public function botPage(Bot $bot, string $sort, string $direction, int $size, ?string $cursor, int $episodeNumber = 0): array
    {
        if ($this->enabled()) {
            return $this->meiliKeysetPage($bot, $sort, $direction, $size, $cursor, $episodeNumber);
        }

        return $this->eloquentKeysetPage($bot, $sort, $direction, $size, $cursor, $episodeNumber);
    }

    private function eloquentKeysetPage(Bot $bot, string $sort, string $direction, int $size, ?string $cursor, int $episodeNumber): array
    {
        $query = $bot->packs();
        if ($episodeNumber > 0) {
            $query->where('episode_number', $episodeNumber);
        }

        $column = self::KEYSET_COLUMNS[$sort] ?? null;
        if ($column === null) {
            $paginator = (clone $query)->sorted($sort, $direction)->paginate($size, ['*'], 'page', 1);

            return [
                'packs' => $paginator->getCollection(),
                'total' => $paginator->total(),
                'next' => null,
                'prev' => null,
            ];
        }

        $asc = strtolower($direction) !== 'desc';

        if ($cursor === null) {
            $rows = (clone $query)
                ->orderBy($column, $asc ? 'asc' : 'desc')
                ->orderBy('id', $asc ? 'asc' : 'desc')
                ->limit($size + 1)
                ->get();

            $hasNext = $rows->count() > $size;
            $rows = $rows->slice(0, $size)->values();
            $last = $rows->last();

            return [
                'packs' => $rows,
                'total' => $query->count(),
                'next' => $hasNext && $last !== null
                    ? $this->encodeCursor($sort, $direction, 'after', $last->{$column}, (int) $last->id)
                    : null,
                'prev' => null,
            ];
        }

        $boundary = $this->decodeCursor($cursor);
        if ($boundary === null) {
            return $this->botPage($bot, $sort, $direction, $size, null, $episodeNumber);
        }

        $forward = ($boundary['o'] ?? 'after') === 'after';
        $rows = $this->keysetQuery($query, $column, $asc, $forward, $boundary['v'], (int) $boundary['i'], $size + 1);

        $hasMore = $rows->count() > $size;
        $rows = $rows->slice(0, $size)->values();
        $first = $rows->first();
        $last = $rows->last();

        return [
            'packs' => $rows,
            'total' => $query->count(),
            'next' => $forward
                ? ($hasMore && $last !== null ? $this->encodeCursor($sort, $direction, 'after', $last->{$column}, (int) $last->id) : null)
                : ($last !== null ? $this->encodeCursor($sort, $direction, 'after', $last->{$column}, (int) $last->id) : null),
            'prev' => $forward
                ? ($first !== null ? $this->encodeCursor($sort, $direction, 'before', $first->{$column}, (int) $first->id) : null)
                : ($hasMore && $first !== null ? $this->encodeCursor($sort, $direction, 'before', $first->{$column}, (int) $first->id) : null),
        ];
    }

    private function meiliKeysetPage(Bot $bot, string $sort, string $direction, int $size, ?string $cursor, int $episodeNumber): array
    {
        $asc = strtolower($direction) !== 'desc';
        $dir = $asc ? 'asc' : 'desc';
        $sortList = [$sort.':'.$dir, 'id:'.$dir];

        $filters = ["bot_id = {$bot->id}"];
        if ($episodeNumber > 0) {
            $filters[] = "episode_number = {$episodeNumber}";
        }

        if (! isset(self::KEYSET_COLUMNS[$sort])) {
            $result = $this->index()->search(null, ['filter' => $filters, 'sort' => $sortList, 'limit' => $size]);

            return [
                'packs' => $this->hydrateAll($result->getHits()),
                'total' => (int) $result->getEstimatedTotalHits(),
                'next' => null,
                'prev' => null,
            ];
        }

        if ($cursor === null) {
            $result = $this->index()->search(null, ['filter' => $filters, 'sort' => $sortList, 'limit' => $size + 1]);
            $hits = $result->getHits();
            $hasNext = count($hits) > $size;
            $hits = array_slice($hits, 0, $size);
            $last = $hits === [] ? null : $hits[count($hits) - 1];

            return [
                'packs' => $this->hydrateAll($hits),
                'total' => (int) $result->getEstimatedTotalHits(),
                'next' => $hasNext && $last !== null
                    ? $this->encodeCursor($sort, $direction, 'after', $last[$sort], (int) $last['id'])
                    : null,
                'prev' => null,
            ];
        }

        $boundary = $this->decodeCursor($cursor);
        if ($boundary === null) {
            return $this->meiliKeysetPage($bot, $sort, $direction, $size, null, $episodeNumber);
        }

        $forward = ($boundary['o'] ?? 'after') === 'after';
        $result = $this->meiliKeysetSearch($filters, $sort, $dir, $asc, $forward, $boundary['v'], (int) $boundary['i'], $size + 1);
        $hits = $result->getHits();

        if (! $forward) {
            $hits = array_reverse($hits);
        }

        $hasMore = count($hits) > $size;
        $hits = array_slice($hits, 0, $size);
        $first = $hits === [] ? null : $hits[0];
        $last = $hits === [] ? null : $hits[count($hits) - 1];

        return [
            'packs' => $this->hydrateAll($hits),
            'total' => (int) $result->getEstimatedTotalHits(),
            'next' => $forward
                ? ($hasMore && $last !== null ? $this->encodeCursor($sort, $direction, 'after', $last[$sort], (int) $last['id']) : null)
                : ($last !== null ? $this->encodeCursor($sort, $direction, 'after', $last[$sort], (int) $last['id']) : null),
            'prev' => $forward
                ? ($first !== null ? $this->encodeCursor($sort, $direction, 'before', $first[$sort], (int) $first['id']) : null)
                : ($hasMore && $first !== null ? $this->encodeCursor($sort, $direction, 'before', $first[$sort], (int) $first['id']) : null),
        ];
    }

    private function meiliKeysetSearch(array $filters, string $sort, string $dir, bool $asc, bool $forward, $value, int $id, int $limit)
    {
        $cmp = ($forward === $asc) ? '>' : '<';
        $fetch = $forward ? $dir : ($asc ? 'desc' : 'asc');
        $v = $this->meiliValue($sort, $value);

        return $this->index()->search(null, [
            'filter' => array_merge($filters, ["{$sort} {$cmp} {$v} OR ({$sort} = {$v} AND id {$cmp} {$id})"]),
            'sort' => [$sort.':'.$fetch, 'id:'.$fetch],
            'limit' => $limit,
        ]);
    }

    private function meiliValue(string $sort, $value): string
    {
        return is_string($value) ? '"'.addslashes($value).'"' : (string) $value;
    }

    private function hydrateAll(array $hits): Collection
    {
        return collect($hits)->map(fn (array $hit) => $this->hydrate($hit, false))->values();
    }

    private function keysetQuery($query, string $column, bool $asc, bool $forward, $value, int $id, int $limit): Collection
    {
        $cmp = ($forward === $asc) ? '>' : '<';
        $natural = $asc ? 'asc' : 'desc';
        $fetch = $forward ? $natural : ($asc ? 'desc' : 'asc');

        $rows = (clone $query)
            ->where(function ($q) use ($column, $cmp, $value, $id) {
                $q->where($column, $cmp, $value)
                  ->orWhere(function ($q2) use ($column, $value, $cmp, $id) {
                      $q2->where($column, $value)->where('id', $cmp, $id);
                  });
            })
            ->orderBy($column, $fetch)
            ->orderBy('id', $fetch)
            ->limit($limit)
            ->get();

        return $forward ? $rows : $rows->reverse()->values();
    }

    private function encodeCursor(string $sort, string $direction, string $op, $value, int $id): ?string
    {
        $encoded = base64_encode(json_encode([
            'k' => $sort,
            'd' => strtolower($direction) === 'desc' ? 'desc' : 'asc',
            'o' => $op,
            'v' => $value,
            'i' => $id,
        ]));

        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    private function decodeCursor(string $cursor): ?array
    {
        $decoded = json_decode(base64_decode(strtr($cursor, '-_', '+/')), true);

        if (! is_array($decoded) || ! array_key_exists('v', $decoded) || ! array_key_exists('i', $decoded)) {
            return null;
        }

        return $decoded;
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
