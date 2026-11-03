<?php

namespace App\Infrastructure\Service;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;

final class HTMLPurify
{
    public static function purify(string $dirtyHtml)
    {
        $config = HTMLPurifier_Config::createDefault();
        $serializerPath = storage_path('htmlpurifier');
        File::ensureDirectoryExists($serializerPath);

        $config->set('Cache.SerializerPath', $serializerPath);
        $purifier = new HTMLPurifier($config);

        return $purifier->purify($dirtyHtml);
    }
}
