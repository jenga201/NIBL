<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meilisearch read model
    |--------------------------------------------------------------------------
    |
    | When enabled, the pack search/sort endpoints are served from Meilisearch
    | (see Plans/NIBL-MEILISEARCH/plan.md). When disabled, they fall back to the
    | Eloquent path so tests and non-Meili environments keep working unchanged.
    |
    */

    'enabled' => env('MEILI_ENABLED', false),
    'host' => env('MEILI_HOST', 'http://meilisearch:7700'),
    'key' => env('MEILI_MASTER_KEY'),
    'index' => env('MEILI_INDEX', 'packs'),
];
