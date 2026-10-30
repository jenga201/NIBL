<?php

return [
    'api_path' => 'api',

    'openapi_version' => \Dedoc\Scramble\OpenApiVersion::V3_1,

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => 'NIBL IRC XDCC bot and pack API.',
    ],

    'ui' => [
        'title' => 'NIBL API',
    ],

        // Docs (OpenAPI JSON + Scramble's own UI) are public in every environment.
        'middleware' => ['web'],
    ];
