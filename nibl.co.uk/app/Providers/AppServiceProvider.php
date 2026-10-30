<?php

namespace App\Providers;

use App\Infrastructure\Service\Cache\APCUCache;
use App\Infrastructure\Service\Cache\ArrayCache;
use App\Infrastructure\Service\Cache\DataCache;
use App\Infrastructure\Service\Cache\RedisCache;
use App\Infrastructure\Service\SiteNews\CachedSiteNewsRepository;
use App\Infrastructure\Service\SiteNews\PDOSiteNewsRepository;
use App\Infrastructure\Service\SiteNews\SiteNewsContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use PDO;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DataCache::class, static function () {
            if (config('nibl.data_cache') === 'redis') {
                $redis = config('nibl.redis');

                return new RedisCache($redis['host'], $redis['port'], $redis['prefix']);
            }

            if (config('nibl.data_cache') === 'array') {
                return new ArrayCache;
            }

            return new APCUCache;
        });

        // Legacy repositories talk to MySQL through a plain PDO instance;
        // reuse the default connection configured in config/database.php.
        $this->app->bind(PDO::class, static fn () => DB::connection()->getPdo());

        $this->app->bind(SiteNewsContract::class, static function (Application $app) {
            return new CachedSiteNewsRepository(
                $app->make(LoggerInterface::class),
                $app->make(PDOSiteNewsRepository::class),
                $app->make(DataCache::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
