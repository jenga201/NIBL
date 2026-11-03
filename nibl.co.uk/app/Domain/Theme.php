<?php

namespace App\Domain;

enum Theme: string
{
    case Light = 'light';
    case Dark = 'dark';

    public const COOKIE = 'nibl_theme';

    public function isDark(): bool
    {
        return $this === self::Dark;
    }

    public static function fromCookie(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Light;
    }

    public static function current(): self
    {
        return self::fromCookie(request()->cookie(self::COOKIE));
    }
}
