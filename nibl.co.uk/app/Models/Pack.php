<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pack extends Model
{
    public const DEFAULT_SORT = 'name';

    public const DEFAULT_PAGE_SIZE = 50;

    public const MAX_PAGE_SIZE = 1000;

    /**
     * Whitelisted sort keys (public name => column). Accepts both the
     * snake_case names and the camelCase spellings of the legacy API.
     */
    public const SORTABLE = [
        'id' => 'id',
        'bot_id' => 'bot_id',
        'botId' => 'bot_id',
        'number' => 'number',
        'name' => 'name',
        'size' => 'size',
        'sizekbits' => 'sizekbits',
        'episode_number' => 'episode_number',
        'episodeNumber' => 'episode_number',
        'last_modified' => 'last_modified',
        'lastModified' => 'last_modified',
        'bot_name' => 'bot_name',
        'botName' => 'bot_name',
    ];

    protected $table = 'updatepacklist_packs';

    public $timestamps = false;

    protected $fillable = [
        'bot_id', 'number', 'name', 'size', 'sizekbits', 'episode_number', 'last_modified',
    ];

    protected $casts = [
        'last_modified' => 'datetime',
        'sizekbits' => 'integer',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class, 'bot_id');
    }

    /** Packs of active bots — the set the legacy API served. */
    public function scopeOfActiveBots(Builder $query): Builder
    {
        return $query->whereHas('bot', static fn (Builder $bot) => $bot->active());
    }

    /**
     * Case-insensitive search where every whitespace-separated term must
     * appear, in order (parity with the legacy API's regex matching).
     */
    public function scopeSearch(Builder $query, string $terms): Builder
    {
        return $query->whereRaw('updatepacklist_packs.name REGEXP ?', [self::buildSearchPattern($terms)]);
    }

    public function scopeForEpisode(Builder $query, int $episodeNumber): Builder
    {
        return $episodeNumber > 0 ? $query->where('episode_number', $episodeNumber) : $query;
    }

    public function scopeSorted(Builder $query, string $by, string $direction): Builder
    {
        $column = self::SORTABLE[$by] ?? self::SORTABLE[self::DEFAULT_SORT];
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        // bot_name is a pseudo-column on the related bots table. Order via a
        // correlated subquery so no join is introduced — the query stays
        // unambiguous and the regex `name` filter keeps resolving to packs.
        if ($column === 'bot_name') {
            return $query->orderByRaw(
                '(SELECT updatepacklist_bots.name FROM updatepacklist_bots WHERE updatepacklist_bots.id = updatepacklist_packs.bot_id) '.$dir
            );
        }

        return $query->orderBy($column, $dir);
    }

    /** Build the ordered-terms regex: "a b" becomes "a.*b". */
    public static function buildSearchPattern(string $query): string
    {
        $terms = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('.*', array_map(self::escapeRegexTerm(...), $terms));
    }

    private static function escapeRegexTerm(string $term): string
    {
        return preg_replace('/([\\\\.+*?()\[\]{}^$|])/', '\\\\$1', $term) ?? $term;
    }
}
