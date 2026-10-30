<?php

namespace App\Infrastructure\Service;

final class LegacyThemeService
{
    public const COOKIE = 'theme';

    private const FALLBACK = 'default';

    /**
     * @return array<string, array{label: string, css: string}>
     */
    public function themes(): array
    {
        return config('nibl.legacy_themes', []);
    }

    public function current(?string $cookie): string
    {
        $cookie = (string) $cookie;

        return array_key_exists($cookie, $this->themes()) ? $cookie : self::FALLBACK;
    }

    public function stylesheet(?string $cookie): string
    {
        return $this->themes()[$this->current($cookie)]['css'];
    }
}
