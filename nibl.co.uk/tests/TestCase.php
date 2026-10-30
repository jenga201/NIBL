<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // The var/ storage tree is created at container start in production
        // (scripts/start-apache) but is gitignored, so make sure the compiled
        // views directory exists before the app boots: the config default
        // resolves through realpath() and is false while the dir is missing.
        $compiledViews = dirname(__DIR__).'/var/framework/views';
        if (! is_dir($compiledViews)) {
            mkdir($compiledViews, 0775, true);
        }

        parent::setUp();

        $this->registerSqliteRegexp();
    }

    /**
     * SQLite has no built-in REGEXP operator; emulate MySQL's case-insensitive
     * REGEXP so pack search queries behave the same under tests.
     */
    private function registerSqliteRegexp(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();
        if (! method_exists($pdo, 'sqliteCreateFunction')) {
            return;
        }

        $pdo->sqliteCreateFunction(
            'regexp',
            static fn ($pattern, $subject): int => (int) preg_match(
                '~'.str_replace('~', '\~', (string) $pattern).'~i',
                (string) $subject
            )
        );
    }
}
