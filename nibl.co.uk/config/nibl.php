<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data cache
    |--------------------------------------------------------------------------
    |
    | Driver used to cache news responses: "apcu", "redis" or "array".
    |
    */

    'data_cache' => env('NIBL_DATA_CACHE', 'apcu'),

    'redis' => [
        'host' => env('NIBL_REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('NIBL_REDIS_PORT', 6379),
        'prefix' => env('NIBL_REDIS_PREFIX', 'nibl'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP caching
    |--------------------------------------------------------------------------
    |
    | Browser cache lifetime (seconds) applied to every HTML response.
    |
    */

    'http_cache_max_age' => (int) env('NIBL_HTTP_CACHE_MAX_AGE', 86400),

    /*
    |--------------------------------------------------------------------------
    | Legacy site color themes
    |--------------------------------------------------------------------------
    |
    | Cookie name -> label + CSS file mapping for the nibl.co.uk-old skin.
    |
    */

    'legacy_themes' => [
        'default' => ['label' => 'Default', 'css' => 'style.css'],
        'default-fullwidth' => ['label' => 'Default (Full width)', 'css' => 'style-fullwidth.css'],
        'default-greenpurple' => ['label' => 'Green/Purple', 'css' => 'style-greenpurple.css'],
        'default-greenpurple-fullwidth' => ['label' => 'Green/Purple (Full width)', 'css' => 'style-greenpurple-fullwidth.css'],
        'default-blue' => ['label' => 'Blue', 'css' => 'style-blue.css'],
        'default-kittie' => ['label' => 'Kittie', 'css' => 'style-kittie.css'],
    ],

];
