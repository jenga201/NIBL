<?php

namespace App\Domain;

enum SiteView: string
{
    case Modern = 'modern';
    case Legacy = 'legacy';

    public const COOKIE = 'nibl_view';

    public function isLegacy(): bool
    {
        return $this === self::Legacy;
    }

    public static function fromCookie(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Modern;
    }
}
