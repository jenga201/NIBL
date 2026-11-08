<?php

namespace App\Infrastructure\Service;

use App\Events\PacksUpdated;
use App\Jobs\SyncBot;
use App\Models\Bot;
use App\Models\Pack;
use Illuminate\Database\Eloquent\Builder;
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

    /** MySQL columns (real packs columns) eligible for the keyset cursor. */
    private const KEYSET_COLUMNS = [
        'number' => 'number',
        'name' => 'name',
        'sizekbits' => 'sizekbits',
    ];

    /** Meilisearch sort keys eligible for the keyset cursor (adds bot_name). */
    private const MEILI_KEYSET_COLUMNS = [
        'number' => 'number',
        'name' => 'name',
        'sizekbits' => 'sizekbits',
        'bot_name' => 'bot_name',
    ];

    public function enabled(): bool
    {
        return (bool) config('meili.enabled');
    }

    private ?Client $client = null;

    /**
     * Prefer a container-bound client so tests can inject a fake; otherwise
     * build the real client from config lazily.
     */
    private function client(): Client
    {
        if ($this->client === null) {
            $this->client = app()->bound(Client::class)
                ? app(Client::class)
                : new Client((string) config('meili.host'), config('meili.key'));
        }

        return $this->client;
    }

    private function index()
    {
        return $this->client()->index((string) config('meili.index'));
    }

    // ------------------------------------------------------------------
    // Read path
    // ------------------------------------------------------------------

    /**
     * Cursor (keyset) pagination for a bot's pack list or a search across
     * packs. The cursor encodes the boundary (sort value + id) of the previous
     * page; a deep page seeks from there instead of skipping O(offset).
     * Meilisearch serves the search + sort via a range filter; the Eloquent
     * fallback mirrors it on MySQL.
     *
     * $botId === null → search across all active bots (query required).
     * $botId !== null → one bot's packs (query optional; search-in-bot).
     * $fuzzy=false → skip Meilisearch's typo-tolerant search and use the
     * exact Eloquent REGEXP matcher (legacy parity) even when Meili is on.
     *
     * @return array{packs: Collection<int, Pack>, total: int, next: ?string, prev: ?string}
     */
    public function keysetPage(?int $botId, string $query, string $sort, string $direction, int $size, ?string $cursor, int $episodeNumber = 0, bool $fuzzy = true): array
    {
        if ($this->enabled() && $fuzzy) {
            $filters = $botId === null ? ['bot_active = true'] : ["bot_id = {$botId}"];
            if ($episodeNumber > 0) {
                $filters[] = "episode_number = {$episodeNumber}";
            }
            $withBot = $botId === null;

            return $cursor === 'last'
                ? $this->meiliLastPage($filters, $query, $sort, $direction, $size, $withBot)
                : $this->meiliKeysetPage($filters, $query, $sort, $direction, $size, $cursor, $withBot);
        }

        return $cursor === 'last'
            ? $this->eloquentLastPage($botId, $query, $sort, $direction, $size, $episodeNumber)
            : $this->eloquentKeysetPage($botId, $query, $sort, $direction, $size, $cursor, $episodeNumber);
    }

    private function meiliLastPage(array $filters, string $query, string $sort, string $direction, int $size, bool $withBot): array
    {
        $asc = strtolower($direction) !== 'desc';
        $opposite = $asc ? 'desc' : 'asc';

        $result = $this->meiliSearch($query, [
            'filter' => $filters,
            'sort' => [$sort.':'.$opposite, 'id:'.$opposite],
            'limit' => $size + 1,
        ]);

        $hits = $result->getHits();
        $hasPrev = count($hits) > $size;
        $hits = array_reverse(array_slice($hits, 0, $size));
        $first = $hits === [] ? null : $hits[0];

        return [
            'packs' => $this->hydrateAll($hits, $withBot),
            'total' => (int) $result->getEstimatedTotalHits(),
            'next' => null,
            'prev' => $hasPrev && $first !== null
                ? $this->encodeCursor($sort, $direction, 'before', $first[$sort], (int) $first['id'])
                : null,
        ];
    }

    private function eloquentLastPage(?int $botId, string $query, string $sort, string $direction, int $size, int $episodeNumber): array
    {
        $builder = $this->searchQuery($botId, $query, $episodeNumber);

        $column = self::KEYSET_COLUMNS[$sort] ?? null;
        if ($column === null) {
            $paginator = (clone $builder)->sorted($sort, $direction)->paginate($size, ['*'], 'page', 1);

            return [
                'packs' => $paginator->getCollection(),
                'total' => $paginator->total(),
                'next' => null,
                'prev' => null,
            ];
        }

        $opposite = strtolower($direction) !== 'desc' ? 'desc' : 'asc';

        $rows = (clone $builder)
            ->orderBy($column, $opposite)
            ->orderBy('id', $opposite)
            ->limit($size + 1)
            ->get();

        $hasPrev = $rows->count() > $size;
        $rows = $rows->slice(0, $size)->reverse()->values();
        $first = $rows->first();

        return [
            'packs' => $rows,
            'total' => $builder->count(),
            'next' => null,
            'prev' => $hasPrev && $first !== null
                ? $this->encodeCursor($sort, $direction, 'before', $first->{$column}, (int) $first->id)
                : null,
        ];
    }

    private function eloquentKeysetPage(?int $botId, string $query, string $sort, string $direction, int $size, ?string $cursor, int $episodeNumber): array
    {
        $builder = $this->searchQuery($botId, $query, $episodeNumber);

        $column = self::KEYSET_COLUMNS[$sort] ?? null;
        if ($column === null) {
            $paginator = (clone $builder)->sorted($sort, $direction)->paginate($size, ['*'], 'page', 1);

            return [
                'packs' => $paginator->getCollection(),
                'total' => $paginator->total(),
                'next' => null,
                'prev' => null,
            ];
        }

        $asc = strtolower($direction) !== 'desc';

        if ($cursor === null) {
            $rows = (clone $builder)
                ->orderBy($column, $asc ? 'asc' : 'desc')
                ->orderBy('id', $asc ? 'asc' : 'desc')
                ->limit($size + 1)
                ->get();

            $hasNext = $rows->count() > $size;
            $rows = $rows->slice(0, $size)->values();
            $last = $rows->last();

            return [
                'packs' => $rows,
                'total' => $builder->count(),
                'next' => $hasNext && $last !== null
                    ? $this->encodeCursor($sort, $direction, 'after', $last->{$column}, (int) $last->id)
                    : null,
                'prev' => null,
            ];
        }

        $boundary = $this->decodeCursor($cursor);
        if ($boundary === null) {
            return $this->eloquentKeysetPage($botId, $query, $sort, $direction, $size, null, $episodeNumber);
        }

        $forward = ($boundary['o'] ?? 'after') === 'after';
        $rows = $this->keysetQuery($builder, $column, $asc, $forward, $boundary['v'], (int) $boundary['i'], $size + 1);

        $hasMore = $rows->count() > $size;
        $rows = $rows->slice(0, $size)->values();
        $first = $rows->first();
        $last = $rows->last();

        return [
            'packs' => $rows,
            'total' => $builder->count(),
            'next' => $forward
                ? ($hasMore && $last !== null ? $this->encodeCursor($sort, $direction, 'after', $last->{$column}, (int) $last->id) : null)
                : ($last !== null ? $this->encodeCursor($sort, $direction, 'after', $last->{$column}, (int) $last->id) : null),
            'prev' => $forward
                ? ($first !== null ? $this->encodeCursor($sort, $direction, 'before', $first->{$column}, (int) $first->id) : null)
                : ($hasMore && $first !== null ? $this->encodeCursor($sort, $direction, 'before', $first->{$column}, (int) $first->id) : null),
        ];
    }

    private function meiliKeysetPage(array $filters, string $query, string $sort, string $direction, int $size, ?string $cursor, bool $withBot): array
    {
        $asc = strtolower($direction) !== 'desc';
        $dir = $asc ? 'asc' : 'desc';
        $sortList = [$sort.':'.$dir, 'id:'.$dir];

        if (! isset(self::MEILI_KEYSET_COLUMNS[$sort])) {
            $result = $this->meiliSearch($query, ['filter' => $filters, 'sort' => $sortList, 'limit' => $size]);

            return [
                'packs' => $this->hydrateAll($result->getHits(), $withBot),
                'total' => (int) $result->getEstimatedTotalHits(),
                'next' => null,
                'prev' => null,
            ];
        }

        if ($cursor === null) {
            $result = $this->meiliSearch($query, ['filter' => $filters, 'sort' => $sortList, 'limit' => $size + 1]);
            $hits = $result->getHits();
            $hasNext = count($hits) > $size;
            $hits = array_slice($hits, 0, $size);
            $last = $hits === [] ? null : $hits[count($hits) - 1];

            return [
                'packs' => $this->hydrateAll($hits, $withBot),
                'total' => (int) $result->getEstimatedTotalHits(),
                'next' => $hasNext && $last !== null
                    ? $this->encodeCursor($sort, $direction, 'after', $last[$sort], (int) $last['id'])
                    : null,
                'prev' => null,
            ];
        }

        $boundary = $this->decodeCursor($cursor);
        if ($boundary === null) {
            return $this->meiliKeysetPage($filters, $query, $sort, $direction, $size, null, $withBot);
        }

        $forward = ($boundary['o'] ?? 'after') === 'after';
        $result = $this->meiliKeysetSearch($filters, $query, $sort, $dir, $asc, $forward, $boundary['v'], (int) $boundary['i'], $size + 1);
        $hits = $result->getHits();

        if (! $forward) {
            $hits = array_reverse($hits);
        }

        $hasMore = count($hits) > $size;
        $hits = array_slice($hits, 0, $size);
        $first = $hits === [] ? null : $hits[0];
        $last = $hits === [] ? null : $hits[count($hits) - 1];

        return [
            'packs' => $this->hydrateAll($hits, $withBot),
            'total' => (int) $result->getEstimatedTotalHits(),
            'next' => $forward
                ? ($hasMore && $last !== null ? $this->encodeCursor($sort, $direction, 'after', $last[$sort], (int) $last['id']) : null)
                : ($last !== null ? $this->encodeCursor($sort, $direction, 'after', $last[$sort], (int) $last['id']) : null),
            'prev' => $forward
                ? ($first !== null ? $this->encodeCursor($sort, $direction, 'before', $first[$sort], (int) $first['id']) : null)
                : ($hasMore && $first !== null ? $this->encodeCursor($sort, $direction, 'before', $first[$sort], (int) $first['id']) : null),
        ];
    }

    private function meiliKeysetSearch(array $filters, string $query, string $sort, string $dir, bool $asc, bool $forward, $value, int $id, int $limit)
    {
        $cmp = ($forward === $asc) ? '>' : '<';
        $fetch = $forward ? $dir : ($asc ? 'desc' : 'asc');
        $v = $this->meiliValue($sort, $value);

        return $this->meiliSearch($query, [
            'filter' => array_merge($filters, ["{$sort} {$cmp} {$v} OR ({$sort} = {$v} AND id {$cmp} {$id})"]),
            'sort' => [$sort.':'.$fetch, 'id:'.$fetch],
            'limit' => $limit,
        ]);
    }

    /**
     * Run a Meilisearch search, adding matchingStrategy=all (ordered-term
     * parity with the legacy REGEXP search) only when a query is present.
     */
    private function meiliSearch(string $query, array $params)
    {
        if ($query !== '') {
            $params['matchingStrategy'] = 'all';
        }

        return $this->index()->search($query === '' ? null : $query, $params);
    }

    /** @return Builder<Pack> */
    private function searchQuery(?int $botId, string $query, int $episodeNumber): Builder
    {
        $packs = Pack::ofActiveBots()
            ->with('bot:id,name,batchenable');

        if ($botId !== null) {
            $packs->where('bot_id', $botId);
        }
        if ($query !== '') {
            $packs->search($query);
        }
        if ($episodeNumber > 0) {
            $packs->forEpisode($episodeNumber);
        }

        return $packs;
    }

    private function meiliValue(string $sort, $value): string
    {
        return is_string($value) ? '"'.addslashes($value).'"' : (string) $value;
    }

    private function hydrateAll(array $hits, bool $withBot): Collection
    {
        return collect($hits)->map(fn (array $hit) => $this->hydrate($hit, $withBot))->values();
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
     * Enqueue a SyncBot job for every bot whose packs, status or informative
     * flag changed since the last run, and drop docs for bots deleted from
     * MySQL. Each job re-indexes or de-indexes based on the bot's current
     * state. Returns the number of bots dispatched.
     */
    public function sync(): int
    {
        $this->configure();

        // Only active bots are ever indexable. Purge any leftover inactive docs
        // so the read model stays exactly equal to the listable bots.
        $this->index()->deleteDocuments(['filter' => 'bot_active = false']);

        $bots = Bot::query()->get(['id', 'name', 'status_id', 'informative', 'batchenable', 'last_processed']);
        $synced = DB::table('meilisearch_sync')->get()->keyBy('bot_id');

        $dispatched = 0;
        foreach ($bots as $bot) {
            $row = $synced->get((int) $bot->id);
            $dirty = $row === null
                || (string) $row->last_processed !== (string) $bot->last_processed
                || (int) $row->status_id !== (int) $bot->status_id
                || (int) $row->informative !== (int) $bot->informative;

            if (! $dirty) {
                continue;
            }

            // The job decides index vs delete based on the bot's current state.
            SyncBot::dispatch((int) $bot->id);

            // Advance the watermark now so a still-running job isn't
            // re-dispatched next minute; the job resets it on failure.
            DB::table('meilisearch_sync')->updateOrInsert(
                ['bot_id' => $bot->id],
                [
                    'last_processed' => $bot->last_processed,
                    'status_id' => $bot->status_id,
                    'informative' => $bot->informative,
                ]
            );
            $dispatched++;
        }

        // Drop docs for bots that no longer exist in MySQL.
        $currentIds = $bots->pluck('id')->map(static fn ($id) => (int) $id)->all();
        foreach ($synced as $botId => $row) {
            if (! in_array((int) $botId, $currentIds, true)) {
                $this->deleteBot((int) $botId);
                DB::table('meilisearch_sync')->where('bot_id', $botId)->delete();
                $dispatched++;
            }
        }

        if ($dispatched > 0) {
            PacksUpdated::dispatch();
        }

        return $dispatched;
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

    public function indexBot(Bot $bot): void
    {
        $index = $this->index();

        // Remove any packs that disappeared from this bot's current list.
        $index->deleteDocuments(['filter' => "bot_id = {$bot->id}"]);

        // Stream in large batches: Meilisearch indexes one task at a time and
        // only parallelises *within* a task, so the old 5k-doc batches under-
        // used the indexing threads. 50k docs keeps the 220k-pack bot to ~5
        // POSTs and stays well under the 256M PHP memory limit.
        $bot->packs()
            ->select(['id', 'bot_id', 'number', 'name', 'size', 'sizekbits', 'episode_number', 'last_modified'])
            ->chunkById(50000, function ($packs) use ($index, $bot): void {
                $docs = $packs
                    ->map(fn (Pack $pack): array => $this->toDoc($pack, $bot))
                    ->values()
                    ->all();

                $index->addDocuments($docs, 'id');
            });
    }

    public function deleteBot(int $botId): void
    {
        $this->index()->deleteDocuments(['filter' => "bot_id = {$botId}"]);
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

    }
