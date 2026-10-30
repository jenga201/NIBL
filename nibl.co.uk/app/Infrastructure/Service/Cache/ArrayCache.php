<?php

namespace App\Infrastructure\Service\Cache;

/**
 * Per-process in-memory cache; useful for tests and CLI runs where neither
 * APCu nor Redis is available.
 */
final class ArrayCache implements DataCache
{
    /** @var array<string, string> */
    private array $items = [];

    public function getItem(string $key): ?string
    {
        return $this->items[$key] ?? null;
    }

    public function setItem(string $key, string $value, int $expire = 60): void
    {
        $this->items[$key] = $value;
    }
}
