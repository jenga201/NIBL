<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bot extends Model
{
    /** status_id value of a bot that is currently serving packs. */
    public const STATUS_ACTIVE = 1;

    protected $table = 'updatepacklist_bots';

    public $timestamps = false;

    protected $fillable = [
        'name', 'url', 'type', 'owner', 'status_id', 'last_seen',
        'last_processed', 'informative', 'batchenable', 'external', 'parser_id',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'last_processed' => 'datetime',
        'batchenable' => 'boolean',
        'external' => 'boolean',
    ];

    public function packs(): HasMany
    {
        return $this->hasMany(Pack::class, 'bot_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status_id', self::STATUS_ACTIVE);
    }

    /** Bots that the site and the API list: active and holding at least one pack. */
    public function scopeListable(Builder $query): Builder
    {
        return $query->active()->has('packs');
    }
}
