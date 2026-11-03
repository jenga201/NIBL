<?php

namespace App\Domain;

enum JsMode: string
{
    case On = 'on';
    case Off = 'off';

    public const COOKIE = 'nibl_js';

    public function isDisabled(): bool
    {
        return $this === self::Off;
    }

    public static function fromCookie(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::On;
    }

    public static function current(): self
    {
        return self::fromCookie(request()->cookie(self::COOKIE));
    }
}
